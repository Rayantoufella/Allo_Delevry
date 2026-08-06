# Guide — 01 : Authentification et rôles

**Périmètre :** `D:\AlloDelevry\backend`
**Entrées (routes) :**
- `POST /api/register` (publique, `throttle:5,1`)
- `POST /api/login` (publique, `throttle:5,1`)
- `POST /api/logout` (`auth:sanctum`)
- `GET /api/me` (`auth:sanctum`)
- Groupe `auth:sanctum` + `role:driver` : `GET /api/dashboard`, `apiResource services`, `apiResource delivery-zones` + `PATCH /delivery-zones/{id}/toggle-active`, `apiResource driver-profiles` (le *middleware* de rôle est le cœur de cette feature ; le contenu de ces routes est détaillé dans les guides 05/06)
**Référence cahier des charges :** F01 (authentification et rôles) + §6 (matrice d'autorisation client/driver)

## Vue d'ensemble

L'API est 100 % token-based : aucun cookie de session côté frontend. L'inscription crée un `User` avec un rôle imposé (`client` ou `driver`), la connexion échange identifiants contre un token Sanctum, la déconnexion révoque le token courant et `GET /me` renvoie l'utilisateur connecté (réservé à soi-même via `UserPolicy::view`). Le rôle n'est pas un paquet tiers : c'est une simple colonne `role` sur `users`, associée à des helpers `isClient()/isDriver()` sur le modèle et à un middleware `EnsureUserHasRole` (alias `role`) qui protège les routes réservées aux livreurs. Sanctum gère les tokens (table `personal_access_tokens`) ; une passe de correction (AR-05, corrections #6/#7/#9/#14/#16) a rendu le flux réellement fonctionnel : trait `HasApiTokens` manquant → 500, trait `AuthorizesRequests` manquant → policies en 500, et invités redirigés vers une route inexistante → 500 au lieu d'un 401 propre.

## Fichiers et rôles (exhaustif)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Http/Controllers/Api/AuthController.php` | Point d'entrée des 4 endpoints : `register()`, `login()`, `me()`, `logout()`. | `register()` : hash du mot de passe + `User::create` + token émis en clair (201). `login()` : lookup par email + `Hash::check` → 401 « Invalid credentials » si échec (message unique volontairement). `me()` : `$this->authorize('view', $request->user())` avant réponse. `logout()` : `currentAccessToken()->delete()` — révoque uniquement le token de la requête. |
| `app/Http/Requests/Api/Auth/LoginRequest.php` | Validation de la connexion. | Règles minimales : `email` requis+format, `password` requis. Aucun message personnalisé. `authorize()` → `true` (la vraie vérification est dans le controller). |
| `app/Http/Requests/Api/Auth/RegisterRequest.php` | Validation de l'inscription. | `role` **requis** et limité à `in:client,driver` (pas de changement de rôle ensuite) ; `email` unique ; `password` ≥ 8 + `confirmed` ; `phone` nullable ≤ 20. Messages d'erreur entièrement en français. |
| `app/Http/Middleware/EnsureUserHasRole.php` | Middleware de garde par rôle (utilisé comme `role:driver`). | Vérifie `$request->user()->role !== $role` → **403 JSON** « Access denied ». N'utilise pas de hiérarchie de rôles : simple comparaison de chaîne. S'il n'y a pas d'utilisateur, il faut que `auth:sanctum` ait déjà répondu 401. |
| `app/Models/User.php` | Modèle central : rôle + tokens + relations. | `use HasApiTokens` (correction #6 — sans lui `createToken()` n'existe pas) ; constantes `ROLE_CLIENT`/`ROLE_DRIVER` ; helpers `isClient()/isDriver()` ; `$fillable` inclut `role` et `phone` ; `casts()` : `password` → `hashed` ; attribut PHP `#[Hidden(['password','remember_token'])]` pour ne jamais sérialiser ces champs. Relations : `driverProfile`, `services`, `deliveryZones`, `aiRequestDrafts`, `notifications`, `clientRequests`, `driverRequests`. |
| `app/Policies/UserPolicy.php` | Autorisation sur la ressource `User`. | `view`/`update`/`delete` : uniquement **soi-même** (`$user->id === $model->id`). `viewAny` : `true` (utilisé par `me()`). |
| `app/Http/Controllers/Controller.php` | Contrôleur de base (hérité par tous les controllers API). | `use ApiResponse, AuthorizesRequests` — le trait `AuthorizesRequests` (correction #7) rend `$this->authorize()` fonctionnel partout, sinon toutes les policies répondaient 500. |
| `app/Traits/ApiResponse.php` | Format de réponse uniforme de toute l'API (AR-03). | `success()` → `{success, message, data}` ; `error()` → `{success, message, errors}` ; `paginated()` pour les collections. `AuthController` l'utilise pour toutes ses réponses. |
| `routes/api.php` | Déclaration des routes d'authentification et du groupe `role:driver`. | `POST /register` et `POST /login` **publiques** avec `throttle:5,1` (correction #14 — anti force brute) ; `logout`/`me` dans le groupe `auth:sanctum` ; `Route::middleware('role:driver')` encapsule dashboard/services/zones/driver-profiles. |
| `bootstrap/app.php` | Configuration du noyau : alias middleware + comportement invité + JSON. | `$middleware->alias(['role' => EnsureUserHasRole::class])` (correction #3) ; `redirectGuestsTo(fn () => abort(401))` (correction #9 — les invités reçoivent un 401 propre au lieu d'une redirection vers `route('login')` qui n'existe pas) ; `shouldRenderJsonWhen(api/*)`. |
| `database/migrations/0001_01_01_000000_create_users_table.php` | Table `users` de base Laravel. | Colonnes `name`, `email` (unique), `email_verified_at`, `password`, `rememberToken`, timestamps. Crée aussi `password_reset_tokens` et `sessions` (inutilisées en API pure). |
| `database/migrations/2026_07_22_150002_add_role_and_phone_to_users_table.php` | Ajoute le rôle et le téléphone. | `enum('role', ['client','driver'])->default('client')` + `phone` nullable. C'est ici que vit la matrice de rôles côté base. |
| `database/migrations/2026_07_22_120457_create_personal_access_tokens_table.php` | Table Sanctum des tokens. | `morphs('tokenable')` (un token par appareil/application), `token` haché (sha1 + suffixe) unique, `abilities` nullable, `expires_at` nullable + index. Fournie par Sanctum via `php artisan vendor:publish`. |
| `config/auth.php` | Guards/providers d'authentification. | Un seul guard `web` (session, provider Eloquent `App\Models\User`). L'API n'utilise pas ce guard directement : `auth:sanctum` fait appel au guard Sanctum qui résout l'utilisateur depuis le bearer token. |
| `config/sanctum.php` | Configuration Sanctum. | `expiration => null` : **les tokens n'expirent jamais** (révocation manuelle uniquement via logout) ; `stateful` : domaines SPA (localhost:3000 etc.) ; `token_prefix` vide. À surveiller en production. |
| `database/factories/UserFactory.php` | Fabrique des utilisateurs pour les tests. | États `client()`/`driver()` (rôle forcé) + `unverified()` ; mot de passe partagé `'password'` hashé une fois. Utilisée par tous les tests via `Sanctum::actingAs`. |
| `tests/Feature/DashboardTest.php` | Tests de la matrice de rôle (403/401). | Vérifie qu'un **client** reçoit 403 sur `GET /api/dashboard` (`role:driver`) et qu'un invité reçoit 401. |
| Autres tests de `tests/Feature/*` (SecurityFlowTest, TrackingTest, UploadTest, NotificationJobTest…) | Couverture implicite de l'auth. | **Aucun fichier `AuthTest`/`RegisterTest`/`LoginTest` dédié n'existe** : tous les tests simulent l'authentification via `Sanctum::actingAs()` et valident l'émersion des tokens/les 401/403 à travers les flux métier. |

## Actions passées (rapports liés)

- **AR-02 — Authentification et permissions** (`docs/rapport/rapport_ar02_authentification.md`) : installation/config Sanctum, évaluation puis **suppression de Spatie** (rôles gérés par la colonne `role`), endpoints livrés, tests 401/403.
- **AR-03 — Réponses API uniformes** (`docs/rapport/rapport_ar03_api_response.md`) : trait `ApiResponse` appliqué à `AuthController` (format `{success, message, data}`).
- **AR-05 — Correction de sécurité** (`docs/rapport/rapport_correction_delivery.md`) : correction **#6** (`HasApiTokens` manquant → 500 sur register/login), **#7** (`AuthorizesRequests` manquant → policies 500), **#9** (invités → 401 propre au lieu de `Route [login] not defined`), **#14** (`throttle:5,1` sur login/register), **#16** (retrait définitif de Spatie).

## Pièges et points d'attention

- **Le rôle est figé à l'inscription** : `RegisterRequest` impose `in:client,driver` et aucune route ne permet de le modifier ensuite. Un changement de rôle nécessite une intervention en base.
- **Tokens sans expiration** (`sanctum.expiration = null`) : la seule révocation est `logout()` (suppression du token courant). Il n'y a pas de rotation/expiration automatique — point à traiter avant la production.
- **`EnsureUserHasRole` est une comparaison de chaîne** : pas de hiérarchie ni d'administration de rôles. Le message est volontairement générique (« Access denied. ») pour ne pas révéler l'existence de la route.
- **Message 401 unique** sur login (`Invalid credentials`) : bonne pratique anti-énumération d'emails, mais aucun `throttle` spécifique ne protège le `Hash::check` lui-même — seul le rate limit global `throttle:5,1` s'applique.
- **`me()` passe par `UserPolicy::view`** : un utilisateur ne peut lire que son propre profil. `User::hidden` n'empêche pas l'accès — c'est la policy qui contrôle.
- **`#[Hidden]` vs `$hidden`** : `User` utilise l'attribut PHP `#[Hidden(['password', 'remember_token'])]` (mécanisme natif Laravel 12+/13), pas la propriété `$hidden`. Les deux coexistent selon les modèles — ne pas confondre.
- **Corrections AR-05 indissociables de cette feature** : sans `HasApiTokens` (modèle) ni `AuthorizesRequests` (contrôleur de base), toute l'auth et toutes les policies redeviennent 500.
- **Le frontend doit stocker le token en clair** (`plainTextToken`) renvoyé par register/login ; l'API n'a aucun moyen de le redonner.
