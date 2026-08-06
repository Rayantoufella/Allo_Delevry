# Rapport — AR-04 : Configuration Reverb (temps réel)

**Date :** 25 juillet 2026 — semaine 1
**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** Cahier des charges — F12 (chat et temps réel, P1), Laravel Reverb
**Statut :** Terminé (Jira AR-04) — configuration seule, diffusion non encore câblée

---

## 1. Contexte

Le cahier des charges prévoit des échanges via un canal privé et la diffusion des événements avec
Reverb / WebSocket (P1, avec fallback sans temps réel autorisé). Cette tâche installe et configure
Reverb ; la diffusion effective des événements métier sera branchée quand le frontend Vue.js sera connecté.

## 2. Tableau récapitulatif des actions

| # | Action | Résultat |
|---|--------|----------|
| 1 | Installation | `ece8bb3` — `laravel/reverb` ajouté (avec barryvdh/laravel-dompdf et simplesoftwareio/simple-qrcode, préparés pour AR-37 et la page publique) |
| 2 | Configuration | `79f7b0a` — broadcasting Reverb configuré, variables `REVERB_*` dans `.env.example`, `routes/channels.php` |

## 3. Détail

- Serveur WebSocket Reverb disponible (démarré par `php artisan reverb:start`).
- Canaux privés prêts à être définis dans `routes/channels.php` (aucun canal métier encore déclaré : décision de brancher le temps réel avec le frontend).
- Pas d'événement broadcast émis par les contrôleurs à ce stade (les notifications passent par la base + jobs queue, cf. AR-30).

## 4. Vérifications

- `php artisan config:show broadcasting` → driver `reverb` ; variables d'environnement présentes dans `.env.example`.
- Suite complète de tests verte (aucun impact).

## 5. Références

- Branche : `feature/5.3-reverb-config`.
- Suite logique : AR-05 (sécurité) puis AR-30 (queues) — le temps réel étant repoussé au moment du frontend.
