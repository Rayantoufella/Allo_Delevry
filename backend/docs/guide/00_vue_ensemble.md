# Guide — 00 : Vue d'ensemble du projet (backend)

**Périmètre :** `D:\AlloDelevry\backend`
**Point de départ :** `routes/api.php` → `app/Http/Controllers/Api/*` → `app/Models/*` → `app/Http/Resources/*` → `app/Policies/*`

## Vue d'ensemble

API REST Laravel 13 (PHP 8.4) pour une application de **livraison à la demande** : un **client** crée une demande, un **livreur** la prend en charge et la livre, avec chat client/livreur (rafraîchi par polling — le temps réel Reverb a été retiré), notifications internes, preuves de livraison et tableau de bord livreur. Le frontend est une SPA **Vue.js séparée** (dossier `frontend/`) qui consomme uniquement l'API — aucun template Blade côté backend (décision utilisateur : l'initiative « ticket PDF » a été annulée).

**Empilement technique :** Laravel 13 · PHP 8.4 · Sanctum (tokens API) · Pest (tests) · MySQL 8 (Docker) · Queue `database` · Redis absent (non requis) · WebSocket Reverb **retiré** (polling à la place, voir `rapport_suppression_reverb.md`).

## Schéma des couches (flux d'une requête)

```
Requête HTTP
  → routes/api.php (groupe auth:sanctum, role:driver, throttle)
    → middleware : EnsureUserHasRole, throttle, sanctum
      → app/Http/Controllers/Api/* (logique + authorize() via Policies)
        → app/Http/Requests/* (validation, parfois authorize())
          → app/Models/* (Eloquent : relations, scopes, machine à états)
            → app/Jobs/* (traitement asynchrone, après commit)
              → app/Http/Resources/* (formatage de la réponse JSON)
                → Réponse JSON uniforme {success, message, data|errors} (app/Traits/ApiResponse.php)
```

## Infrastructure Docker (`D:\AlloDelevry\docker-compose.yml`)

| Service | Conteneur | Rôle | Port |
|---------|-----------|------|------|
| `app` | `allo_backend` | PHP-FPM : le backend (monté sur `./backend`) | — |
| `queue` | `allo_queue` | Worker `php artisan queue:work database --tries=3 --sleep=3 --timeout=60` | — |
| `nginx` | `allo_nginx` | Reverse proxy HTTP → app | 8000 |
| `db` | `allo_db` | MySQL 8 (base `allo_delivery`, user `allo`) | 3306 |
| `phpmyadmin` | `allo_phpmyadmin` | Interface web de la base (PMA_HOST=db) | 8081 |
| `frontend` | `allo_frontend` | SPA Vue.js (npm run dev) | 5173 |

**Commandes utiles :** `docker exec allo_backend php artisan test --compact` · `docker exec allo_backend vendor/bin/pint --dirty` · **`docker compose` (plugin) n'existe pas sur cette machine — toujours `docker-compose`**.

## Fichiers et rôles (exhaustif)

### Entrée de l'application

| Fichier | Rôle | Points clés |
|---------|------|-------------|
| `routes/api.php` | **Toutes les routes API** (voir le détail par feature dans les guides 01-06) | Routes publiques : register/login (`throttle:5,1`), profil public livreur + QR (`throttle:60,1`), tracking (`throttle:60,1`) ; groupe `auth:sanctum` : me/logout, delivery-requests + actions (status/confirm-price/cancel/generate-code/confirm-delivery), notifications, chat, preuves, incidents, GPS, paiements, historique ; groupe `role:driver` : dashboard, services, zones, profils livreur |
| `routes/console.php` | Commandes artisan custom | Vide par défaut (pas de scheduler : décision utilisateur — pas de cron) |
| `routes/web.php` | Route web par défaut | Non utilisée (API pure) |
| `bootstrap/app.php` | Noyau : alias middleware `role`, JSON pour `api/*`, 401 propre pour les invités | `redirectGuestsTo(fn () => abort(401))` + `shouldRenderJsonWhen(api/*)` |

### Modèle de base commun

