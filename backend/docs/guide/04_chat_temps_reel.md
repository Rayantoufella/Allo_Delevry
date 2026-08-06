# Guide — 04 : Chat temps réel (Reverb)

**Périmètre :** `D:\AlloDelevry\backend`
**Entrées (routes/canaux) :**
- `POST /api/chat-messages` — envoi d'un message (broadcast instantané)
- `GET /api/chat-messages` — historique paginé
- `PATCH /api/chat-messages/{id}` — mise à jour (marquage lecture, etc.)
- `DELETE /api/chat-messages/{id}` — suppression par l'expéditeur
- Canal privé Reverb : `conversation.{deliveryRequestId}` (préfixé `private-` par Laravel)

**Référence cahier des charges :** F12 (chat et temps réel — P1, chat client/livreur + diffusion statuts en direct)

---

## Vue d'ensemble

Le module chat permet au **client** et au **livreur** d'échanger des messages en temps réel autour d'une demande de livraison. Chaque message est persisté en base (`chat_messages`) puis **diffusé instantanément** via un canal WebSocket privé Reverb (`private-conversation.{id}`). En parallèle, un **job asynchrone** crée une notification interne pour le destinataire (cf. guide 05).

En plus du chat, le **changement de statut** d'une demande (via `DeliveryRequest::transitionTo()`) est **aussi diffusé** sur le même canal privé, permettant au frontend de mettre à jour la timeline en direct sans polling.

**Flux résumé :**
1. Le client ou le livreur envoie un message (`POST /api/chat-messages`)
2. Le controller vérifie la politique (`ChatMessagePolicy`) → crée le `ChatMessage`
3. Un job (`CreateChatMessageNotificationJob`) est dispatché (`afterCommit`) pour créer une notification en base
4. L'événement `ChatMessageReceived` est broadcast sur `private-conversation.{deliveryRequestId}`
5. Le frontend de l'autre participant reçoit le message en temps réel via Reverb

---

## Fichiers et rôles (exhaustif)

### Contrôleurs et endpoints

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Http/Controllers/Api/ChatMessageController.php` | CRUD complet du chat (index, store, show, update, destroy) | `store()` dispatch `CreateChatMessageNotificationJob` après commit + `broadcast(new ChatMessageReceived($message))` ; filtre les messages par `delivery_request_id` ou par participation utilisateur ; pagination 20 |

### Modèle et base de données

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Models/ChatMessage.php` | Modèle Eloquent — table `chat_messages` | Relations : `belongsTo(DeliveryRequest)`, `belongsTo(User, 'sender_id')` ; cast `is_read` → boolean ; fillable : delivery_request_id, sender_id, message_type, content, is_read |
| `database/migrations/2026_07_22_141318_create_chat_messages_table.php` | Migration de la table `chat_messages` | Colonnes : id, delivery_request_id (FK, cascadeDelete), sender_id (FK, cascadeDelete), message_type (default 'text'), content (text), is_read (default false), timestamps |
| `app/Models/DeliveryRequest.php` (section chat) | Relations vers chatMessages + broadcast dans `transitionTo()` | `hasMany(ChatMessage::class)` ; dans `transitionTo()` : `broadcast(new DeliveryRequestStatusUpdated(...))` diffuse le statut sur le canal privé — voir guide 02 pour le détail complet de la machine à états |

### Événements temps réel (ShouldBroadcast)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Events/ChatMessageReceived.php` | Événement broadcast : nouveau message de chat | Implémente `ShouldBroadcast` ; canal : `PrivateChannel('conversation.'.$message->delivery_request_id)` ; payload : id, delivery_request_id, sender (id + name), message_type, content, created_at |
| `app/Events/DeliveryRequestStatusUpdated.php` | Événement broadcast : changement de statut | Implémente `ShouldBroadcast` ; canal : `PrivateChannel('conversation.'.$deliveryRequest->id)` ; payload : delivery_request_id, tracking_number, status, comment, changed_by, updated_at ; déclenché par `DeliveryRequest::transitionTo()` |

### Routes broadcast (canaux privés)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `routes/channels.php` | Déclaration du canal privé `conversation.{deliveryRequestId}` | Auth : vérifie que l'utilisateur est `client_id` OU `driver_id` de la `DeliveryRequest` ; garde : `sanctum` ; le frontend doit se connecter à `private-conversation.{id}` (préfixe `private-` ajouté automatiquement par Laravel) |

### Job asynchrone

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Jobs/CreateChatMessageNotificationJob.php` | Crée une notification interne (en base) pour le destinataire du message | `tries = 3`, `timeout = 30`, `backoff = [10, 60]` ; identifie le destinataire (l'autre partie client/livreur) ; corps de la notification tronqué à 120 caractères (`Str::limit`) ; canal log `jobs` en cas d'échec |

### Requests (validation)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Http/Requests/StoreChatMessageRequest.php` | Validation de la création d'un message | Champs requis : `delivery_request_id` (exists), `content` (string) ; optionnels : `message_type` (max 50), `is_read` (boolean) |
| `app/Http/Requests/UpdateChatMessageRequest.php` | Validation de la mise à jour d'un message | Champs optionnels (tous `sometimes`) : `message_type` (max 50), `content` (string), `is_read` (boolean) |

