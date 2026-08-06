# Rapport — AR-04 : Configuration Reverb et temps réel (F12)

**Date :** 25 juillet 2026 — mise à jour le 6 août 2026 (diffusion F12)
**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** Cahier des charges — F12 (chat et temps réel, P1), Laravel Reverb
**Statut :** Terminé — configuration (AR-04) + diffusion câblée sur la branche `feature/Reverb`

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

## 5. Mise à jour — 6 août 2026 : diffusion temps réel câblée (F12)

La branche `feature/Reverb` implémente la diffusion effective :

| # | Action | Fichier |
|---|--------|---------|
| 1 | Canal privé `conversation.{id}` (participants client/livreur, garde `sanctum`) | `routes/channels.php` |
| 2 | Événement `ChatMessageReceived` (payload : message, expéditeur, date) | `app/Events/ChatMessageReceived.php` |
| 3 | Événement `DeliveryRequestStatusUpdated` (payload : statut, commentaire, auteur) | `app/Events/DeliveryRequestStatusUpdated.php` |
| 4 | Hook diffusion à la création d'un message | `ChatMessageController::store()` |
| 5 | Hook diffusion dans la machine à états | `DeliveryRequest::transitionTo()` |
| 6 | Service Docker `reverb` (port 8080, `reverb:start`) | `docker-compose.yml` |
| 7 | Tests de diffusion (Event::fake, canal `private-conversation.{id}`) | `tests/Feature/ReverbBroadcastTest.php` |

Décisions : les deux événements `ShouldBroadcast` passent par la queue (`database`, worker `allo_queue`) ;
les tests tournent avec `BROADCAST_CONNECTION=null` (aucun effet de bord).

## 6. Références

- Branche : `feature/5.3-reverb-config` (config) → `feature/Reverb` (diffusion F12).
- Suite logique : AR-05 (sécurité) puis AR-30 (queues) — le temps réel étant repoussé au moment du frontend.
