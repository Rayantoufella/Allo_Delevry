# Guide — 05 : Notifications et jobs asynchrones

**Périmètre :** `D:\AlloDelevry\backend`
**Entrées (routes/canaux) :**
- `GET /api/notifications` — liste paginée des notifications de l'utilisateur
- `GET /api/notifications/{id}` — détail d'une notification
- `PATCH /api/notifications/{id}/read` — marquer comme lue
- `PATCH /api/notifications/read-all` — marquer toutes comme lues
- Worker queue : service Docker `allo_queue` (`queue:work database`)

**Référence cahier des charges :** F13 (notifications), F14 (preuves/code confirmation), §9.2 (workflow IA), RG06

---

## Vue d'ensemble

Ce module gère les **notifications internes en base** (pas d'e-mail, pas de push) et les **jobs asynchrones** qui les produisent. Les notifications sont créées par des jobs dispatchés sur la **queue `database`** (table `jobs`) et traitées par le worker Docker `allo_queue`.

Trois types de notifications sont produits automatiquement :
1. **Création de demande** → notifie le livreur qu'un client a créé une demande
2. **Changement de statut** → notifie l'autre partie (client↔livreur) du nouveau statut
3. **Message chat** → notifie le destinataire qu'il a reçu un message

Deux jobs de maintenance complètent :
4. **Expiration du code** → expire le code de confirmation après 30 minutes
5. **Purge GPS** → supprime les positions GPS de plus de 7 jours

**Flux résumé :**
1. Un événement métier (création de demande, changement de statut, message chat) dispatch un job via `->afterCommit()`
2. Le job est inséré dans la table `jobs` (queue `database`)
3. Le worker `allo_queue` exécute le job et crée une `Notification` en base
4. Le frontend appelle `GET /api/notifications` pour afficher les notifications
5. L'utilisateur peut marquer les notifications comme lues via `PATCH`

---

## Fichiers et rôles (exhaustif)

### Modèle et base de données

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Models/Notification.php` | Modèle Eloquent — table `notifications` | Relations : `belongsTo(User)`, `belongsTo(DeliveryRequest)` ; cast `read_at` → datetime ; fillable : user_id, delivery_request_id, type, title, body, read_at |
| `database/migrations/2026_07_22_141321_create_notifications_table.php` | Migration de la table `notifications` | Colonnes : id, user_id (FK, cascadeDelete), delivery_request_id (FK, nullable, nullOnDelete), type (string), title (string), body (text, nullable), read_at (timestamp, nullable), timestamps |
| `database/migrations/2026_07_22_150000_create_gps_locations_table.php` | Migration de la table `gps_locations` (purge par `PruneGpsLocationsJob`) | Colonnes : id, delivery_request_id (FK, cascadeDelete), latitude (decimal 10,7), longitude (decimal 10,7), recorded_at (timestamp, nullable), timestamps |
| `database/migrations/0001_01_01_000002_create_jobs_table.php` | Migration des tables `jobs`, `job_batches`, `failed_jobs` | Table `jobs` : id, queue (index), payload (longText), attempts, reserved_at, available_at, created_at — supporte la queue `database` |

### Contrôleur et endpoints

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Http/Controllers/Api/NotificationController.php` | CRUD notifications (index, show, markAsRead, markAllAsRead) | `index()` : paginé (20), filtre par `user_id` ; `show()` : autorisé par `NotificationPolicy` ; `markAsRead()` : met à jour `read_at = now()` ; `markAllAsRead()` : met à jour toutes les non-lues de l'utilisateur |
| `app/Http/Controllers/Api/DeliveryRequestController.php` (hooks) | Point de dispatch des jobs notification | `storeForDriver()` : dispatch `CreateDeliveryRequestNotificationJob` après commit ; `generateCode()` : dispatch `ExpireConfirmationCodeJob` avec delay 30 min |
| `app/Models/DeliveryRequest.php` (`transitionTo()`) | Point de dispatch du job changement de statut | `CreateStatusChangedNotificationJob::dispatch($this, $newStatus, $changedBy)->afterCommit()` — voir guide 02 pour le détail complet |
| `app/Http/Controllers/Api/ChatMessageController.php` (`store()`) | Point de dispatch du job notification chat | `CreateChatMessageNotificationJob::dispatch($message)->afterCommit()` — voir guide 04 |

### Jobs asynchrones (6 jobs)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Jobs/CreateDeliveryRequestNotificationJob.php` | Notification : nouvelle demande créée | `tries = 3`, `timeout = 30`, `backoff = [10, 60]` ; crée une notif pour le livreur (`driver_id`) ; type : `delivery_request_created` ; titre : "Nouvelle demande de livraison" ; corps inclut le tracking_number |
| `app/Jobs/CreateStatusChangedNotificationJob.php` | Notification : changement de statut | `tries = 3`, `timeout = 30`, `backoff = [10, 60]` ; reçoit `$newStatus` + `$changedBy` ; notifie l'autre partie (inversé : driver→notif→client, client→notif→driver) ; ignorié si `changedBy === null` ; mapping FR des 9 statuts : en_attente, prix_propose, confirmee, colis_recupere, en_livraison, livree, refusee, echec, annulee ; type : `status_changed` |
| `app/Jobs/CreateChatMessageNotificationJob.php` | Notification : nouveau message chat | `tries = 3`, `timeout = 30`, `backoff = [10, 60]` ; identifie le destinataire (l'autre partie) ; corps : contenu tronqué à 120 caractères (`Str::limit`) ; type : `chat_message` ; titre : "Nouveau message" |
| `app/Jobs/ExpireConfirmationCodeJob.php` | Expiration du code de confirmation | `tries = 3`, `timeout = 30`, `backoff = [10, 60]` ; dispatché avec `delay(now()->addMinutes(30))` depuis `generateCode()` ; met à jour `confirmation_code_expires_at = now()` si le code est encore valide ; ignoré si pas de hash ou déjà expiré |
| `app/Jobs/PruneGpsLocationsJob.php` | Purge des positions GPS ancennes | `tries = 3`, `timeout = 30`, `backoff = [10, 60]` ; constante `DAYS = 7` ; supprime les `GpsLocation` où `created_at < now()->subDays(7)` ; non dispatché automatiquement (GPS hors périmètre actuel) |
| `app/Jobs/AnalyzeAiRequestDraftJob.php` | **Préremplissage IA F08** : analyse du message libre par Grok/xAI | `tries = 3`, `timeout = 60`, `backoff = [10, 60]` ; `__construct(AiRequestDraft $draft, int $driverUserId)` ; no-op si statut ≠ `pending` ; lit les **services actifs** du livreur (RG11, double garde avec le service) ; appel `AiRequestAnalyzer` (HTTP xAI, `response_format json_object`) ; résout `service_id` (fallback `LOWER(name)`) ; écrit `done`/`failed` + `error_message` FR contrôlé (corps brut loggé canal `jobs`, CA05) ; dispatché par `AiRequestDraftController::analyze()` avec `->afterCommit()` ; en test : `Http::fake()` / `Queue::fake()` (QUEUE_CONNECTION=sync exécute en ligne !) |

### Requests (validation)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Http/Requests/StoreNotificationRequest.php` | Validation création notification (usage interne) | Champs requis : `type` (max 100), `title` (max 255) ; optionnels : `delivery_request_id` (exists), `body`, `read_at` (date) |
| `app/Http/Requests/UpdateNotificationRequest.php` | Validation mise à jour notification | Tous les champs `sometimes` : `delivery_request_id`, `type`, `title`, `body`, `read_at` |

### Resource (API)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Http/Resources/NotificationResource.php` | Formatage de la réponse API d'une notification | Passthrough (`parent::toArray`) — renvoie les attributs du modèle tel quel |

### Policy (autorisation)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Policies/NotificationPolicy.php` | Contrôle d'accès aux notifications | `view`/`update`/`delete` : réservé au propriétaire (`user_id === user.id`) ; `viewAny`/`create` : ouvert |

### Configuration file d'attente

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `config/queue.php` | Configuration de la queue | Default : `database` (env `QUEUE_CONNECTION`) ; driver `database` : table `jobs`, retry_after 90s, after_commit false (mais les jobs utilisent `->afterCommit()` au dispatch) ; failed jobs : driver `database-uuids` |
| `config/logging.php` (canal `jobs`) | Canal de log dédié aux jobs | Driver `daily`, path `storage/logs/jobs.log`, level via `LOG_JOBS_LEVEL` (default info), rotation 14 jours via `LOG_JOBS_DAYS` ; tous les jobs écrivent via `Log::channel('jobs')->error()` en cas d'échec |

### Infrastructure Docker

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `docker-compose.yml` (service `queue`) | Worker de traitement des jobs | Container `allo_queue`, même build que `app` ; commande : `php artisan queue:work database --tries=3 --sleep=3 --timeout=60` ; dépend de `db` ; réseau `allo_network` |

### Tests (5 fichiers, ~17 tests)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `tests/Feature/DeliveryRequestNotificationJobTest.php` | Tests du job notification création | 3 tests : (1) job dispatché quand un client crée une demande ; (2) pas de dispatch sur payload invalide ; (3) la notification est bien créée en base quand le job s'exécute ; utilisation de `Queue::fake()` et `Sanctum::actingAs()` |
| `tests/Feature/StatusChangedNotificationJobTest.php` | Tests du job notification changement de statut | 4 tests : (1) dispatch quand le livreur change le statut ; (2) notifie le client quand le livreur change ; (3) notifie le livreur quand le client confirme ; (4) rien ne se passe si `changedBy` est null ; vérifie le bon destinataire dans chaque cas |
| `tests/Feature/ChatMessageNotificationJobTest.php` | Tests du job notification chat | 4 tests : (1) dispatch quand le client envoie un message ; (2) notifie le livreur ; (3) notifie le client quand le livreur envoie ; (4) rien ne se passe si l'expéditeur n'est pas un participant ; vérifie le type `chat_message` |
| `tests/Feature/ExpireConfirmationCodeJobTest.php` | Tests du job expiration code | 4 tests : (1) expire un code encore valide ; (2) ne change rien si déjà expiré ; (3) ne change rien si pas de code ; (4) vérifie le dispatch avec delay 30 min |
| `tests/Feature/PruneGpsLocationsJobTest.php` | Tests du job purge GPS | 2 tests : (1) supprime les GPS > 7 jours, garde les récents ; (2) garde les GPS à la frontière exacte (7 jours) |

---

## Actions passées (rapports liés)

- **[rapport_ar30_queues_uploads.md](../rapport/rapport_ar30_queues_uploads.md)** — AR-30 : mise en place complète de l'infrastructure jobs queue (5 jobs, hooks, canal log, worker Docker) + uploads preuves de livraison. Détail de chaque job, tests, et décisions.

---

## Pièges et points d'attention

1. **`afterCommit()` au dispatch** : Tous les jobs notification sont dispatchés avec `->afterCommit()` pour éviter de créer des notifications si la transaction échoue. C'est particulièrement important pour `CreateStatusChangedNotificationJob` qui est dispatché dans `transitionTo()` (une transaction DB).

2. **Queue `database`** : Le driver par défaut est `database` (table `jobs`). Le worker tourne dans le conteneur `allo_queue`. En développement, il faut lancer `docker-compose -f D:\AlloDelevry\docker-compose.yml up -d queue` en parallèle. En test, on utilise `Queue::fake()` pour simuler.

3. **Canal de log `jobs`** : Les erreurs de jobs sont logguées dans `storage/logs/jobs.log` (rotation 14 jours). Utile pour diagnostiquer les échecs. Le canal est défini dans `config/logging.php`.

4. **Mapping FR des statuts** : `CreateStatusChangedNotificationJob` mappe les 9 statuts techniques en libellés français (ex: `colis_recupere` → "colis récupéré"). Attention à maintenir ce mapping si de nouveaux statuts sont ajoutés.

5. **PruneGpsLocationsJob non dispatché** : Ce job est prêt mais n'est pas dispatché automatiquement. Le GPS est hors périmètre actuel. Il pourrait être lancé manuellement ou via un scheduler (non prévu dans ce projet).

6. **ExpireConfirmationCodeJob avec delay** : Le job est dispatché avec `->delay(now()->addMinutes(30))` dans `generateCode()`. Le worker doit tourner en continu pour traiter les jobs retardés. Si le worker est redémarré, les jobs avec delay sont traités dès que `available_at` est atteint.

7. **Comment tester manuellement** :
   - Lancer le worker : `docker-compose -f D:\AlloDelevry\docker-compose.yml up -d queue`
   - Créer une demande → vérifier `storage/logs/jobs.log`
   - `php artisan test --compact --filter=NotificationJob`
   - Vérifier la file : `php artisan queue:work --once`