### Resource (API)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Http/Resources/ChatMessageResource.php` | Formatage de la réponse API d'un message | Passthrough (`parent::toArray`) — renvoie les attributs du modèle tel quel |

### Policy (autorisation)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Policies/ChatMessagePolicy.php` | Contrôle d'accès au chat | `view`/`create` : vérifie `isParticipant()` (client_id OU driver_id de la DeliveryRequest) ; `update`/`delete` : réservé à l'expéditeur (`sender_id === user_id`) ; `viewAny` : ouvert à tous les utilisateurs authentifiés |

### Infrastructure Reverb

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `docker-compose.yml` (service `reverb`) | Conteneur Docker dédié au serveur WebSocket Reverb | Image : même build que `app` (docker/php) ; commande : `php artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction` ; port exposé : `8080:8080` ; dépend de `db` et `app` ; réseau `allo_network` |
| `config/reverb.php` | Configuration du serveur Reverb | Serveur : host `0.0.0.0`, port 8080 (env `REVERB_SERVER_PORT`) ; app : key/secret/id via `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_APP_ID` ; ping 60s, activity timeout 30s ; scaling Redis optionnel |
| `config/broadcasting.php` | Configuration du driver de broadcast | Default via `BROADCAST_CONNECTION` (doit être `reverb`) ; connexion reverb : key/secret/id, host/port/scheme via variables `REVERB_*` |

### Variables d'environnement (noms uniquens)

| Fichier | Noms de variables | Rôle |
|---------|-------------------|------|
| `.env` | `BROADCAST_CONNECTION`, `QUEUE_CONNECTION` | Détermine le driver de broadcast (`reverb`) et de queue (`database`) |
| `.env` | `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | Identifiants de l'application Reverb |
| `.env` | `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | Adresse du serveur Reverb (localhost:8080, http) |
| `.env` | `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` | Variables exposées au frontend pour la connexion WebSocket |

### Tests

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `tests/Feature/ReverbBroadcastTest.php` | Tests de diffusion temps réel | 2 tests Pest : (1) un message est diffusé sur `private-conversation.{id}` avec Event::fake + vérification `broadcastOn()->name` ; (2) un changement de statut est diffusé sur le même canal ; utilise des factories client/driver/DeliveryRequest ; les canaux privés portent le préfixe `private-` ajouté par le framework |

---

## Actions passées (rapports liés)

- **[rapport_ar04_reverb_config.md](../rapport/rapport_ar04_reverb_config.md)** — Configuration initiale de Reverb (AR-04) puis câblage de la diffusion F12 (août 2026) : installation, config, canal privé, événements, hook diffusion, tests.
- Voir aussi le **guide 02** ([02_demandes_livraison.md](02_demandes_livraison.md)) pour le détail complet de `DeliveryRequest::transitionTo()` qui déclenche la diffusion du statut.

---

## Pièges et points d'attention

1. **Préfixe `private-`** : Laravel ajoute automatiquement le préfixe `private-` aux canaux privés. Le frontend doit se connecter à `private-conversation.{id}`, mais le code côté serveur déclare `conversation.{id}`. Les tests vérifient que `broadcastOn()->name === 'private-conversation.{id}'`.

2. **Auth Sanctum obligatoire** : La déclaration dans `routes/channels.php` utilise `['guards' => ['sanctum']]`. Le frontend doit envoyer un token Sanctum valide pour s'abonner au canal.

3. **Pas de BroadcastServiceProvider** : En development/testing, `BROADCAST_CONNECTION` peut être `null` ou `log`. Les tests utilisent `Event::fake()` pour vérifier le dispatch sans émettre réellement via Reverb.

4. **Job afterCommit** : Le `CreateChatMessageNotificationJob` est dispatché avec `afterCommit()` pour garantir que la notification n'est créée que si la transaction DB du message est validée.

5. **Diffusion des statuts** : `DeliveryRequestStatusUpdated` est déclenché dans `transitionTo()` (guide 02), pas dans le controller directement. Toute transition de statut passe par cette méthode et déclenche la diffusion.

6. **Service Docker** : Le service `reverb` dans `docker-compose.yml` tourne sur le port 8080 et doit être démarré (`docker-compose -f D:\AlloDelevry\docker-compose.yml up -d reverb`) en parallèle du worker queue. En test, Reverb n'est pas nécessaire (Event::fake).

7. **Comment tester manuellement** :
   - Vérifier que Reverb tourne : `docker exec allo_backend php artisan reverb:start`
   - Envoyer un message via l'API et observer la diffusion dans les logs Reverb
   - En test : `php artisan test --compact --filter=ReverbBroadcastTest`
