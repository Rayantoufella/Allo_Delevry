# Rapport de correction — API Allo Delivery

**Date :** 04 août 2026
**Périmètre :** `D:\AlloDelevry\backend` (Laravel 13 / PHP 8.4 / Sanctum)
**Référence :** Cahier des charges `docs/Cahier_des_charges_Allo_Delivery_V5_1.md` — **Modèle B** (page publique du livreur, *pas* une marketplace)

---

## 1. Contexte et cause racine

La correction a été entamée par un bug apparent : impossible d'attribuer une livraison à un livreur
(`driver_id` non nullable dans la MLD alors que le flux d'assignation n'existait pas).

Une **première tentative** a implémenté un modèle *marketplace* (`assign()`, `driver_id` nullable,
filtre `?available=1`, pool de demandes). Une **analyse d'architecte senior** a ensuite démontré que le
cahier des charges impose le **Modèle B** : le client scanne le QR Code du livreur X, visite
`/drivers/{slug}`, et sa demande est rattachée à X **dès la création** (`driver_id` jamais NULL).

La tentative marketplace a donc été **annulée** et le flux conforme au Modèle B implémenté. Au cours de la
vérification, plusieurs autres défauts (dont des bugs bloquants d'authentification) ont été découverts
et corrigés.

---

## 2. Tableau récapitulatif

| # | Problème | Sévérité | Avant | Maintenant |
|---|----------|----------|-------|------------|
| 1 | Aucun flux d'assignation conforme | Critique | Impossible de créer/attribuer une demande | Création via la page publique du livreur, `driver_id` fixé à la création |
| 2 | `store()` générique cassé | Critique | Endpoint `POST /delivery-requests` ne positionnait pas `driver_id` (NOT NULL) → 500 | Route retirée ; création uniquement via `POST /drivers/{slug}/delivery-requests` |
| 3 | `updateStatus` sans contrôle | Critique | N'importe quel participant pouvait mettre n'importe quel statut | Driver seul ; matrice de transitions autorisées |
| 4 | `driver_id` modifiable | Critique | `driver_id` modifiable dans `UpdateDeliveryRequest` | Règle supprimée ; l'assignation est définitive |
| 5 | Service / zone non vérifiés | Élevé | Un client pouvait lier un service d'un autre livreur | Vérification d'appartenance au livreur du slug |
| 6 | Authentification inexistante | Bloquant | `User::createToken()` n'existait pas (traits Sanctum absents) | `HasApiTokens` ajouté ; login/register fonctionnels |
| 7 | Toutes les policies 500 | Bloquant | `authorize()` indéfini dans le contrôleur de base | `AuthorizesRequests` ajouté |
| 8 | Connexion DB impossible | Bloquant | `DB_HOST=mysql` + mauvais identifiants → aucune migration possible | `DB_HOST=db` + `allo/allo/allo_delivery` (docker-compose) |
| 9 | Guests non-JSON → 500 | Élevé | Redirection vers `route('login')` inexistante | 401 (abort) au lieu de 500 |
| 10 | Pas de notification à la création | P0 (F13) | Aucune | Notification interne créée pour le livreur |
| 11 | Pas de suivi public | P0 (F11) | Aucun | `GET /tracking/{privateToken}` + ressource publique |
| 12 | Pas de QR Code | P0 (F04) | Aucun | `GET /drivers/{slug}/qr` (SVG) |
| 13 | Pas de code de confirmation | P0 (RG06) | `confirmation_code_hash` jamais rempli | Génération, vérification, expiration 30 min, 5 essais max |
| 14 | Pas de rate limiting | Élevé (§15) | Aucun | `throttle:5,1` sur login/register |
| 15 | Route morte `/user` | Faible | Renvoyait toujours `null` (sans auth) | Supprimée |
| 16 | Spatie/permission inutilisé | Faible | Installé mais jamais utilisé | Retiré (composer + migration + config) |

---

## 3. Détail par correction : Avant / Maintenant / Cause

### 3.1 Flux d'assignation conforme au Modèle B (#1)

**Cause :** le cahier des charges exige que la demande soit adressée directement au livreur (RG01, RG03) ;
le modèle marketplace contredisait la MLD (`driver_id` non nullable).

**Avant (après la tentative marketplace) :**
- `POST /delivery-requests/assign`, policy `assign()`, `driver_id` nullable via migration, filtre `?available=1`.

**Maintenant :**
- `GET  /drivers/{slug}` — page publique du livreur (services + zones actifs).
- `GET  /drivers/{slug}/qr` — QR Code pointant vers la page publique.
- `POST /drivers/{slug}/delivery-requests` — création de demande rattachée au livreur du slug.
- `driver_id` reste **non nullable** (migration `make_driver_id_nullable` supprimée).
- Factory : `driver_id` positionné par défaut ; états `forClient()`/`forDriver()` conservés.

### 3.2 `store()` générique cassé (#2)

**Cause :** `driver_id` est NOT NULL mais le `store()` générique ne le positionnait jamais → erreur DB à chaque création.

**Avant :** `Route::apiResource('delivery-requests', ...)` incluait `store` ; `POST /delivery-requests` → 500.

**Maintenant :** la route `store` est retirée (`->except(['store'])`). La création passe uniquement par la page publique du livreur. La méthode `store()` du contrôleur a été supprimée (code mort).

### 3.3 Contrôle des transitions de statut (#3, RG06, F10)

**Cause :** `updateStatus` utilisait `authorize('update')` (client *et* driver) sans matrice de transitions ; le statut `livree` était atteignable sans code ni preuve.

**Avant :** `PATCH /delivery-requests/{id}/status` — tout participant, toute transition.

**Maintenant :**
- **Policy `updateStatus`** : driver uniquement.
- **Matrice de transitions autorisées :**
  - `en_attente → prix_propose | refusee`
  - `prix_propose → refusee`
  - `confirmee → colis_recupere`
  - `colis_recupere → en_livraison`
  - `en_livraison → echec`
- **`livree`** uniquement via `POST /delivery-requests/{id}/confirm-delivery` (code confirmé + preuve, RG06).
- Nouvelles actions client : `confirmPrice` (`prix_propose → confirmee`, RG05) et `cancel` (annulation client).

### 3.4 `driver_id` non modifiable (#4)

**Cause :** l'update autorisait le transfert d'une demande vers un autre livreur, contraire au Modèle B.

**Avant :** `'driver_id' => ['nullable', 'exists:users,id']` dans `UpdateDeliveryRequest`.

**Maintenant :** règle supprimée. L'assignation est définitive à la création.

### 3.5 Services / zones liés à un autre livreur (#5)

**Cause :** validation `exists:services,id` globale, sans lien au livreur.

**Avant :** un client pouvait référencer un service ou une zone d'un autre livreur.

**Maintenant :** dans `storeForDriver`, le service et la zone doivent appartenir au livreur du slug (sinon 422).

### 3.6 Authentification Sanctum absente (#6)

**Cause :** le modèle `User` n'utilisait ni `HasApiTokens` ni le trait Sanctum → `createToken()` inexistant.

**Avant :** `POST /api/register` et `/api/login` → **500** (`Call to undefined method User::createToken()`). Aucun token ne pouvait être émis.

**Maintenant :** `use HasApiTokens` dans `App\Models\User`. Register/login fonctionnels (testés : token émis).

### 3.7 Toutes les policies en 500 (#7)

**Cause :** le contrôleur de base (`App\Http\Controllers\Controller`) n'utilisait pas le trait `AuthorizesRequests`.

**Avant :** chaque `$this->authorize(...)` (toutes les policies de l'app) → **500**.

**Maintenant :** `use AuthorizesRequests` dans le contrôleur de base.

### 3.8 Connexion base de données (#8)

**Cause :** `.env` pointait vers `mysql` (service inexistant) avec des identifiants `sail`/`password` alors que `docker-compose.yml` définit un service `db` avec `allo/allo/allo_delivery`.

**Avant :** `DB_HOST=mysql`, `DB_DATABASE=Allo_Delevry`, `DB_USERNAME=sail`, `DB_PASSWORD=password` → `SQLSTATE[HY000]` / Access denied.

**Maintenant :** `DB_HOST=db`, `DB_DATABASE=allo_delivery`, `DB_USERNAME=allo`, `DB_PASSWORD=allo`. Migration + seed OK.

### 3.9 Guests non-JSON → 500 au lieu de 401 (#9)

**Cause :** redirection par défaut des utilisateurs non authentifiés vers `route('login')`, qui n'existe pas (API pure).

**Avant :** requête non authentifiée sans `Accept: application/json` → 500 `Route [login] not defined`.

**Maintenant :** `redirectGuestsTo(fn () => abort(401))` → 401 propre.

### 3.10 Notification à la création de demande (#10, F13)

**Cause :** fonctionnalité manquante.

**Avant :** aucune notification au livreur lors d'une nouvelle demande.

**Maintenant :** `storeForDriver` crée une `Notification` (`delivery_request_created`) pour le livreur.

### 3.11 Suivi public de livraison (#11, F11)

**Cause :** fonctionnalité manquante.

**Avant :** aucun moyen pour le destinataire de suivre la livraison.

**Maintenant :** `GET /tracking/{privateToken}` → `PublicTrackingResource` (numéro, statut, timeline, adresses) sans authentification.

### 3.12 QR Code (#12, F04)

**Cause :** fonctionnalité manquante (le package `simplesoftwareio/simple-qrcode` était déjà installé).

**Avant :** aucun QR Code.

**Maintenant :** `GET /drivers/{slug}/qr` renvoie un QR Code SVG pointant vers la page publique du livreur.

### 3.13 Code de confirmation (#13, RG06)

**Cause :** la colonne `confirmation_code_hash` existait mais aucun flux de génération/vérification.

**Avant :** pas de code, pas d'expiration, pas de limite d'essais.

**Maintenant :**
- Migration `2026_08_04_150000` : `confirmation_code_expires_at`, `confirmation_code_attempts`.
- `POST /delivery-requests/{id}/generate-code` (driver) : code 6 chiffres haché, expiration 30 min, essais remis à 0, retour du code en clair.
- `POST /delivery-requests/{id}/confirm-delivery` (driver) : exige statut `en_livraison`, code non expiré, ≤ 5 essais, **preuve existante** ; code vérifié → `livree` + `delivered_at` ; sinon essai incrémenté et 422.

### 3.14 Rate limiting (#14, §15)

**Cause :** exigence du cahier des charges non implémentée.

**Avant :** login/register sans limite → exposés aux attaques par force brute.

**Maintenant :** `throttle:5,1` (5 tentatives/minute) sur `POST /login` et `POST /register`.

### 3.15 Route morte `/user` (#15)

**Cause :** reliquat du template Laravel, sans middleware d'auth (`$request->user()` toujours `null`).

**Avant :** `GET /api/user` renvoyait `null`.

**Maintenant :** supprimée (`/me` reste l'endpoint authentifié).

### 3.16 Spatie/permission inutilisé (#16)

**Cause :** installé mais jamais utilisé dans `app/` ; la gestion des rôles se fait via la colonne `users.role` + middleware `EnsureUserHasRole`.

**Avant :** package + migration `permission_tables` + `config/permission.php` présents.

**Maintenant :** `composer remove spatie/laravel-permission`, migration et config supprimées.

---

## 4. Fichiers modifiés / créés

**Modifiés**
- `routes/api.php`
- `app/Http/Controllers/Api/DeliveryRequestController.php`
- `app/Http/Controllers/Api/DriverProfileController.php`
- `app/Policies/DeliveryRequestPolicy.php`
- `app/Http/Requests/UpdateDeliveryRequest.php`
- `app/Models/DeliveryRequest.php`
- `app/Models/User.php`
- `app/Http/Controllers/Controller.php`
- `bootstrap/app.php`
- `.env`
- `composer.json` / `composer.lock` (retrait spatie)

**Créés**
- `app/Http/Resources/PublicTrackingResource.php`
- `database/migrations/2026_08_04_150000_add_confirmation_code_meta_to_delivery_requests_table.php`

**Supprimés**
- `database/migrations/2026_08_04_141804_make_driver_id_nullable_on_delivery_requests_table.php`
- `database/migrations/2026_07_28_091538_create_permission_tables.php`
- `config/permission.php`

### 4.1 Passe sécurité / intégrité (B1–B11)

**Modifiés**
- `app/Http/Resources/DriverProfileResource.php` (B1)
- `app/Http/Controllers/Api/RequestStatusHistoryController.php` (B3)
- `app/Http/Controllers/Api/DeliveryRequestController.php` (B6/B7/RG06)
- `app/Http/Controllers/Api/ReviewController.php` (B11)
- `app/Http/Requests/UpdateDeliveryRequest.php` (B6)
- `app/Http/Requests/UpdateDeliveryProofRequest.php`, `UpdateChatMessageRequest.php`, `UpdateIncidentRequest.php`, `UpdateGpsLocationRequest.php`, `UpdateReviewRequest.php` (B10)
- `app/Policies/DeliveryProofPolicy.php` (B4), `ChatMessagePolicy.php` (B5), `GpsLocationPolicy.php` (B8), `IncidentPolicy.php` (B9), `DeliveryRequestPolicy.php` (RG06), `ReviewPolicy.php` (B11)
- `routes/api.php` (B3)

**Créés**
- `tests/Feature/DeliveryRequestSecurityFlowTest.php`

**Supprimés**
- `app/Http/Requests/StoreRequestStatusHistoryRequest.php`
- `app/Http/Requests/UpdateRequestStatusHistoryRequest.php`

---

## 5. Vérifications effectuées

1. `vendor/bin/pint --dirty` → **passed**.
2. `php artisan migrate:fresh --seed` → **OK** (18 migrations + seeders).
3. Test fonctionnel de bout en bout (register client + driver → profil → création demande → transitions → prix confirmé → code → preuve → livraison) :
   - `client updateStatus` → **403** (driver uniquement) ✅
   - transition invalide `prix_propose → confirmee` via status → **422** ✅
   - `confirm-price` client → **200** ✅
   - `confirm-delivery` sans preuve → **422** ✅
   - mauvais code → **422** ✅
   - bon code + preuve → **200**, statut `livree` + `delivered_at` ✅

---

## 6. Correction sécurité / intégrité (B1–B11)

**Date :** 04 août 2026 — **Périmètre :** bugs Critiques + Élevés (les moyens B12–B17 sont différés ; les paiements B2 sont différés vers l'epic paiement en ligne).

### B1 — Fuite de données sensibles sur le profil public

| | |
|---|---|
| **Avant** | `DriverProfileResource` sérialisait l'utilisateur complet → **RIB, email, phone, user_id** exposés sur `GET /drivers/{slug}` (public). |
| **Maintenant** | `toArray()` explicite : `id`, `brand_name`, `slug`, `logo_path`, `city`, `is_available` + services/zones **actifs** uniquement. Le `rib` et `user_id` ne sont renvoyés que si l'utilisateur authentifié **est le propriétaire** du profil. |

### B3 — Historique de statut falsifiable

| | |
|---|---|
| **Avant** | `POST/PUT/DELETE /request-status-histories` permettaient d'ajouter/supprimer des entrées d'audit. |
| **Maintenant** | Routes limitées à `index/show` (lecture seule). FormRequests `Store/UpdateRequestStatusHistoryRequest` supprimés. |

### B4 — Preuve de livraison déposée par le client

| | |
|---|---|
| **Avant** | `DeliveryProofPolicy::create` autorisait les deux participants. |
| **Maintenant** | Création **driver uniquement** (`driver_id === user->id`). |

### B5 — Messages de chat modifiables par le correspondant

| | |
|---|---|
| **Avant** | `ChatMessagePolicy::update/delete` basées sur la participation à la demande. |
| **Maintenant** | Seul l'**expéditeur** (`sender_id === user->id`) peut modifier/supprimer son message. |

### B6 — Prix proposé et champs métier modifiables à tout moment

| | |
|---|---|
| **Avant** | `proposed_price` modifiable via `PATCH /delivery-requests/{id}` ; adresses/montants modifiables après confirmation. |
| **Maintenant** | `proposed_price` retiré de `UpdateDeliveryRequest` ; il est **posé par le driver lors de la transition vers `prix_propose`** (`PATCH .../status` avec `proposed_price` requis). Les modifications `PUT` sont refusées (422) dès `colis_recupere` (statuts non-éditables : `colis_recupere`, `en_livraison`, `livree`, `echec`). Le service/zone modifié doit appartenir au driver de la demande. |

### B7 — Suppression destructive d'une demande active

| | |
|---|---|
| **Avant** | `DELETE /delivery-requests/{id}` supprimait une demande quel que soit son statut. |
| **Maintenant** | Suppression limitée aux statuts **terminaux** (`livree`, `refusee`, `echec`, `annulee`), sinon 422. |

### B8 — Position GPS injectable par le client

| | |
|---|---|
| **Avant** | `GpsLocationPolicy::create` autorisait les deux participants. |
| **Maintenant** | Création **driver uniquement**. |

### B9 — Incident modifiable par le correspondant

| | |
|---|---|
| **Avant** | `IncidentPolicy::update/delete` basées sur la participation. |
| **Maintenant** | Seul le **signaleur** (`reported_by === user->id`) peut modifier/supprimer l'incident. |

### B10 — `delivery_request_id` modifiable dans les updates

| | |
|---|---|
| **Avant** | `delivery_request_id` modifiable dans `UpdateDeliveryProofRequest`, `UpdateChatMessageRequest`, `UpdateIncidentRequest`, `UpdateGpsLocationRequest`, `UpdateReviewRequest` → réassignation frauduleuse. |
| **Maintenant** | Règle retirée de ces 5 FormRequests ; l'appartenance est fixée à la création. |

### B11 — Doublon d'avis (500) et avis avant livraison

| | |
|---|---|
| **Avant** | Un second avis sur la même demande déclenchait une erreur 500 (contrainte d'unicité) ; avis possible avant livraison ; le driver pouvait noter. |
| **Maintenant** | Avis **client uniquement**, sur demande **livrée** uniquement ; doublon → **422** avec message clair. |

### RG06 — Confirmation de livraison par le client

| | |
|---|---|
| **Avant** | `confirm-delivery` (saisie du code RG06) autorisée au **driver**. |
| **Maintenant** | La saisie du code est **côté client** (`client_id === user->id`) : le client saisit le code que le driver lui transmet. Le driver ne peut pas auto-confirmer (403). |

---

## 7. Vérifications effectuées (passe B1–B11)

1. `vendor/bin/pint --dirty` → **passed**.
2. Nouveaux tests Pest `tests/Feature/DeliveryRequestSecurityFlowTest.php` (12 tests, ajoutés) — **15 passed (47 assertions)** au total :
   - Flux RG06 complet client : création → prix proposé (avec prix) → confirmation client → code → colis récupéré → en livraison → preuve (driver) → **confirmation par le client** → `livree` + `delivered_at` ✅
   - Le driver ne peut **pas** confirmer la livraison (403) ; le client ne peut **pas** générer le code (403) ✅
   - `prix_propose` sans `proposed_price` → 422 ; transition interdite → 422 ✅
   - Preuve et GPS : driver OK, client 403 ✅
   - Édition après `en_livraison` → 422 ; suppression non-terminale → 422 ✅
   - Avis : non-livré → 422, doublon → 422, driver → 403 ✅
   - Profil public sans `rib`/`user_id`/`email`/`phone` ✅
   - Historique : POST → 405 (lecture seule) ✅

---

## 8. Points restants (hors périmètre)

- Tests Pest : création **laissée à l'utilisateur** (demande explicite).
- Envoi d'email réel (F13) : `MAIL_MAILER=log` (démo) — Mailable non implémenté.
- Dépôt de preuves par fichier/image (RG06) : la preuve est un enregistrement `DeliveryProof` ; l'upload n'est pas traité.
- Bugs de sévérité moyenne (B12–B17) : différés.
- Paiements falsifiables (B2) : différés vers l'epic paiement en ligne.
