# Rapport — AR-39 : Tableau de bord livreur (indicateurs, CA, missions)

**Date :** 6 août 2026
**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** Cahier des charges — F16 (tableau de bord, P0/P1), O8 (statistiques simples), §8.2 (revenus estimés)
**Statut :** Terminé (implémentation) — branche `feature/DashbordDriver`, commit `e316371`, push OK

---

## 1. Contexte

AR-39 : « Gérer incidents et litiges, indicateurs, chiffre d'affaires, missions et tableaux de bord du
livreur. » La partie **incidents/litiges était déjà couverte** (CRUD `incidents` complet). Il manquait
les **indicateurs / chiffre d'affaires / missions** : un endpoint de tableau de bord pour le livreur.

## 2. Tableau récapitulatif des actions

| # | Action | Fichier | Résultat |
|---|--------|---------|----------|
| 1 | Contrôleur | `app/Http/Controllers/Api/DashboardController.php` | `GET /api/dashboard` — agrégats scoped `driver_id` (RG01) |
| 2 | Resource | `app/Http/Resources/DashboardResource.php` | Formate les indicateurs (passthrough) |
| 3 | Route | `routes/api.php` | `GET /dashboard` dans le groupe `role:driver` (client → 403, RG02) |
| 4 | Tests | `tests/Feature/DashboardTest.php` | 3 tests |

## 3. Détail des indicateurs renvoyés

| Clé | Calcul |
|-----|--------|
| `total_requests` | Nombre de demandes du livreur |
| `active_missions` | Statuts non terminaux (scope `active()`) |
| `pending_requests` | Statut `en_attente` |
| `delivered_missions` | Statut `livree` |
| `estimated_revenue` | Somme `proposed_price` (confirmee, colis_recupere, en_livraison, livree) — format `"200.00"` |
| `collected_revenue` | Somme `proposed_price` (livree uniquement) |
| `average_rating` | Moyenne des notes des avis, arrondie à 0,1 (null si aucun) |
| `unread_notifications` | Notifications `read_at` null |
| `recent_requests` | 5 dernières demandes |
| `recent_messages` | 5 derniers messages du chat (id, expéditeur, contenu, date) |

## 4. Tests

- Indicateurs exacts (6 demandes : 2 livrées, 1 confirmée, 1 colis récupéré, 1 en attente, 1 refusée →
  CA estimé 200.00, encaissé 150.00, note 4, 2 notifications non lues).
- Client → **403** ; non authentifié → **401**.

## 5. Vérifications

- Pint propre ; **40 tests passed (97 assertions)** à la livraison.
- Commit `e316371` — push `origin/feature/DashbordDriver` OK.

## 6. Références

- Branche : `feature/DashbordDriver` → PR : https://github.com/Rayantoufella/Allo_Delevry/pull/new/feature/DashbordDriver
- Suite logique : AR-37 (ticket PDF).
