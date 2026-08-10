# Rapport — Chat IA (assistant conversationnel) + désactivation Reverb

**Date :** 10/08/2026 — **Branche :** `feature/FrontEnd`

## Contexte

L'utilisateur veut transformer l'assistant IA (textarea → formulaire pré-rempli) en un **chat conversationnel** : le client discute avec l'IA qui **joue le livreur**, répond en direct et **remplit le formulaire de demande au fil de la conversation**. Décisions validées :
- **2 modèles OpenRouter** : `nano` (rapide) pour les réponses de conversation, `ultra` (puissant) pour l'extraction du formulaire.
- Le chat fonctionne en **polling** (pas de WebSocket) → **Reverb désactivé** (env), code conservé pour réactivation future.
- Après création de la demande, le chat reste ouvert : l'IA confirme, le client peut continuer à discuter ou repartir sur une nouvelle demande.

## Contrat API (fixé par le lead, respecté)

| Endpoint | Rôle |
|---|---|
| `POST /api/ai-request-drafts/start` | Crée la session de chat : draft `pending`, `chat_history: []`, `input_message: ''` → 201 |
| `POST /api/ai-request-drafts/{id}/messages` | Body `{content, driver_slug}` → append message `user`, dispatch `ProcessAiChatMessageJob` → 200 (throttle:10,1) |
| `GET /api/ai-request-drafts/{id}` | Polling : `chat_history` (role/content/created_at), `status`, `generated_data` |

## Fichiers touchés

| Fichier | Rôle | Points clés |
|---|---|---|
| `database/migrations/2026_08_09_100000_add_chat_history_to_ai_request_drafts_table.php` | **Créé** — colonne `chat_history` JSON nullable sur `ai_request_drafts` | MySQL : pas de défaut `[]` sur JSON → nullable + cast côté PHP |
| `app/Models/AiRequestDraft.php` | Modifié | `chat_history` dans `$fillable` + cast `'array'` |
| `config/services.php` | Modifié | `openrouter.chat_model` (nano, `OPENROUTER_CHAT_MODEL`) + `openrouter.extract_model` (ultra, `OPENROUTER_EXTRACT_MODEL`) |
| `app/Services/AiRequestAnalyzer.php` | Modifié | Refactor HTTP (`sendRequest()` partagé) + `chatReply()` (nano, persona livreur, température 0.7, pas de JSON) + `extractFromConversation()` (ultra, JSON, RG11 via `normalizeResult`) + `chatSystemPrompt()` + boucles fallback par famille de modèles |
| `app/Jobs/ProcessAiChatMessageJob.php` | **Créé** | tries=3, timeout=60, backoff [10,60] : chatReply (nano) → append assistant → extractFromConversation (ultra) → **fusion partielle** (les non-null écrasent, les null ne touchent pas l'existant) → service_id via matchServiceId → done ; `failed()` → status failed + error_message (canal jobs) |
| `app/Http/Requests/SendAiChatMessageRequest.php` | **Créé** | `content` required string max:2000 + `driver_slug` required exists:driver_profiles,slug |
| `app/Http/Controllers/Api/AiRequestDraftController.php` | Modifié | +`start()` (201, draft vide) +`sendMessage()` (policy update, append user, dispatch job) — annotations Scribe FR |
| `app/Http/Resources/AiRequestDraftResource.php` | Modifié | `toArray` explicite : `chat_history` formaté (role/content/created_at) + champs du draft |
| `routes/api.php` | Modifié | `POST /ai-request-drafts/start` + `POST /ai-request-drafts/{draft}/messages` (throttle) |
| `tests/Feature/AiChatMessageTest.php` | **Créé** | 9 tests : start 201, envoi 200 + chat_history + assertPushed, 422 contenu vide, 403 autre user, job success (nano texte + ultra JSON distincts par model dans Http::fake), fusion partielle, échec → failed, RG11 service inconnu → null |
| `frontend/src/views/AiAssistantView.vue` | Modifié | Réécriture en **chat** : session au montage, bulles (user droite verte / IA gauche + avatar sparkle), message d'accueil avec brand_name, bulle optimiste, « L'IA écrit… » (3 points animés), polling 2,5 s (timeout 45 s), auto-scroll, formulaire réutilisé intégralement (bandeau champs manquants, zone-cards tarif fixe, focus auto), après création : bulle confirmation IA + carte « Voir le suivi » / « Nouvelle demande », chat toujours ouvert, erreurs en bulles système + réessayer |
| `.env` / `.env.example` | Modifié | `BROADCAST_CONNECTION=null` + variables `REVERB_*`/`VITE_REVERB_*` **commentées** (réactivation = décommenter + `reverb:start`) ; `OPENROUTER_CHAT_MODEL`/`OPENROUTER_EXTRACT_MODEL` ajoutés |

## Vérifications

- Pint `--dirty` : **0 fichier** (passed)
- Suite complète : **93 passed (324 assertions)** — dont `AiChatMessageTest` 9 passed (38), `AiRequestDraftAnalysis` 9, `AiRequestDraftFallback` 5 (flux analyze legacy intact)
- `npm run build` : **139 modules, 0 erreur**
- Migration appliquée en base (`migrate:status` → Ran)

## QA navigateur (live, vraie clé OpenRouter)

1. Page `/drivers/rayan-express/ai` → message d'accueil « Salam ! Je suis l'assistant de Rayan Express… » ✅
2. Envoi message riche → bulle user immédiate → réponse IA nano (« Quelle est l'adresse exacte dans Al Houda (rue, numéro) ? ») ✅
3. Formulaire pré-rempli par ultra : Sara, 06 12 34 56 78, Avenue Hassan II, chaussures taille 39, 420 DH, service Envoi de colis (service_id 1) ✅
4. Bandeau « Complète ces champs : Adresse de livraison, Zone de livraison » + focus sur le premier manquant ✅
5. Complétion manuelle + zone Al Houda (20 DH) → « Envoyer la demande » → création **DLV-UKC99HOBJ2** (delivery_zone_id=2, amount 420, status en_attente) ✅
6. Chat reste ouvert : bulle IA « Votre demande N°DLV-UKC99HOBJ2 est créée ! » + boutons « Voir le suivi » (→ détail demande) / « Nouvelle demande » ✅
7. Données de test nettoyées (draft 29, demande 47).

## Pièges

- **OPcache worker** : après modif de `AiRequestAnalyzer`, le worker `backend-queue-1` servait l'ancien code (`Call to undefined method chatReply()`) → **redémarrer le conteneur queue** après déploiement de code backend.
- PowerShell casse les guillemets dans `tinker --execute` → utiliser un fichier PHP temporaire via `docker cp`.
- `input_message` reste NOT NULL en base → `start()` envoie `''` (le chat_history est la vraie source).
- `generated_data` ne contient pas de clé `service_id` → le frontend lit `service_id` au top-level du draft (corrigé dans la vue).

## Réactivation Reverb (futur)

1. `.env` : `BROADCAST_CONNECTION=reverb` + décommenter `REVERB_*`/`VITE_REVERB_*`.
2. Démarrer le serveur : `php artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction` (ou service docker).
3. Les événements `ChatMessageReceived` / `DeliveryRequestStatusUpdated` et `routes/channels.php` sont intacts.
