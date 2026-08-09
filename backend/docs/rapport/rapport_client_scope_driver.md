# Rapport — Scoping client/livreur : compte client rattaché à un livreur précis

**Date :** 8 août 2026
**Périmètre :** `D:\AlloDelevry\backend` (tests, seeders, documentation — voir aussi le rapport côté backend-specialist pour `app/`, `routes/`, `database/migrations/`, `database/factories/`)
**Référence :** cahier des charges — inscription client scopée à un livreur (lien public `/drivers/{slug}` / QR code)
**Statut :** Terminé (côté tests/docs/seeders)
**Branche :** `feature/FrontEnd` — commit(s) : en cours (non commité au moment du rapport)

## 1. Contexte

Le compte client était jusqu'ici global : `users.email` était unique sur toute la table, indépendamment du livreur avec lequel le client interagit. Cette feature rattache chaque compte client à un livreur précis : l'inscription se fait toujours dans le contexte d'un livreur (via son lien public ou son QR code), et un même e-mail peut désormais créer un compte distinct chez deux livreurs différents. Ce rapport couvre la part **tests + seeders + documentation** du travail ; l'implémentation (`app/`, `routes/`, migrations, factories) a été menée en parallèle par `backend-specialist`, contre le même contrat API figé.

## 2. Tableau récapitulatif des actions

