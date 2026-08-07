# Rapports — Allo Delivery (backend)

Rapport de chaque tâche réalisée sur le projet, pour la révision complète du projet.
**Modèle à suivre pour toute nouvelle action/feature :** `TEMPLATE.md` — chaque rapport doit lister le **rôle de chaque fichier touché** (table `Fichier | Rôle | Points clés`, exhaustif).

## Index des rapports

| # | Tâche | Rapport | Statut |
|---|-------|---------|--------|
| AR-01 | Initialisation du projet (Laravel API + Vue.js + Docker, MLD, CRUD) | [rapport_ar01_initialisation.md](rapport_ar01_initialisation.md) | Terminé |
| AR-02 | Authentification et permissions (Sanctum, rôles) | [rapport_ar02_authentification.md](rapport_ar02_authentification.md) | Terminé |
| AR-03 | Réponses API uniformes (trait ApiResponse, Pint, CI) | [rapport_ar03_api_response.md](rapport_ar03_api_response.md) | Terminé |
| AR-04 | Configuration Reverb + **diffusion temps réel F12** (canaux, événements, tests) | [rapport_ar04_reverb_config.md](rapport_ar04_reverb_config.md) | Terminé |
| AR-05 | Correction de sécurité (16 corrections B1–B11, RG06) | [rapport_correction_delivery.md](rapport_correction_delivery.md) | Terminé |
| AR-30 | Jobs queue asynchrones + uploads des preuves | [rapport_ar30_queues_uploads.md](rapport_ar30_queues_uploads.md) | Terminé |
| AR-37 | Récupération, ticket (preuve) et **tracking privé (F11)** | [rapport_ar37_recuperation_tracking.md](rapport_ar37_recuperation_tracking.md) | Terminé |
| AR-39 | Tableau de bord livreur (indicateurs, CA, missions) | [rapport_ar39_dashboard.md](rapport_ar39_dashboard.md) | Terminé |
| AR-41 | Durcissement flux de livraison (appartenance `ai_request_draft_id`, test bout-en-bout) | [rapport_ar41_durcissement_flux.md](rapport_ar41_durcissement_flux.md) | Terminé |
| F08 | Préremplissage IA des demandes (Grok/xAI : service, job, endpoint, RG11) | [rapport_f08_prefill_ia.md](rapport_f08_prefill_ia.md) | Terminé* |

## Guide du code par feature (`docs/guide/`)

Pour comprendre le projet à 100 % : **un fichier par feature**, avec **le rôle de chaque fichier** (table `Fichier | Rôle | Points clés`).

| Guide | Contenu |
|-------|---------|
| [00_vue_ensemble.md](../guide/00_vue_ensemble.md) | Architecture en couches, Docker (7 services), configs, routes, 19 migrations |
| [01_authentification_roles.md](../guide/01_authentification_roles.md) | F01 — Sanctum, rôles, register/login/me/logout, EnsureUserHasRole |
| [02_demandes_livraison.md](../guide/02_demandes_livraison.md) | Demandes, machine à états (transitionTo), zones, services, avis, incidents, IA |
| [03_suivi_tracking.md](../guide/03_suivi_tracking.md) | F11 — tracking public/privé, PublicTrackingResource, historique, preuves |
| [04_chat_temps_reel.md](../guide/04_chat_temps_reel.md) | F12 — chat temps réel Reverb, canaux privés, événements |
| [05_notifications_jobs.md](../guide/05_notifications_jobs.md) | Notifications internes, 5 jobs queue, canal log jobs, worker |
| [06_dashboard_livreur.md](../guide/06_dashboard_livreur.md) | AR-39 — GET /api/dashboard, indicateurs, missions, CA |
| [07_securite_permissions.md](../guide/07_securite_permissions.md) | Toutes les Policies, corrections AR-05 (B1-B11), rate limiting |
| [08_uploads_preuves.md](../guide/08_uploads_preuves.md) | F14 — preuves (photo/ticket/signature), uploads multipart, disk public |
| [09_bonus_ecarts.md](../guide/09_bonus_ecarts.md) | GPS (bonus), paiements (P2), IA Grok, e-mail différé, écarts |

## Tâches déjà présentes sur `main` / branches (couvertes, marquées Terminé dans Jira)

- AR-31 (récupération mot de passe — dépend de l'e-mail) — **différé** (décision utilisateur)
- AR-33 (page publique + QR code), AR-34/AR-35 (prix), AR-36 (historique/statuts), AR-38 (code + preuves) — sur `main` / branches
- AR-40 (tracking privé), AR-32 (page publique) — sur `main` / branche

## Suivi du projet

- Conventions d'équipe : `.opencode/team-notes.md`
- Branches : `feature/Queue-Jobs` (AR-30) → `feature/DashbordDriver` (AR-39) → `feature/PDF` (AR-37, PDF annulé) → **`feature/Reverb` (F12 diffusion temps réel, poussée)** → **`feature/AR41-durcissement` (AR-41, poussée)** → **`feature/AI-Laravel` (F08 préremplissage IA)**
- PR : https://github.com/Rayantoufella/Allo_Delevry/pull/new/feature/Reverb · https://github.com/Rayantoufella/Allo_Delevry/pull/new/feature/PDF · https://github.com/Rayantoufella/Allo_Delevry/pull/new/feature/AR41-durcissement · https://github.com/Rayantoufella/Allo_Delevry/pull/new/feature/AI-Laravel