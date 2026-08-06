# Guide — 07 : Sécurité et permissions

**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** cahier des charges §6 (matrice d'autorisation), RG06 (confirmation par code), rapport AR-05 (16 corrections)

## Vue d'ensemble

La sécurité repose sur 4 piliers : (1) **Sanctum** pour l'authentification par token, (2) la **matrice de rôles** (`client` / `driver` + middleware `EnsureUserHasRole`), (3) un **jeu complet de Policies** appliqué à chaque ressource via `$this->authorize()`, et (4) des **règles métier** (machine à états de livraison, code de confirmation, rate limiting). La passe **AR-05** a corrigé 16 vulnérabilités (sécurité B1-B11 + intégrité RG06 + corrections d'infrastructure) — ce guide inventorie tous les garde-fous et les fichiers qui les portent.

## Fichiers et rôles (exhaustif)

### Infrastructure de sécurité

| Fichier | Rôle | Points clés |
|---------|------|-------------|
| `app/Http/Middleware/EnsureUserHasRole.php` | Garde par rôle (alias `role:driver`) | 403 JSON si `$user->role !== $role` ; **comparaison de chaîne, pas de hiérarchie** (le paquet Spatie a été retiré, correction #16) |
| `bootstrap/app.php` | Noyau | alias `role` ; `redirectGuestsTo(fn () => abort(401))` → l'invité reçoit un **401 JSON propre** (correction #9) ; `shouldRenderJsonWhen(api/*)` |
| `app/Http/Controllers/Controller.php` | Contrôleur de base | `use AuthorizesRequests` → rend `$this->authorize()` opérationnel (correction #7, sinon toutes les policies répondaient 500) |
| `app/Traits/ApiResponse.php` | Format d'erreur uniforme | `error()` → `{success: false, message, errors}` avec le bon statut HTTP |
| `app/Models/User.php` | Masquage des champs sensibles | `#[Hidden(['password','remember_token'])]` — jamais sérialisés ; helpers `isClient()/isDriver()` |
| `routes/api.php` | Rate limiting | `throttle:5,1` sur register/login (correction #14) ; `throttle:60,1` sur tracking, profils publics, QR |
| `config/sanctum.php` | Tokens | `expiration => null` : **tokens sans expiration** (révocation manuelle via logout) — à surveiller en production |
| `tests/Feature/DeliveryRequestSecurityFlowTest.php` | Tests de sécurité du flux | Transitions illégales → 403/409, confirmation RG06 par sous-test, 401/403 invités |
| `tests/Feature/TrackingTest.php` | Tests d'absence de fuite | Réponse `data` sans `private_token` ni `confirmation_code_hash` ; 404 pour jeton non reconnu |

### Toutes les Policies (dénégation par défaut)

| Fichier | Règles |
|---------|--------|
| `app/Policies/DeliveryRequestPolicy.php` | La plus importante : participants client/livreur pour view/update ; rôles métier par action (client crée, livreur prend en charge) — détail guide 02 |
| `app/Policies/UserPolicy.php` | Seul l'utilisateur lui-même (view/update/delete) |
| `app/Policies/DeliveryZonePolicy.php` | Livreur propriétaire (`user_id`) uniquement |
| `app/Policies/ServicePolicy.php` | Livreur propriétaire uniquement |
| `app/Policies/DriverProfilePolicy.php` | Livreur propriétaire uniquement (view public = autre règle) |
| `app/Policies/ChatMessagePolicy.php` | view/create : participant de la discussion ; **update/delete : expéditeur uniquement** (correction B5) |
| `app/Policies/DeliveryProofPolicy.php` | create : participant de la demande ; `uploaded_by` est forcé côté serveur (correction B4) |
| `app/Policies/RequestStatusHistoryPolicy.php` | view : participants ; la table est en **lecture seule** (routes `only index/show`, POST → 405 — correction B3) |
| `app/Policies/GpsLocationPolicy.php` | **create : livreur uniquement** ; view/update/delete : participants (correction B8 : un client ne peut pas injecter de position) |
| `app/Policies/IncidentPolicy.php` | Gestion par le déclarant (correction B9) |
| `app/Policies/ReviewPolicy.php` | Un avis par client et par demande, uniquement après livraison (correction B11) |
| `app/Policies/NotificationPolicy.php` | Propriétaire de la notification uniquement |
| `app/Policies/AiRequestDraftPolicy.php` | Propriétaire du brouillon |
| `app/Policies/PaymentTransactionPolicy.php` | Participants de la demande |

## Actions passées (rapports liés)

- **AR-05** — `docs/rapport/rapport_correction_delivery.md` : cause racine, tableau des 16 corrections (B1-B11 + RG06), avant/maintenant/cause, vérifications.
- **AR-02** — `docs/rapport/rapport_ar02_authentification.md` : choix Sanctum vs Spatie, matrice de rôles.

## Pièges et points d'attention

- **Deux pièces sont indispensables** : `AuthorizesRequests` sur le contrôleur de base et `HasApiTokens` sur `User`. Sans elles, politiques et tokens renvoient 500 (correctives #6/#7).
- **Toute policy sans méthode = deny by default** : les policies sont confidentielles, une méthode oubliée bloque la route.
- **Le rôle est figé à l'inscription** — aucune route ne permet de changer de rôle (guide 01).
- **Gère la diffusion du code de confirmation** : `confirmation_code_hash` (hash, jamais en clair) + `ExpireConfirmationCodeJob` avec `->delay(30 min)` (pas de cron dans le projet, décision utilisateur).
- **Rate limiting vérifié empiriquement** : la 61e requête/min sur /tracking renvoie 429.