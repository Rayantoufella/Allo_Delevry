# Rapport — Retrait du chat IA (analyse conversationnelle « temps réel »)

**Date :** 10/08/2026 — **Branche :** `feature/FrontEnd` — **Statut :** Terminé (retiré)

## Contexte

L'assistant IA conversationnel (le client « discutait » avec l'IA qui jouait le livreur et remplissait le formulaire de demande au fil du chat — « analyse temps réel ») a été **implémenté puis retiré sur demande de l'utilisateur**. Le commit `94eaabb` a été **reverté proprement** : tout le code du chat IA a été supprimé ou restauré dans son état antérieur (`c2a868b`), sans toucher aux autres fonctionnalités.

**Conservé après retrait :**

- Le **flux d'analyse classique** : textarea → `POST /ai-request-drafts/analyze` → job `AnalyzeAiRequestDraftJob` → formulaire pré-rempli (zone-cards, bandeau champs requis, focus). Vue `AiAssistantView.vue` restaurée dans sa version classique.
- Le **chat entre client et livreur** (`ChatPanel.vue` + `POST /chat-messages`) — intact, indépendant de l'IA.
- La **désactivation de Reverb** (décision antérieure conservée) : `BROADCAST_CONNECTION=null`, variables `REVERB_*`/`VITE_REVERB_*` commentées — code intact, réactivation documentée plus bas.

## Fichiers touchés par le retrait (revert du commit `94eaabb`)

| Fichier | Rôle | Points clés |
|---|---|---|
| `app/Services/AiRequestAnalyzer.php` | Restauré depuis `c2a868b` | Retirés : `chatReply()`, `extractFromConversation()`, `chatSystemPrompt()`, `getChatModels()`, `getExtractModels()`, `stripCreatedAt()`, `buildConversationText()`. Conservé : `analyze()` + fallback IA par famille |
| `app/Http/Controllers/Api/AiRequestDraftController.php` | Restauré depuis `c2a868b` | Retirés : `start()` (session chat) et `sendMessage()`. Conservés : `analyze()` + annotations Scribe |
| `app/Http/Resources/AiRequestDraftResource.php` | Restauré depuis `c2a868b` | Retour à `parent::toArray($request)` — plus de champ `chat_history` exposé |
| `app/Models/AiRequestDraft.php` | Restauré | `chat_history` retiré du `$fillable` et des `$casts` |
| `config/services.php` | Restauré | `openrouter.chat_model` / `openrouter.extract_model` supprimés |
| `routes/api.php` | Restauré | Routes `POST /ai-request-drafts/start` et `POST /ai-request-drafts/{draft}/messages` supprimées — `analyze` et la ressource `ai-request-drafts` conservées |
| `app/Jobs/ProcessAiChatMessageJob.php` | **Supprimé** | Job conversationnel (nano → ultra → fusion) |
| `app/Http/Requests/SendAiChatMessageRequest.php` | **Supprimé** | Validation du message de chat |
| `tests/Feature/AiChatMessageTest.php` | **Supprimé** | 9 tests du chat IA retirés |
| `database/migrations/2026_08_09_100000_add_chat_history_to_ai_request_drafts_table.php` | **Supprimé** | Colonne `chat_history` retirée de la base dev (rollback effectué) — migration absente des environnements frais |
| `frontend/src/views/AiAssistantView.vue` | Restauré depuis `c2a868b` | Version classique : textarea, bouton « Analyser avec l'IA », polling 2,5 s, bandeau champs requis, zone-cards, focus — plus d'interface de chat |
| `.env` / `.env.example` | Modifiés | Variables `OPENROUTER_CHAT_MODEL` / `OPENROUTER_EXTRACT_MODEL` retirées ; **désactivation Reverb conservée** (`BROADCAST_CONNECTION=null`, bloc REVERB commenté) |

## Vérifications

- **Pint** : clean.
- **Suite de tests** : `84 passed (286 assertions)` — retour au niveau pré-chat.
- **Build frontend** : vite OK (139 modules, 0 erreur), chunk `AiAssistantView` = version classique confirmée (11 kB gzip 3,84 kB).
- **Grep résidus** : aucune occurrence de `chat_history`, `chatReply`, `extractFromConversation`, `ProcessAiChatMessage`, `SendAiChatMessage`, `OPENROUTER_CHAT_MODEL`/`EXTRACT_MODEL` dans le code backend ou frontend.
- **Migrations** : `migrate:status` propre — plus de trace de la migration `add_chat_history`.

## Rappel : réactivation de Reverb (futur)

1. `.env` : `BROADCAST_CONNECTION=reverb` + décommenter les variables `REVERB_*` et `VITE_REVERB_*`.
2. Démarrer le serveur : `php artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction`.
3. Les événements `ChatMessageReceived` / `DeliveryRequestStatusUpdated` et `routes/channels.php` sont intacts.
