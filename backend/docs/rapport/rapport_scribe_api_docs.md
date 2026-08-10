# Rapport — Documentation API avec Scribe (knuckleswtf/scribe)

## Vue d'ensemble

Installation et configuration de Scribe v5.11.0 pour générer la documentation statique de l'API Allo Delivery.
Les endpoints sont documentés via des annotations PHPDoc (`@group`, `@authenticated`, `@urlParam`, `@bodyParam`, `@query`, `@response`).

## Fichiers modifiés/créés

| Fichier | Rôle | Points clés |
|---------|------|-------------|
| `composer.json` | Dépendance | `knuckleswtf/scribe` v5.11.0 ajouté en `require-dev` |
| `config/scribe.php` | Configuration | Title "Allo Delivery API", description FR, auth Sanctum Bearer par défaut, type `static`, exclusion routes problématiques |
| `app/Http/Controllers/AuthController.php` | Annotations | 6 méthodes : `@group Authentification`, `@unauthenticated` sur register/login |
| `app/Http/Controllers/DeliveryRequestController.php` | Annotations | 10 méthodes : `@group Demandes de livraison`, suivi public `@unauthenticated` |
| `app/Http/Controllers/DriverProfileController.php` | Annotations | 7 méthodes : `@group Profils livreurs`, page publique `@unauthenticated` |
| `app/Http/Controllers/DashboardController.php` | Annotations | 1 méthode : `@group Dashboard livreur` |
| `app/Http/Controllers/ServiceController.php` | Annotations | 5 méthodes : `@group Services` |
| `app/Http/Controllers/DeliveryZoneController.php` | Annotations | 6 méthodes : `@group Zones & tarifs` |
| `app/Http/Controllers/NotificationController.php` | Annotations | 4 méthodes : `@group Notifications` |
| `app/Http/Controllers/AiRequestDraftController.php` | Annotations | 5 méthodes : `@group Assistant IA` |
| `app/Http/Controllers/ChatMessageController.php` | Annotations | 5 méthodes : `@group Chat` |
| `app/Http/Controllers/ReviewController.php` | Annotations | 5 méthodes : `@group Avis` |
| `app/Http/Controllers/DeliveryProofController.php` | Annotations | 5 méthodes : `@group Preuves` |
| `app/Http/Controllers/IncidentController.php` | Annotations | 5 méthodes : `@group Incidents` |
| `app/Http/Controllers/GpsLocationController.php` | Annotations | 5 méthodes : `@group GPS` |
| `app/Http/Controllers/PaymentTransactionController.php` | Annotations | 5 méthodes : `@group Paiements` |
| `app/Http/Controllers/RequestStatusHistoryController.php` | Annotations | 2 méthodes : `@group Historique des statuts` |
| `public/docs/index.html` | Généré | Documentation HTML statique |
| `public/docs/collection.json` | Généré | Collection Postman v2.1.0 |
| `public/docs/openapi.yaml` | Généré | Spécification OpenAPI 3.0.3 |
| `.scribe/` | Généré | Fichiers Markdown source pour Scribe |

## Commandes

| Commande | Description |
|----------|-------------|
| `composer require knuckleswtf/scribe --dev` | Installation Scribe v5.11.0 |
| `cp vendor/knuckleswtf/scribe/config/scribe.php config/scribe.php` | Publication config (vendor:publish non disponible en v5) |
| `php artisan scribe:generate` | Génération de la documentation |

## Endpoints documentés

- **53 endpoints** traités (sur 55 routes API, 2 exclus : `drivers/{slug}/register` — slug inexistant en DB, `ai-request-drafts PUT` — champ `generated_data` JSON incompatible avec le rendu Blade de Scribe)
- **0 erreur** lors de la génération
- **16 groupes** : Authentification, Profils livreurs, Suivi public, Demandes de livraison, Zones & tarifs, Services, Notifications, Assistant IA, Chat, Avis, Preuves, Incidents, GPS, Paiements, Historique des statuts, Dashboard

## Fichiers produits

| Fichier | Taille | Description |
|---------|--------|-------------|
| `public/docs/index.html` | ~600 KB | Page HTML autonome |
| `public/docs/collection.json` | ~190 KB | Collection Postman |
| `public/docs/openapi.yaml` | ~97 KB | Spécification OpenAPI |

## Accès aux docs

- **Statique** : `public/docs/index.html` (ouvre directement dans le navigateur)
- **Via Laravel** : Non activé (type `static` choisi pour simplicité)
- **Postman** : `public/docs/collection.json`
- **OpenAPI** : `public/docs/openapi.yaml`

## Limitations et notes

1. **Scribe v5.x** (pas v4.x) : la v5.11.0 est compatible PHP 8.4 et Laravel 13. Pas de commande `scribe:install` — la config se copie manuellement depuis `vendor/knuckleswtf/scribe/config/scribe.php`.
2. **Routes exclues** :
   - `POST /api/drivers/{slug}/register` — NotFoundHttpException car aucun profil de livreur n'existe en base lors de la génération (le contrôleur fait `firstOrFail`).
   - `PUT/PATCH /api/ai-request-drafts/{id}` — Le champ `generated_data` (type `json` en validation) crée un objet `stdClass` qui ne peut pas être converti en string dans les vues Blade de Scribe.
3. **Pas de `bodyParameters()`** : Les FormRequests du projet n'implémentent pas `bodyParameters()` — Scribe extrait les paramètres depuis `rules()`. C'est fonctionnel mais les warnings restent affichés.
4. **Pas de réponse live** : La stratégie `ResponseCalls` est désactivée (no routes) pour éviter les erreurs de DB/tests. Les `@response` en PHPDoc fournissent des exemples.
5. **84 tests Pest** : Aucun test cassé par l'ajout de Scribe (package `--dev` uniquement, annotations PHPDoc sans impact runtime).
6. **Pint** : Propre (0 fichiers modifiés dirty).

## Difficultés rencontrées

- **Vendor:publish indisponible** en Scribe v5 : la config n'est pas publishable via artisan, copie manuelle nécessaire.
- **stdClass error** : Le champ `generated_data` (JSON) dans `UpdateAiRequestDraftRequest` provoque une erreur de conversion lors du rendu Blade. Résolu par exclusion de la route dans `config/scribe.php`.
- **NotFoundHttpException** : La route `drivers/{slug}/register` échoue car Scribe tente un appel réel avec un slug aléatoire inexistant. Résolu par exclusion.
- **WARN bodyParameters** : Les FormRequests sans `bodyParameters()` génèrent des warnings — purely cosmétiques, les paramètres sont quand même extraits depuis `rules()`.
