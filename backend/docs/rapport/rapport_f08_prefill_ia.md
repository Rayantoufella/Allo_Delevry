# Rapport — F08 : Préremplissage IA des demandes (OpenRouter / Nemotron 3 Super)

**Date :** 7 août 2026
**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** Cahier des charges — F08 (préremplissage IA), §9.2 (workflow IA), RG11 (services actifs)
**Statut :** Terminé
**Branche :** `feature/AI-Laravel` — commit(s) : `906deb1` (v1 xAI/Grok) + `4f77285` (bascule OpenRouter)

## 1. Contexte

F08 : le client saisit un **message libre** (« Livrer demain à Sara à Al Houda, 420 DH à encaisser… ») ; l'IA le transforme en **données structurées** qui préremplissent le formulaire de demande de livraison, toujours **sous validation humaine** (le brouillon reste un simple draft). Décisions utilisateur : **seulement le préremplissage** (pas de réponses auto du chat), endpoint protégé `auth:sanctum`, `driver_slug` obligatoire (le préremplissage est limité aux **services actifs du livreur ciblé** — RG11), fournisseur **OpenRouter** (modèle gratuit NVIDIA **Nemotron 3 Super** `nvidia/nemotron-3-super-120b-a12b:free`), paquet `laravel/ai` **non utilisé** (appel HTTP direct via Laravel `Http`).

**Historique :** la v1 (commit `906deb1`) utilisait xAI/Grok (`grok-4.5`) ; les 2 clés xAI fournies ayant été rejetées (`Incorrect API key provided`), bascule vers **OpenRouter** (clé `sk-or-v1-…`) avec le modèle gratuit Nemotron — JSON mode supporté, démo live **réussie**.

## 2. Tableau récapitulatif des actions

| # | Action | Résultat (commit, fichiers, décision) |
|---|--------|--------------------------------------|
| 1 | Configurer le fournisseur | `config/services.php` (bloc `openrouter`), `.env` (`OPENROUTER_API_KEY`, `OPENROUTER_MODEL=nvidia/nemotron-3-super-120b-a12b:free`), `.env.example` (variables vides) |
| 2 | Créer le service d'analyse IA | `app/Services/AiRequestAnalyzer.php` + `app/Exceptions/AiAnalysisException.php` — contrat 9 clés, messages FR |
| 3 | Créer le job asynchrone | `app/Jobs/AnalyzeAiRequestDraftJob.php` — appel réel, RG11, statuts `done`/`failed` |
| 4 | Exposer l'endpoint | `AiRequestDraftController::analyze()` + `AnalyzeAiRequestDraftRequest` + route `POST /api/ai-request-drafts/analyze` (`throttle:10,1`) |
| 5 | Tests | `tests/Feature/AiRequestDraftAnalysisTest.php` — 9 tests (succès, RG11, erreurs IA, 422, rate limit), URLs `openrouter.ai/*`, helper `aiDraftAiResponse()` |
| 6 | Vérifications | Pint passé ; suite complète **55 passed (185 assertions)** |
| 7 | Démo live | Appel réel **réussi** vers `https://openrouter.ai/api/v1/chat/completions` (Nemotron 3 Super free) : message libre → données structurées correctes (Sara, 420 DH, service `Envoi urgent`) en ~2,25 s. **Piège** : clé mal recopiée → HTTP 401 `Unauthorized` — vérifier `.env` |
| 8 | Documentation | Ce rapport + index `README.md` + guides 02/05/09 mis à jour |

## 3. Rôle des fichiers (obligatoire)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `config/services.php` | Configuration du fournisseur IA | Bloc `openrouter` : `base_url` (env `OPENROUTER_BASE_URL`, défaut `https://openrouter.ai/api/v1`), `api_key` (env `OPENROUTER_API_KEY`), `model` (env `OPENROUTER_MODEL`, défaut `nvidia/nemotron-3-super-120b-a12b:free`) |
| `.env` | Secrets (gitignore) | `OPENROUTER_API_KEY` + `OPENROUTER_MODEL=…` ; **jamais commité** |
| `.env.example` | Modèle de configuration | Variables OpenRouter vides (aucun secret) |
| `app/Services/AiRequestAnalyzer.php` | **Service d'analyse IA** | `analyze(string $freeText, array $activeServiceNames): array` ; POST `/chat/completions` avec `response_format: json_object` ; headers OpenRouter `HTTP-Referer` + `X-Title` ; contrat 9 clés : `recipient_name`, `recipient_phone`, `pickup_address`, `delivery_address`, `package_description`, `product_amount` (float\|null), `amount_to_collect` (float\|null), `scheduled_at`, `service` ; **RG11** : `service` filtré par noms actifs (insensible casse) ; normalise les montants en float ; **zéro accès DB** ; erreurs levées en `AiAnalysisException` |
| `app/Exceptions/AiAnalysisException.php` | Exception métier IA | `extends RuntimeException` ; messages FR : « Clé API OpenRouter non configurée (OPENROUTER_API_KEY). », « Erreur API OpenRouter (HTTP {status}). » (détail du corps loggé au canal `jobs`), « Réponse IA vide ou format inattendu. », « JSON invalide retourné par l'IA. » |
| `app/Jobs/AnalyzeAiRequestDraftJob.php` | **Job d'analyse asynchrone** | `__construct(AiRequestDraft $draft, int $driverUserId)` ; `tries=3`, `timeout=60`, `backoff=[10,60]` ; no-op si statut ≠ `pending` ; relit les services actifs en base (double garde RG11) ; résout `service_id` (fallback `LOWER(name)`) ; écrit `done`/`failed` + `error_message` contrôlé (CA05) ; erreurs loggées canal `jobs` |
| `app/Http/Requests/AnalyzeAiRequestDraftRequest.php` | Validation de l'endpoint | `input_message` required string ; `driver_slug` required `exists:driver_profiles,slug` |
| `app/Http/Controllers/Api/AiRequestDraftController.php` | Contrôleur des brouillons IA | `analyze()` (CRUD inchangé) : `authorize('create')` → `DriverProfile` par slug → création `AiRequestDraft` `pending` → dispatch job `afterCommit()` → **201** |
| `routes/api.php` | Routes | Ligne 53 : `POST /ai-request-drafts/analyze` dans le groupe `auth:sanctum`, middleware `throttle:10,1` |
| `tests/Feature/AiRequestDraftAnalysisTest.php` | Tests de la feature | 9 tests ; helpers `aiDraftDriver()` (slug `ai-driver-slug`) et `aiDraftAiResponse()` (réponse JSON simulée) |

