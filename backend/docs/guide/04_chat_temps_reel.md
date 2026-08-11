# Guide — 04 : Chat client/livreur (polling)

**Périmètre :** `D:\AlloDelevry\backend`
**Entrées (routes) :**
- `POST /api/chat-messages` — envoi d'un message (notification interne au destinataire)
- `GET /api/chat-messages` — historique paginé (rafraîchi par polling côté frontend)
- `PATCH /api/chat-messages/{id}` — mise à jour (marquage lecture, etc.)
- `DELETE /api/chat-messages/{id}` — suppression par l'expéditeur

**Référence cahier des charges :** F12 (chat client/livreur — diffusion temps réel Reverb **retirée**, remplacée par du polling)

---

## Vue d'ensemble

Le module chat permet au **client** et au **livreur** d'échanger des messages autour d'une demande de livraison. Chaque message est persisté en base (`chat_messages`) ; un **job asynchrone** crée une notification interne pour le destinataire (cf. guide 05). Le frontend rafraîchit l'historique par **polling** (`usePolling`).

> **Historique Reverb (retiré le 11/08/2026)** : la diffusion WebSocket temps réel (`laravel/reverb`, événements `ChatMessageReceived` / `DeliveryRequestStatusUpdated`, canal privé `conversation.{id}`, `routes/channels.php`) a été **entièrement supprimée** — non utilisée (le frontend consomme l'API par polling). Détails dans `rapport_suppression_reverb.md`.

**Flux résumé :**
1. Le client ou le livreur envoie un message (`POST /api/chat-messages`)
2. Le controller vérifie la politique (`ChatMessagePolicy`) → crée le `ChatMessage`
3. Un job (`CreateChatMessageNotificationJob`) est dispatché (`afterCommit`) pour créer une notification en base
4. Le destinataire récupère le message au prochain polling (`GET /api/chat-messages`)

---

## Fichiers et rôles (exhaustif)

### Contrôleurs et endpoints

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Http/Controllers/Api/ChatMessageController.php` | CRUD complet du chat (index, store, show, update, destroy) | `store()` dispatch `CreateChatMessageNotificationJob` après commit ; filtre les messages par `delivery_request_id` ou par participation utilisateur ; pagination 20 |

### Modèle et base de données

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Models/ChatMessage.php` | Modèle Eloquent — table `chat_messages` | Relations : `belongsTo(DeliveryRequest)`, `belongsTo(User, 'sender_id')` ; cast `is_read` → boolean ; fillable : delivery_request_id, sender_id, message_type, content, is_read |
| `database/migrations/2026_07_22_141318_create_chat_messages_table.php` | Migration de la table `chat_messages` | Colonnes : id, delivery_request_id (FK, cascadeDelete), sender_id (FK, cascadeDelete), message_type (default 'text'), content (text), is_read (default false), timestamps |

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

### Frontend (polling)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `frontend/src/composables/usePolling.js` | Rafraîchissement périodique des données | Délai défaut 4000 ms ; utilisé par le chat (`ChatPanel.vue`), le suivi (`TrackingView.vue`), le dashboard, les notifications |

### Variables d'environnement (noms uniquement)

| Fichier | Noms de variables | Rôle |
|---------|-------------------|------|
| `.env` | `QUEUE_CONNECTION` | Driver de queue (`database`) — indispensable pour la notification asynchrone |
| `.env` | `BROADCAST_CONNECTION` | Conservé à `null` (broadcast framework par défaut, aucun driver actif) |

### Tests

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `tests/Feature/ChatMessageNotificationJobTest.php` | Test du job de notification | Vérifie la création de la notification interne au destinataire |

---

## Actions passées (rapports liés)

- **[rapport_ar04_reverb_config.md](../rapport/rapport_ar04_reverb_config.md)** — Configuration initiale de Reverb (AR-04) puis câblage de la diffusion F12 (août 2026) : **historique, fonctionnalité retirée**.
- **[rapport_suppression_reverb.md](../rapport/rapport_suppression_reverb.md)** — Retrait complet de Reverb/websocket (11/08/2026) : paquets, événements, canaux, env, docs.
- Voir aussi le **guide 02** ([02_demandes_livraison.md](02_demandes_livraison.md)) pour le détail complet de `DeliveryRequest::transitionTo()` (statuts + notification).

---

## Pièges et points d'attention

1. **Pas de temps réel** : le chat et les statuts sont rafraîchis par polling côté frontend. Aucun WebSocket, aucun canal privé, aucun `Event::listen` broadcast.

2. **Job afterCommit** : le `CreateChatMessageNotificationJob` est dispatché avec `afterCommit()` pour garantir que la notification n'est créée que si la transaction DB du message est validée.

3. **Broadcast framework conservé** : `config/broadcasting.php` reste présent (fichier standard Laravel, défaut `null`). Le paquet `laravel/reverb` et les variables `REVERB_*` n'existent plus ; ne pas les réintroduire sans re-tester.

4. **Comment tester manuellement** :
   - Envoyer un message via l'API (`POST /api/chat-messages`) puis vérifier la notification (`GET /api/notifications`).
   - En test : `php artisan test --compact --filter=ChatMessage`
