# Rapport — AR-02 : Authentification et permissions

**Date :** 23–24 juillet 2026 — semaine 1
**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** Cahier des charges — F01 (authentification et rôles), §6 (matrice d'autorisation)
**Statut :** Terminé (Jira AR-02)

---

## 1. Contexte

L'API doit gérer l'inscription, la connexion, la déconnexion et appliquer les permissions client / driver
(matrice §6.1 du cahier des charges). Deux paquets ont été évalués : Sanctum (recommandé pour les SPA)
et Spatie Laravel Permission.

## 2. Tableau récapitulatif des actions

| # | Action | Résultat |
|---|--------|----------|
| 1 | Installation Sanctum | `7c07256` — `laravel/sanctum` installé et configuré (migration `personal_access_tokens`, traité `HasApiTokens` sur `User`) |
| 2 | Installation Spatie | `bc543a6` — `spatie/laravel-permission` installé, middleware de rôle, requests d'authentification (Login/Register) |
| 3 | Alias du middleware | `47366ea` — alias `role` enregistré dans `bootstrap/app.php` |
| 4 | Correction auth bloquante | `997e139` — `HasApiTokens` manquant → tokens impossibles ; `AuthorizesRequests` manquant dans le contrôleur de base → policies en 500 ; 401 propre au lieu de redirections (corrections #6, #7, #9 du rapport AR-05) |
| 5 | Suppression de Spatie | `0013545` — Spatie installé mais **jamais utilisé** (rôles gérés par la colonne `role` + helper `isDriver()/isClient()`) : retiré de composer + migration `permission_tables` + config |

## 3. Détail

### 3.1 Modèle de rôles retenu (après suppression de Spatie)

- Colonne `role` sur `users` (`client` | `driver`), constantes `ROLE_CLIENT` / `ROLE_DRIVER`.
- Helpers `User::isClient()` / `User::isDriver()`.
- Middleware `EnsureUserHasRole` (`role:driver`) → 403 JSON si rôle incorrect.

### 3.2 Endpoints livrés

- `POST /api/register` (nom, e-mail unique, mot de passe ≥ 8 + confirmation, téléphone, rôle imposé) → 201 + token.
- `POST /api/login` → token Sanctum.
- `POST /api/logout` (révocation du token courant).
- `GET /api/me` → utilisateur connecté.

## 4. Vérifications

- Tests fonctionnels : register (client/driver), login OK / 401, rôles — via la suite complète.
- Tous les endpoints du groupe `role:driver` renvoient 403 à un client.

## 5. Références

- Branche : `feature/5.2-auth-permissions`.
- Suite logique : AR-03 (réponses API uniformes).