## 4. Détail technique

**Contrat du service** (`AiRequestAnalyzer::analyze`) — les 9 clés retournées :

```php
[
    'recipient_name'      => string,
    'recipient_phone'     => string,
    'pickup_address'      => string,
    'delivery_address'    => string,
    'package_description'=> string,
    'product_amount'      => float|null,
    'amount_to_collect'   => float|null,
    'scheduled_at'        => string|null, // attendu ISO 8601 / Y-m-d H:i
    'service'             => string,      // RG11 : doit être un service actif du livreur
]
```

**Flux complet :** client → `POST /api/ai-request-drafts/analyze` (sanctum, throttle 10/min) → draft `pending` → job `afterCommit()` → appel **OpenRouter** (`nvidia/nemotron-3-super-120b-a12b:free`, `response_format: json_object`) → JSON parsé → `service` filtré (RG11) → `service_id` résolu → draft `done` (ou `failed` + FR) → le frontend lit le draft puis **soumet sous validation humaine**.

**Sécurité / robustesse :**
- RG11 double-gardé : le prompt ne propose que les noms actifs **et** le job re-filtre avant résolution `service_id`.
- CA05 : `error_message` = message FR uniquement (jamais le corps brut de l'API) ; corps brut loggé canal `jobs`.
- Rate limiting : `throttle:10,1` (test : 11e requête → 429).
- En test, `Http::fake()` simule l'API ; rate limit test avec `Queue::fake()` (job non exécuté).
- Piège rencontré : `QUEUE_CONNECTION=sync` exécute le job **en ligne** → sans `Queue::fake()`/`Http::fake()`, un vrai appel HTTP partait. Tests corrigés en conséquence.
- Bascule de fournisseur : passer de xAI à OpenRouter n'a touché **que** la config (`services.openrouter.*`), les messages d'erreur FR et les URLs de fake en test — le payload, le prompt et le contrat 9 clés sont restés identiques (les 2 API sont compatibles OpenAI).

## 5. Vérifications

- Pint : **passed** (`vendor/bin/pint --dirty --format agent`)
- Tests : **55 passed (185 assertions)** — `php artisan test --compact` ; filtre ciblé : `--filter=AiRequestDraftAnalysisTest` **9 passed (37 assertions)**
- Manuel (démo live) : `docker exec allo_backend php tmp_ai_demo.php` (script temporaire, supprimé après) → **succès** en ~2,25 s : `recipient_name=Sara`, `recipient_phone=06 12 34 56 78`, `pickup_address=Avenue Hassan II`, `delivery_address=quartier Al Houda Agadir`, `package_description=chaussures taille 39`, `amount_to_collect=420`, `service=Envoi urgent` (valide RG11). Piège : clé mal recopiée → HTTP 401 `Unauthorized` (message `{"error":{"message":"User not found.","code":401}}`) — corriger la clé dans `.env`.

## 6. Références / suite logique

- Branche : `feature/AI-Laravel` — PR : https://github.com/Rayantoufella/Allo_Delevry/pull/new/feature/AI-Laravel
- Rapports liés : `rapport_ar30_queues_uploads.md` (infra jobs), `rapport_ar37_recuperation_tracking.md` (brouillons), `rapport_ar41_durcissement_flux.md` (appartenance `ai_request_draft_id` — brouillon étranger rejeté 422)
- Suite : démo complète bout-en-bout via l'endpoint (draft `done` → création de demande) ; pré-règles de validation métier du `generated_data` côté frontend ; les clés xAI/Grok restent utilisables en changeant `config/services.php`