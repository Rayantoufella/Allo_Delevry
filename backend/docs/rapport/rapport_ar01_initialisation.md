# Rapport — AR-01 : Initialisation du projet

**Date :** 22 juillet 2026 — semaine 1
**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** Cahier des charges — initialisation, MLD, CRUD
**Statut :** Terminé (Jira AR-01)

---

## 1. Contexte

Mise en place de la base du projet **Allo Delivery** : une API Laravel (backend) consommée par une SPA Vue.js (frontend), le tout orchestré par Docker.Compose. Cette tâche pose l'empilement technique (stacks Docker), le modèle de données (19 migrations), la structure des ressources API (CRUD génériques) et l'outillage de test (Pest).

## 2. Tableau récapitulatif des actions

| # | Action | Résultat |
|---|--------|----------|
| 1 | Squellette Laravel 13 (PHP 8.4) | `laravel/framework` v13 installé, structure `app/`, `routes/`, `config/`, `database/` |
| 2 | Docker Compose | Services `app` (PHP-FPM), `nginx` (port 8000), `db` (MySQL 8), `phpmyadmin` (8081), puis `frontend` (Vue, 5173), puis `queue` et `reverb` ajoutés plus tard (AR-30, AR-04) |
| 3 | MLD (modèle logique de données) | 19 migrations : users + role/phone, personal_access_tokens, driver_profiles, services, delivery_zones, ai_request_drafts, delivery_requests, chat_messages, request_status_histories, reviews, notifications, incidents, delivery_proofs, gps_locations, payment_transactions, cache, jobs |
| 4 | CRUD génériques | Pour chaque ressource : Controller (`apiResource`), 2 FormRequests (Store/Update), Resource, Policy — ~15 ressources |
| 5 | Pest configuré | `tests/Pest.php` (RefreshDatabase sur Feature), 13 fichiers de tests fonctionnels (détaillés par feature dans `docs/guide/`) |
| 6 | Convention de réponse | Trait `app/Traits/ApiResponse.php` : `success()`/`error()`/`paginated()` — **formalisé ensuite en AR-03** |

## 3. Détail

### 3.1 Arborescence cible

```
backend/       → API Laravel (ce dossier)
frontend/      → SPA Vue.js (séparée, pas de Blade)
docker/        → Dockerfile PHP + conf nginx
docker-compose.yml → orchestration des 7 services
```

### 3.2 Choix structurants

- **API 100 % JSON token-based** (Sanctum — posé en AR-02) : aucun rendu Blade côté backend.
- **Rôles par colonne** `role` (`client`/`driver`) sur `users` — le gérant Spatie a été évalué puis retiré (AR-02).
- **Réponses uniformes** `{success, message, data}` via le trait `ApiResponse` — formalisé au AR-03.
- **Pest + RefreshDatabase** : chaque test part d'un base mémoire vierge (sqlite :memory: dans phpunit.xml).

## 4. Fichiers et rôles — le socle (voir le détail de chaque fichier par guide)

| Fichier | Rôle | Points clés |
|---------|------|-------------|
| `docker-compose.yml` (racine) | 7 services | `docker exec allo_backend ...` pour la plupart des commandes artisan |
| `bootstrap/app.php` | Noyau | alias `role`, JSON pour `api/*`, 401 invités (versions) |
| `routes/api.php` | Toutes les routes API | voir guide 00 |
| `database/migrations/*` (19) | Schéma complet | le lecteur se référera au « ha » du guide 00 pour la liste |
| `app/Traits/ApiResponse.php` | Réponse uniforme | AR-03 |
| `tests/Pest.php` / `TestCase.php` | Harness de test | RefreshDatabase |

## 5. Vérifications

- `docker-compose config` OK ; `docker-compose up -d` (services app/nginx/db/phpmyadmin) OK.
- Suite de base : plusieurs tests CRUD verts avant l'implémentation des features.
- Pint : passerelle sur le code initial (corrections incluses dans AR-05).

## 6. Références / suite logique

- Branches : `main` (initialisation) → `feature/5.2-auth-permissions` (AR-02) puis les epics suivants.
- Guides : `docs/guide/00_vue_ensemble.md` pour la carte du socle.