| Fichier | Rôle | Points clés |
|---------|------|-------------|
| `app/Http/Controllers/Controller.php` | Contrôleur de base | `use ApiResponse, AuthorizesRequests` — sans `AuthorizesRequests` toutes les policies répondaient 500 (correction AR-05 #7) |
| `app/Traits/ApiResponse.php` | Format uniforme `{success, message, data}` / `{success, message, errors}` | `success()`, `error()`, `paginated()` — utilisé par AuthController et le CRUD standard |
| `app/Http/Middleware/EnsureUserHasRole.php` | Middleware de rôle (`role:driver`) | Comparaison de chaîne `role` → 403 JSON « Access denied » |
| `app/Providers/AppServiceProvider.php` | Boot du framework | Minimal (aucune logique métier) |

### Configuration (points clés)

| Fichier | Rôle | Points clés |
|---------|------|-------------|
| `.env` | Variables d'environnement | `BROADCAST_CONNECTION=null` (temps réel désactivé), `QUEUE_CONNECTION=database`, `FRONTEND_URL` ; variables `GEMINI_*` pour l'IA |
| `phpunit.xml` | Config de test | `BROADCAST_CONNECTION=null` (aucune diffusion réelle), `QUEUE_CONNECTION=sync`, **DB sqlite :memory:** |
| `config/auth.php` | Guards/providers | Guard `web` par défaut ; l'API passe par le guard Sanctum (bearer token) |
| `config/sanctum.php` | Tokens Sanctum | `expiration => null` : **tokens sans expiration** (révocation via logout) — point à surveiller en prod |
| `config/broadcasting.php` | Driver broadcast | Fichier standard Laravel ; défaut `null` (`BROADCAST_CONNECTION`) — aucun driver actif |
| `config/queue.php` | Files d'attente | Default `database` (table `jobs`) |
| `config/logging.php` | Canaux de log | Canal `jobs` ajouté (daily, `storage/logs/jobs.log`) pour les échecs de jobs |
| `config/filesystems.php` | Stockage | Disque `public` (root `storage/app/public`) pour les preuves — `php artisan storage:link` exécuté |
| `config/database.php` | Connexions | MySQL pour l'app, sqlite pour les tests |
| `config/cache.php` / `config/session.php` / `config/mail.php` / `config/services.php` | Config par défaut Laravel | **Non utilisées par le projet** (pas de cache Redis, pas de session SPA, pas de mail, pas de services tiers configurés) |

### Tests

| Fichier | Rôle | Points clés |
|---------|------|-------------|
| `tests/Pest.php` | Configuration Pest | `pest()->extend(TestCase)->use(RefreshDatabase)->in('Feature')` — chaque test part d'une base vierge |
| `tests/TestCase.php` | Base des tests | `createApplication()` Laravel standard |
| `tests/Feature/*` (12 fichiers) | Tests fonctionnels par feature | Voir les guides 01-06 ; **aucun test unitaire pur** (sauf l'ExampleTest) |
| `tests/Feature/ExampleTest.php` / `tests/Unit/ExampleTest.php` | Stubs Laravel par défaut | À ne pas prendre pour des tests réels ; seul le stubbing d'exemple (identique à `DeliveryRequestFlowTest`) |

### Base de données (19 migrations)

| Migration | Table |
|-----------|-------|
| `0001_01_01_000000` | `users`, `password_reset_tokens`, `sessions` |
| `0001_01_01_000001_create_cache_table.php` | `cache` (stockage de cache) |
| `0001_01_01_000002_create_jobs_table.php` | `jobs` (queue database) |
| `2026_07_22_120457` | `personal_access_tokens` (Sanctum) |
| `2026_07_22_141302` | `driver_profiles` |
| `2026_07_22_141309` | `services` |
| `2026_07_22_141315` | `delivery_zones` |
| `2026_07_22_141316` | `ai_request_drafts` |
| `2026_07_22_141317` | `delivery_requests` |
| `2026_07_22_141318` | `chat_messages` |
| `2026_07_22_141319` | `request_status_histories` |
| `2026_07_22_141320` | `reviews` |
| `2026_07_22_141321` | `notifications` |
| `2026_07_22_141322` | `incidents` |
| `2026_07_22_141323` | `delivery_proofs` |
| `2026_07_22_150000` | `gps_locations` |
| `2026_07_22_150001` | `payment_transactions` |
| `2026_07_22_150002` | `role` + `phone` sur `users` |
| `2026_08_04_150000` | `confirmation_code_hash` + `confirmation_code_expires_at` sur `delivery_requests` (legacy, **supprimées par la migration `2026_08_11_000000`**) |

## Actions passées (rapports liés)

- **AR-01** Initialisation (Laravel + Docker + MLD + CRUD) — `docs/rapport/rapport_ar01_initialisation.md`
- **AR-02** Auth/permissions, **AR-03** réponses uniformes, **AR-04** Reverb config (**retiré**, voir `rapport_suppression_reverb.md`), **AR-05** correction sécurité, **AR-30** queues/uploads, **AR-37** tracking privé, **AR-39** dashboard — voir `docs/rapport/README.md`

## Pièges et points d'attention

- **Toujours passer par Docker** pour PHP/artisan (`docker exec allo_backend ...`) : la machine hôte n'a pas PHP configuré pour le projet.
- **`docker-compose`** (trait d'union) et non `docker compose` (plugin absent).
- La **réponse de `tracking()` est enveloppée dans `data`** (JsonResource) → chemins `data.*` dans les tests.
- **PowerShell : pas de `rg`** → utiliser `Select-String` ; tinker : éviter les variables `$` (interpolation PowerShell).
- L'arborescence `app/` suit le pattern Laravel standard ; chaque ressource CRUD = Controller + 2 FormRequests (Store/Update) + Resource + Policy (détail dans les guides par feature).
