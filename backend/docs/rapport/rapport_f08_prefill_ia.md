# Rapport — F08 : Préremplissage IA des demandes (Grok / xAI)

**Date :** 6 août 2026
**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** Cahier des charges — F08 (préremplissage IA), §9.2 (workflow IA), RG11 (services actifs)
**Statut :** Terminé (démo live en attente d'une clé xAI valide)
**Branche :** `feature/AI-Laravel` — commit(s) : à venir

## 1. Contexte

F08 : le client saisit un **message libre** (« Livrer demain à Sara à Al Houda, 420 DH à encaisser… ») ; l'IA le transforme en **données structurées** qui préremplissent le formulaire de demande de livraison, toujours **sous validation humaine** (le brouillon reste un simple draft). Décisions utilisateur : **seulement le préremplissage** (pas de réponses auto du chat), endpoint protégé `auth:sanctum`, `driver_slug` obligatoire (le préremplissage est limité aux **services actifs du livreur ciblé** — RG11), modèle par défaut `grok-4.5`, paquet `laravel/ai` **non utilisé** (appel HTTP direct via Laravel `Http`).

## 2. Tableau récapitulatif des actions

| # | Action | Résultat (commit, fichiers, décision) |
|---|--------|--------------------------------------|
| 1 | Configurer le fournisseur xAI | `config/services.php` (bloc `xai`), `.env` (`XAI_API_KEY`, `XAI_MODEL=grok-4.5`), `.env.example` (variables vides) |
| 2 | Créer le service d'analyse IA | `app/Services/AiRequestAnalyzer.php` + `app/Exceptions/AiAnalysisException.php` — contrat 9 clés, messages FR |
| 3 | Créer le job asynchrone | `app/Jobs/AnalyzeAiRequestDraftJob.php` — appel réel, RG11, statuts `done`/`failed` |
| 4 | Exposer l'endpoint | `AiRequestDraftController::analyze()` + `AnalyzeAiRequestDraftRequest` + route `POST /api/ai-request-drafts/analyze` (`throttle:10,1`) |
| 5 | Tests | `tests/Feature/AiRequestDraftAnalysisTest.php` — 9 tests (succès, RG11, erreurs IA, 422, rate limit) |
| 6 | Vérifications | Pint passé ; suite complète **55 passed (185 assertions)** |
| 7 | Démo live | Tentée avec 2 clés : appel réel vers `api.x.ai` OK mais **clés rejetées** (`Incorrect API key provided`) — à refaire avec une clé valide |
| 8 | Documentation | Ce rapport + index `README.md` + guides 02/05/09 mis à jour |

## 3. Rôle des fichiers (obligatoire)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `config/services.php` | Configuration du fournisseur IA | Bloc `xai` : `base_url` (env `XAI_BASE_URL`, défaut `https://api.x.ai/v1`), `api_key` (env `XAI_API_KEY`), `model` (env `XAI_MODEL`, défaut `grok-4.5`) |
| `.env` | Secrets (gitignoré) | `XAI_API_KEY` + `XAI_MODEL=grok-4.5` ; **jamais commité** |
| `.env.example` | Modèle de configuration | Variables xAI vides (aucun secret) |
| `app/Services/AiRequestAnalyzer.php` | **Service d'analyse IA** | `analyze(string $freeText, array $activeServiceNames): array` ; POST `/chat/completions` avec `response_format: json_object` ; contrat 9 clés : `recipient_name`, `recipient_phone`, `pickup_address`, `delivery_address`, `package_description`, `product_amount` (float\|null), `amount_to_collect` (float\|null), `scheduled_at`, `service` ; **RG11** : `service` filtré par noms de services actifs (insensible casse) ; normalise les montants en float ; **zéro accès DB** ; erreurs levées en `AiAnalysisException` |
| `app/Exceptions/AiAnalysisException.php` | Exception métier IA | `extends RuntimeException` ; messages FR : « Clé API xAI non configurée (XAI_API_KEY). », « Erreur API xAI (HTTP {status}). » (détail du corps loggé au canal `jobs`), « Réponse IA vide ou format inattendu. », « JSON invalide retourné par l'IA. » |
| `app/Jobs/AnalyzeAiRequestDraftJob.php` | **Job d'analyse asynchrone** | `__construct(AiRequestDraft $draft, int $driverUserId)` ; `tries=3`, `timeout=60`, `backoff=[10,60]` ; no-op si statut ≠ `pending` ; relit les services actifs du livreur en base (double garde RG11) ; résout `service_id` par nom (fallback `LOWER(name)`) ; écrit `done`/`failed` + `error_message` contrôlé (CA05 : pas de fuite du corps brut) ; erreurs loggées canal `jobs` |
| `app/Http/Requests/AnalyzeAiRequestDraftRequest.php` | Validation de l'endpoint | `input_message` : required, string ; `driver_slug` : required, string, `exists:driver_profiles,slug` |
| `app/Http/Controllers/Api/AiRequestDraftController.php` | Contrôleur des brouillons IA | Ajout de la méthode `analyze()` (CRUD inchangé) : `authorize('create')` → `DriverProfile` par slug → **création** d'un `AiRequestDraft` `pending` avec le message → dispatch du job `afterCommit()` → **201** avec le brouillon |
| `routes/api.php` | Routes | Ligne 53 : `Route::post('/ai-request-drafts/analyze', ...)` dans le groupe `auth:sanctum`, middleware `throttle:10,1` (10 req/min) |
| `tests/Feature/AiRequestDraftAnalysisTest.php` | Tests de la feature | 9 tests — voir détail technique ; helpers `aiDraftDriver()` (slug `ai-driver-slug`) et `aiDraftGrokResponse()` (réponse JSON simulée) |

## 4. Détail technique

**Contrat du service** (`AiRequestAnalyzer::analyze`) — les 9 clés retournées :

```php
[
    'recipient_name'     => string,
    'recipient_phone'    => string,
    'pickup_address'     => string,
    'delivery_address'   => string,
    'package_description'=> string,
    'product_amount'     => float|null,
    'amount_to_collect'  => float|null,
    'scheduled_at'       => string|null, // attendu ISO 8601 / Y-m-d H:i
    'service'            => string,      // RG11 : doit être un nom de service actif du livreur
]
```

**Flux complet :** client → `POST /api/ai-request-drafts/analyze` (auth:sanctum, throttle 10/min) → draft `pending` créé → job dispatché `afterCommit()` → job appelle l'API xAI (modèle `grok-4.5`, `response_format: json_object`) → JSON parsé → `service` filtré par les services actifs du livreur (RG11) → `service_id` résolu → draft `done` (ou `failed` + `error_message` FR) → le frontend lit le draft et **soumet le formulaire sous validation humaine**.

**Sécurité / robustesse :**
- RG11 double-gardé : le prompt du service ne propose que les noms actifs **et** le job re-filtre côté job avant résolution `service_id`.
- CA05 : `error_message` du draft = message FR du service uniquement (jamais le corps brut de l'API) ; le corps brut est loggé au canal `jobs` pour le diagnostic.
- Rate limiting : `throttle:10,1` sur l'endpoint (test : 11e requête → 429).
- En test, `Http::fake()` simule l'API ; le test de rate limit utilise `Queue::fake()` (job non exécuté).
- Piège rencontré : en test, `QUEUE_CONNECTION=sync` exécute le job **en ligne** → sans `Queue::fake()`/`Http::fake()`, un vrai appel HTTP partait (400/500). Les deux tests concernés ont été corrigés en conséquence.

## 5. Vérifications

- Pint : **passed** (`vendor/bin/pint --dirty --format agent`)
- Tests : **55 passed (185 assertions)** — `php artisan test --compact` ; filtre ciblé : `--filter=AiRequestDraftAnalysisTest` **9 passed (37 assertions)**
- Manuel (démo live) : appel réel vers `https://api.x.ai/v1/chat/completions` depuis le conteneur — **connectivité OK** (DNS, TLS), mais **les 2 clés fournies sont rejetées** par xAI (`{"code":"invalid-argument","error":"Incorrect API key provided."}`) ; vérification hors Laravel en cURL/PHP direct (même rejet → le problème est la clé, pas le code). **À refaire avec une clé valide** (console.x.ai, clé active) : `php artisan tinker` → `(new AiRequestAnalyzer)->analyze('...', ['Envoi de colis'])`

## 6. Références / suite logique

- Branche : `feature/AI-Laravel` — PR : https://github.com/Rayantoufella/Allo_Delevry/pull/new/feature/AI-Laravel
- Rapports liés : `rapport_ar30_queues_uploads.md` (infra jobs), `rapport_ar37_recuperation_tracking.md` (brouillons), `rapport_ar41_durcissement_flux.md` (appartenance `ai_request_draft_id` — un brouillon d'un autre client est rejeté 422 à la création de demande)
- Suite : brancher une clé xAI valide dans `.env` puis démo live complète (endpoint → draft `done` → création de demande) ; éventuellement prérègles de validation métier du `generated_data` côté frontend
