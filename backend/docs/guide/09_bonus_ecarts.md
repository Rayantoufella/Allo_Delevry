# Guide — 09 : Bonus, GPS et écarts de périmètre

**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** cahier des charges — F02/F13 (notification), GPS (bonus), e-mail (bonus), IA (Grok direct)

## Vue d'ensemble

Ce guide regroupe les **éléments hors périmètre principal** ou en attente, pour comprendre ce qui existe déjà, ce qui est un bonus, et ce qui est explicitement différé (décisions utilisateur). **Règle : GPS et e-mail = BONUS** (l'utilisateur les implémentera lui-même à la fin du projet) ; l'IA du chat est traitée par **appel direct Grok** (pas de job IA, paquet `laravel/ai` non installé).

## Fichiers et rôles (exhaustif) — ressources déjà présentes

### GPS (bonus — l'utilisateur finira le flux)

| Fichier | Rôle | Points clés |
|---------|------|-------------|
| `app/Models/GpsLocation.php` | Modèle — table `gps_locations` | `belongsTo(DeliveryRequest)` ; champs : delivery_request_id, latitude, longitude, recorded_at |
| `app/Http/Controllers/Api/GpsLocationController.php` | CRUD des positions | `store()` : `authorize('create', [GpsLocation::class, $deliveryRequest])` → **livreur uniquement** (policy) ; `recorded_at` défini à `now()` si absent |
| `app/Http/Requests/StoreGpsLocationRequest.php` / `UpdateGpsLocationRequest.php` | Validation | latitude/longitude numériques + `delivery_request_id` exists (store) ; correction B8 : champs non modifiables dans l'update |
| `app/Http/Resources/GpsLocationResource.php` | Formatage | Passthrough des attributs |
| `app/Policies/GpsLocationPolicy.php` | Autorisation | **create : driver uniquement** (correction B8) ; view/update/delete : participants |
| `app/Jobs/PruneGpsLocationsJob.php` | Nettoyage | Supprime les positions **> 7 jours** ; sans constructeur (exécutable manuellement) — **non dispatché** (pas de flux GPS actif) |
| `database/migrations/2026_07_22_150000_create_gps_locations_table.php` | Table | FK delivery_request_id, latitude/longitude decimals, recorded_at, timestamps |

### Paiements (P2 — hors périmètre sauf demande)

| Fichier | Rôle | Points clés |
|---------|------|-------------|
| `app/Models/PaymentTransaction.php` | Modèle — table `payment_transactions` | `belongsTo(DeliveryRequest)` |
| `app/Http/Controllers/Api/PaymentTransactionController.php` | CRUD | CRUD standard ; `PaymentTransactionPolicy` : participants |
| `app/Http/Requests/StorePaymentTransactionRequest.php` / `UpdatePaymentTransactionRequest.php` | Validation | Montant numérique, statut CRUD standard (aucune gateway branchée) |
| `app/Http/Resources/PaymentTransactionResource.php` | Sérialisation | Passthrough |
| `database/migrations/2026_07_22_150001_create_payment_transactions_table.php` | Table | montant, statut, référence |

### IA / brouillons (décision : Grok en direct, PAS de job IA)

| Fichier | Rôle | Points clés |
|---------|------|-------------|
| `app/Models/AiRequestDraft.php` | Modèle — table `ai_request_drafts` | Brouillon de demande préparé par l'IA |
| `app/Http/Controllers/Api/AiRequestDraftController.php` | CRUD | CRUD standard + `AiRequestDraftPolicy` (propriétaire) |
| `database/migrations/2026_07_22_141316_create_ai_request_drafts_table.php` | Table | contenu JSON/markdown + delivery_request_id nullable |
| — | Paquet `laravel/ai` | **NON installé** (composer require interrompu — composer.json intact) ; Grok = appel HTTP direct côté frontend/backend à décider |

## Écarts de périmètre (décisions utilisateur)

| Sujet | Statut | Détail |
|-------|--------|--------|
| **GPS en direct** | Bonus (différé) | Les modèles/contrôleur/policy existent ; le flux temps réel est laissé à l'utilisateur |
| **E-mail** (F13, AR-31 récupération de mot de passe) | Différé | Dépend du service e-mail ; AR-31 marqué Différé dans Jira ; `CreateStatusChangedNotificationJob` remplace la notification e-mail par une notification interne |
| **Paiement Sandbox** (P2) | Hors périmètre | Pas de gateway intégrée ; simple CRUD `payment-transactions` |
| **IA du chat** | Grok en direct | Pas de job IA ; décision utilisateur (appel direct) |
| **Scheduler / cron** | Absent | Pas de scheduler : expiration du code via `ExpireConfirmationCodeJob` avec `delay(30 min)`, purge GPS manuelle |
| **Ticket PDF / Blade** | Annulé | Frontend Vue séparé → aucun template Blade ; dompdf reste installé (aucun coût) |

## Actions passées (rapports liés)

- **AR-31** (récupération mot de passe) — différé (dépend de l'e-mail) : voir `docs/rapport/README.md`.
- **AR-30** — `docs/rapport/rapport_ar30_queues_uploads.md` (PruneGpsLocationsJob).

## Pièges et points d'attention

- **`PruneGpsLocationsJob` n'est pas dispatché automatiquement** : sans scheduler, il faut l'exécuter manuellement (`php artisan` tinker → `PruneGpsLocationsJob::dispatchSync()`) ou le brancher dans un futur flux GPS.
- **Le GPS est déjà verrouillé côté sécurité** (B8) : un client ne peut pas écrire de position — le flux livreur → serveur est prêt à être câblé.
- **E-mail absent** : aucune classe Mailable/notifications mail — toute notification passe par la table `notifications` (guide 05).