# Rapports — Allo Delivery (backend)

Rapport de chaque tâche réalisée sur le projet, pour la révision complète du projet.

## Index des rapports

| # | Tâche | Rapport | Statut |
|---|-------|---------|--------|
| AR-01 | Initialisation du projet (Laravel API + Vue.js + Docker, MLD, CRUD) | [rapport_ar01_initialisation.md](rapport_ar01_initialisation.md) | Terminé |
| AR-02 | Authentification et permissions (Sanctum, rôles) | [rapport_ar02_authentification.md](rapport_ar02_authentification.md) | Terminé |
| AR-03 | Réponses API uniformes (trait ApiResponse, Pint, CI) | [rapport_ar03_api_response.md](rapport_ar03_api_response.md) | Terminé |
| AR-04 | Configuration Reverb (temps réel) | [rapport_ar04_reverb_config.md](rapport_ar04_reverb_config.md) | Terminé |
| AR-05 | Correction de sécurité (16 corrections B1–B11, RG06) | [rapport_correction_delivery.md](rapport_correction_delivery.md) | Terminé |
| AR-30 | Jobs queue asynchrones + uploads des preuves | [rapport_ar30_queues_uploads.md](rapport_ar30_queues_uploads.md) | Terminé |
| AR-39 | Tableau de bord livreur (indicateurs, CA, missions) | [rapport_ar39_dashboard.md](rapport_ar39_dashboard.md) | Terminé |
| AR-37 | Ticket PDF d'une demande de livraison | [rapport_ar37_ticket_pdf.md](rapport_ar37_ticket_pdf.md) | Terminé |

## Tâches déjà présentes sur `main` / couvertes (vérifiées, marquées Terminé dans Jira)

- AR-31 (récupération mot de passe — décalé : dépend de l'e-mail) — **différé** (décision utilisateur)
- AR-33 (page publique + QR code), AR-34 (prix), AR-36 (historique/statuts) — sur `main`
- AR-40 (tracking privé), AR-32 (page publique) — sur `main`/branche
- AR-35 (prix) — sur la branche `feature/DashbordDriver`
- AR-38 (code + preuves) — sur la branche `feature/DashbordDriver`

## Suivi du projet

- Conventions d'équipe : `.opencode/team-notes.md`
- Branches de travail : `feature/Queue-Jobs` (AR-30) → `feature/DashbordDriver` (AR-39, AR-37)
- PR : https://github.com/Rayantoufella/Allo_Delevry/pull/new/feature/DashbordDriver