| # | Action | Résultat (fichiers, décision) |
|---|--------|--------------------------------------|
| 1 | Audit des 14 fichiers de tests existants | Aucun n'appelait `User::factory()->create()` nu en attendant implicitement un rôle : tous utilisaient déjà `->client()` ou `->driver()` explicitement. Aucune adaptation nécessaire de ce côté. |
| 2 | Correction de 2 fichiers de tests existants suite à l'implémentation réelle | `DeliveryRequestFlowTest.php`, `DeliveryRequestNotificationJobTest.php` : le client était créé via `User::factory()->client()->create()` (parent livreur **aléatoire**) puis utilisé pour poster sur le slug d'un livreur **différent** créé séparément → 403 attendu par la nouvelle règle de scoping. Corrigé en créant le client via `->clientOf($driver)` sur le bon livreur. |
| 3 | Écriture de `tests/Feature/ClientDriverScopingTest.php` | 19 nouveaux tests d'isolation client/livreur (inscription, connexion, `/me`, demandes, chat, avis, brouillons IA, preuves, incidents). Voir détail §5. |
| 4 | Mise à jour de `database/seeders/UserSeeder.php` | Le client démo (`client@example.com`) est désormais rattaché explicitement au livreur démo (`driver@example.com`) via `driver_id`, au lieu d'un `driver_id` implicite NULL. |
| 5 | Mise à jour de `database/seeders/DeliveryRequestsSeeder.php` | Remplacement du double `recycle($clients)` / `recycle($drivers)` indépendant (qui pouvait générer des demandes avec `client.driver_id != driver_id`) par des paires (client, son propre livreur) tirées de façon cohérente. |
| 6 | Vérifications | Pint : passé. Suite complète : **77 passed (252 assertions)**. `migrate:fresh --seed` : passe de bout en bout (vérifié en SQLite, voir §6 pour le détail de l'environnement MySQL cassé, hors périmètre). |
| 7 | Documentation | Ce rapport + mise à jour de `docs/rapport/README.md`. |

## 3. Rôle des fichiers (obligatoire)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|--------------|
| `tests/Feature/ClientDriverScopingTest.php` | **Nouveau** — couverture complète de l'isolation client/livreur | 19 tests : double inscription même e-mail chez 2 livreurs, doublon rejeté chez le même livreur, doublon rejeté entre deux comptes livreurs (piège `driver_key = COALESCE(driver_id, 0)`), login croisé refusé, rôle/`driver_id` forcés côté serveur (payload ignoré), `/me` avec/sans contexte livreur, filtrage `GET /delivery-requests`, 403 sur `POST /drivers/{slug}/delivery-requests` avec le mauvais slug, isolation chat/avis/brouillons IA/preuves/incidents entre clients de livreurs différents |
| `tests/Feature/DeliveryRequestFlowTest.php` | Test existant (bout-en-bout du flux RG06) | Corrigé : le client est désormais créé via `User::factory()->clientOf($driver)->create()` sur le même livreur que celui du slug utilisé dans `POST /api/drivers/{slug}/delivery-requests`, sinon la nouvelle règle de scoping renvoie 403 au lieu de 201/422 |
| `tests/Feature/DeliveryRequestNotificationJobTest.php` | Test existant (job de notification à la création) | Même correction : `clientOf($driver)` sur les 3 tests qui postent sur `/drivers/notification-driver-slug/delivery-requests` |
| `database/seeders/UserSeeder.php` | Comptes de démo | Le driver démo est créé en premier ; le client démo (`client@example.com`) est `firstOrCreate` avec `driver_id` du driver démo dans la clause de recherche, pour qu'un rejeu de la migration ne recrée pas de doublon orphelin |
| `database/seeders/DeliveryRequestsSeeder.php` | Génération de données de démo pour les demandes de livraison | Restructuré : construit d'abord les livreurs, puis leurs clients propres (`clientOf`), puis tire des **paires cohérentes** (client, son livreur) pour chaque demande créée, au lieu de recycler deux collections indépendantes qui pouvaient produire des demandes incohérentes (`client.driver_id != driver_id`) |
| `docs/rapport/rapport_client_scope_driver.md` | Ce rapport | — |
| `docs/rapport/README.md` | Index des rapports | Nouvelle ligne ajoutée |

**Fichiers audités mais non modifiés** (déjà conformes ou hors périmètre de correction) :
- `tests/Feature/AiRequestDraftAnalysisTest.php`, `ChatMessageNotificationJobTest.php`, `DashboardTest.php`, `DeliveryProofUploadTest.php`, `DeliveryRequestSecurityFlowTest.php`, `ExpireConfirmationCodeJobTest.php`, `ReverbBroadcastTest.php`, `StatusChangedNotificationJobTest.php`, `TrackingTest.php` : utilisent déjà `->client()`/`->driver()` explicitement, sans dépendance à un slug de livreur précis — inchangés.
- `database/seeders/AiRequestDraftsSeeder.php`, `ReviewsSeeder.php`, `ChatMessagesSeeder.php`, `DeliveryProofsSeeder.php`, `IncidentsSeeder.php`, `NotificationSeeder.php`, `RequestStatusHistoriesSeeder.php` : utilisent `->client()` (qui crée désormais automatiquement un livreur parent) ou `User::all()`/`User::factory()->create()` en repli générique (jamais déclenché en pratique dans l'enchaînement `DatabaseSeeder`, les tables ne sont jamais vides à ce stade) — laissés tels quels, changement minimal.

## 4. Détail technique

**Contrat testé (figé par le lead, implémenté par `backend-specialist`) :**
- `POST /drivers/{slug}/register` / `POST /drivers/{slug}/login` : seules portes d'entrée client, rôle et `driver_id` déduits du slug, jamais du corps de la requête.
- `POST /register` / `POST /login` : réservés aux livreurs. `RegisterRequest` ne valide même plus de champ `role` — un `role` envoyé dans le payload est silencieusement ignoré, le contrôleur force `role = driver` et `driver_id = null`. Un test initial attendait un 422 sur ce cas ; corrigé après lecture du code réel pour attendre 201 + rôle forcé (le contrat dit « rôle forcé côté serveur », pas « payload rejeté »).
- `GET /me` : la réponse a la forme `{data: {user: {...}, driver: {...}|null}}` — `driver` est une clé **sœur** de `user`, pas imbriquée dedans. Un premier jet de test s'était trompé sur ce point (`data.user.driver` au lieu de `data.driver`) ; corrigé après inspection de `AuthController::me()`.
- `GET /delivery-requests` pour un client : filtré sur `client_id` et implicitement sur `driver_id` (puisque `client_id` détermine déjà le livreur).
- `POST /drivers/{slug}/delivery-requests` : 403 si le slug ne correspond pas au livreur du client authentifié — couvert par un test dédié et par la correction des 2 fichiers existants qui posaient ce piège sans le savoir.

**Piège des NULL dans l'index unique `(email, driver_key)` :**
Deux comptes **livreurs** (`driver_id` NULL) ne doivent pas pouvoir partager le même e-mail, alors que NULL n'est normalement jamais égal à NULL dans une contrainte unique SQL classique. La colonne générée `driver_key = COALESCE(driver_id, 0)` neutralise ce piège en ramenant tous les NULL à la même valeur `0`, donc l'unique `(email, driver_key)` s'applique bien entre deux livreurs. Testé explicitement (`rejects a duplicate email for two drivers created without a client`).

**Cohérence des données de seed :**
`DeliveryRequestsSeeder` recyclait auparavant `$clients` et `$drivers` comme deux pools indépendants pour `DeliveryRequest::factory()->recycle([$clients, $drivers, ...])`. Avec le rattachement client → livreur, ce tirage indépendant pouvait produire une demande où `client_id` pointe vers un client du livreur A alors que `driver_id` pointe vers le livreur B — incohérent avec la règle métier, même si aucune contrainte SQL ne l'empêche. Corrigé en construisant des paires `(client, son livreur)` avant de créer les demandes.

## 5. Couverture de tests (nouveau fichier `ClientDriverScopingTest.php`)

| Test | Vérifie |
|------|---------|
| `allows the same email to register with two different drivers` | 2 comptes distincts, même e-mail, `driver_id` différents |
| `rejects a duplicate email for the same driver` | 422 sur doublon chez le même livreur |
| `rejects a duplicate email for two drivers created without a client (driver_key = 0 fallback)` | piège NULL / `driver_key` |
| `prevents a client of driver A from logging in through driver B's login route` | login croisé refusé (401), login légitime accepté |
| `forces the client role on driver-scoped registration regardless of the payload` | rôle forcé malgré `role: driver` dans le payload |
| `ignores an arbitrary driver_id supplied in the registration payload` | `driver_id` du payload ignoré, celui du slug prévaut |
| `ignores a client role sent to the driver-only registration endpoint` | `/register` force toujours `driver` |
| `always creates a driver via the top-level registration route, even without a role` | comportement par défaut de `/register` |
| `exposes the driver context on /me for a client` / `returns a null driver context on /me for a driver` | contrat `GET /me` |
| `only returns delivery requests scoped to the client's own driver` | filtrage `GET /delivery-requests` |
| `forbids a client from creating a delivery request through another driver's slug` / `allows ... through their own driver's slug` | 403 vs 201 sur `POST /drivers/{slug}/delivery-requests` |
| `prevents a client from viewing chat messages of another driver's delivery request` | isolation chat |
| `prevents a client from reviewing a delivery request of another driver` | isolation avis |
| `prevents a client from referencing an AI draft owned by a client of another driver` | isolation brouillons IA (422 `ai_request_draft_id`) |
| `prevents a client from viewing delivery proofs of another driver's delivery request` | isolation preuves |
| `prevents a client from raising an incident on another driver's delivery request` | isolation incidents |

## 6. Vérifications

- Pint : **passed** (`vendor/bin/pint --dirty --format agent`, exécuté via `docker exec backend-laravel.test-1`)
- Tests : **77 passed (252 assertions)** — `php artisan test --compact` (exécuté dans le conteneur Sail `backend-laravel.test-1`, `php` n'étant pas disponible dans le shell hôte). Le fichier `phpunit.xml` utilise SQLite en mémoire, donc ce résultat est indépendant du problème d'infra MySQL décrit ci-dessous.
- `migrate:fresh --seed` :
  - **Échec initial via `.env` par défaut** (`DB_HOST=db`) : `getaddrinfo for db failed` — le hostname réseau Docker réel du service MySQL est `mysql` (confirmé via `getent hosts` dans le conteneur), pas `db`. En forçant `DB_HOST=mysql`, nouvel échec : `Access denied for user 'allo'@'172.19.0.5'` alors que les identifiants `.env` correspondent bien aux variables `MYSQL_USER`/`MYSQL_PASSWORD` du conteneur MySQL — probablement des privilèges accordés à une IP de conteneur différente d'une exécution précédente. **Ce problème est un défaut d'environnement Docker/`.env`, hors de mon périmètre (`.env` et `docker-compose` ne sont pas dans `tests/`, `database/seeders/` ou `docs/rapport/`)** ; signalé au lead pour arbitrage plutôt que corrigé directement.
  - **Vérifié en contournant l'infra cassée** : exécuté avec `DB_CONNECTION=sqlite` / `DB_DATABASE=/tmp/scoping_test.sqlite` en variables d'environnement ponctuelles (sans toucher au `.env` partagé) → **passe de bout en bout**, les 14 seeders s'exécutent sans erreur.
  - Contrôle post-seed (`tinker`) : 32 utilisateurs (21 livreurs, 11 clients), **0 client avec `driver_id` NULL**, **0 demande de livraison avec `client.driver_id != driver_id`** — confirme que la restructuration de `DeliveryRequestsSeeder` produit des données cohérentes.

## 7. Références / suite logique

- Travail mené en parallèle par `backend-specialist` sur `app/Http/Controllers/Api/AuthController.php`, `ClientRegisterRequest`, `RegisterRequest`, `app/Models/User.php`, `database/migrations/2026_08_08_000000_add_driver_id_to_users_table.php`, `database/factories/UserFactory.php` (états `client()`, `clientOf()`, `driver()`).
- Rapports liés : `rapport_ar41_durcissement_flux.md` (contrôle d'appartenance `ai_request_draft_id`, base réutilisée ici), `rapport_correction_delivery.md` (policies d'isolation client/driver déjà en place et réutilisées telles quelles pour les tests d'isolation des ressources dérivées).
- **À signaler au lead pour arbitrage** : la configuration `.env` (`DB_HOST=db`) et/ou les privilèges MySQL du conteneur `backend-mysql-1` ne permettent pas d'exécuter `migrate:fresh --seed` avec la config par défaut ; à corriger côté infra (hors de mon périmètre de fichiers).
- Suite possible : étendre la couverture aux flux de récupération de mot de passe si celui-ci est un jour implémenté (actuellement différé, cf. `docs/rapport/README.md`), en tenant compte du scoping livreur.
