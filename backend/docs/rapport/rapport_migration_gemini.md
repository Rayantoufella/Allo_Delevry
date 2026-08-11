# Rapport — Migration IA : OpenRouter → Google AI Studio (Gemini)

**Date :** 11 août 2026
**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** F08 / ChatIA (fournisseur IA)
**Statut :** Terminé
**Branche :** — (non commité)

## 1. Contexte

La clé API OpenRouter (`sk-or-v1-…`) était devenue invalide (HTTP 401). L'utilisateur a fourni une nouvelle clé Google AI Studio au **nouveau format `AQ.Ab8RN…`**. Ce format :
- fonctionne sur l'API **native Gemini** (`generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`) via `?key=` ou le header `x-goog-api-key` ;
- est **rejeté** par l'endpoint OpenAI-compatible de Google (`/v1beta/openai/`) et par OpenRouter.

Il a donc fallu migrer le code d'appel IA, pas seulement remplacer la clé.

## 2. Tableau récapitulatif des actions

| # | Action | Résultat |
|---|--------|----------|
| 1 | Vérifier la clé sur l'API native | Clé valide (HTTP 200). Modèles `gemini-2.5-flash`/`gemini-3-flash`/`gemini-2.0-flash` inutilisables (retirés ou quota 0) → **`gemini-3.6-flash`** principal, **`gemini-3-flash-preview`** fallback |
| 2 | `config/services.php` | Bloc `openrouter` remplacé par bloc `gemini` (`GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_FALLBACK_MODELS`, `GEMINI_CHAT_MODEL`, `GEMINI_EXTRACT_MODEL`) |
| 3 | `app/Services/AiRequestAnalyzer.php` | Payload natif Gemini (`contents` + `systemInstruction` + `generationConfig`), réponse `candidates.0.content.parts.0.text`, helper `toGeminiContents()` (`assistant` → `model`), messages d'erreur FR « Google AI » |
| 4 | `.env` / `.env.example` | Variables `OpenRouter` supprimées, `GEMINI_*` ajoutées (clé réelle dans `.env` gitignoré) |
| 5 | `tests/Feature/AiChatMessageTest.php` | Fakes HTTP redirigés vers `generativelanguage.googleapis.com/*`, réponses au format candidats/parts, assertions modèles via URL `:generateContent` |
| 6 | Validation | Test réel bout-en-bout `analyze()` réussi avec la clé : Sara, 0612345678, snak 2000, houda quartier, 420 DH, service reconnu |

## 3. Table des fichiers

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `config/services.php` | Bloc `gemini` (base_url, api_key, model, fallback_models, chat_model, extract_model) | `base_url` défaut `https://generativelanguage.googleapis.com/v1beta` ; modèles par défaut `gemini-3.6-flash` (chat/extract), fallback `gemini-3-flash-preview` |
| `app/Services/AiRequestAnalyzer.php` | Appels natifs Gemini (3 usages : extraction directe, chat, extraction conversationnelle) | `sendRequest()` : POST `{base}/models/{model}:generateContent`, clé en header `x-goog-api-key` ; `responseMimeType: application/json` + `maxOutputTokens` généreux (1024 chat / 2048 extract) car les modèles Gemini 3 « pensent » d'abord (`thoughtsTokenCount`) — un budget trop court vide le `content` (`finishReason: MAX_TOKENS`) et déclenche le fallback |
| `app/Jobs/ProcessAiChatMessageJob.php` | Commente simplement la double étape Google AI | `timeout = 180` conservé |
| `.env` | Secrets (gitignoré) | `GEMINI_API_KEY=AQ.Ab8…`, modèles `GEMINI_*` ; variables `OPENROUTER_*` supprimées |
| `.env.example` | Documentation des variables | Mêmes variables, clé vide |
| `tests/Feature/AiChatMessageTest.php` | 15 tests du flux chat IA | Helpers `chatTextResponse()`/`chatJsonResponse()` au format `candidates[0].content.parts[0].text` ; `Http::fakeSequence('generativelanguage.googleapis.com/*')` ; assertions `str_contains($request->url(), $model)` (le modèle est dans l'URL, plus dans le body) |

## 4. Détail technique

**Contrat d'appel Gemini** (remplace Chat Completions OpenAI) :
```php
POST {base_url}/models/{model}:generateContent
Headers : x-goog-api-key: {clé}
Body : {
  "contents": [{"role": "user"|"model", "parts": [{"text": "…"}]}],
  "systemInstruction": {"parts": [{"text": "…"}]},
  "generationConfig": {"temperature": 0.1, "maxOutputTokens": 2048, "responseMimeType": "application/json"}
}
```
**Réponse** : `candidates[0].content.parts[0].text` (peut être vide si `MAX_TOKENS` atteint pendant la phase de « pensée » → boucle de fallback existante gère ce cas).

**Piège découvert** : `thinkingConfig` n'existe pas sur `gemini-3.6-flash` via `generateContent` v1beta (400 `Unknown name "thinkingConfig"`) ; on s'appuie donc sur un budget de sortie large plutôt que sur la désactivation de la réflexion. Le normalize (`services.$decoded['product_amount']`/`delivery_zone`) tolère les chaînes vides renvoyées par le modèle (« `""` » → `null`), comportement inchangé côté job.

La logique métier (9 clés, RG11, zones, prompts) est **inchangée** — seule la couche transport a été remplacée.

## 5. Vérifications

- Pint : `vendor/bin/pint --dirty` → **passé**
- Tests : `php artisan test --compact` → **92 passed (356 assertions)** ; filtre IA → 15 passed (59 assertions)
- Manuel : appel réel `AiRequestAnalyzer->analyze()` (script temporaire supprimé) → extraction correcte (Sara / 0612345678 / snak 2000 / houda quartier / 420 DH / service « Envoi de colis »)

## 6. Références / suite logique

- Rapports liés : `rapport_f08_prefill_ia.md` (création de l'intégration IA), `rapport_chat_ia_assistant.md` (chat IA), `rapport_chat_ia_zone_livraison.md` (zones)
- Suite : mise à jour éventuelle du guide `docs/guide/09_bonus_ecarts.md` (section IA) ; surveiller le quota free tier (`429 RESOURCE_EXHAUSTED` possible sur les modèles retirés)