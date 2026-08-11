# Rapport — Suppression complète de Reverb / WebSocket

**Date :** 11 août 2026
**Périmètre :** `D:\AlloDelevry\backend` + `D:\AlloDelevry\frontend` + `docker-compose.yml` racine
**Référence :** F12 (chat et temps réel) — retrait décidé par l'utilisateur (« reverb/websocket je ne vais pas faire »)
**Statut :** Terminé (non commité)
**Branche :** —

## 1. Contexte

Reverb (WebSocket) était désactivé par env (`BROADCAST_CONNECTION=null`) et **jamais utilisé** : le frontend consomme l'API par polling (`usePolling.js`). L'utilisateur a confirmé ne pas vouloir de temps réel → **suppression complète** du paquet, des événements, des canaux, des variables d'environnement et du service Docker, sans impact sur le chat classique ni sur les statuts (déjà fonctionnels en polling).

## 2. Tableau récapitulatif des actions

| # | Action | Résultat |
|---|--------|----------|
| 1 | Suppression fichiers backend | `config/reverb.php`, `app/Events/DeliveryRequestStatusUpdated.php`, `app/Events/ChatMessageReceived.php` (dossier `app/Events/` vidé et supprimé), `routes/channels.php`, `tests/Feature/ReverbBroadcastTest.php` |
| 2 | Code PHP nettoyé | `bootstrap/app.php` (ligne `channels:` retirée), `DeliveryRequest::transitionTo()` (appel `broadcast()` supprimé, notification conservée), `ChatMessageController::store()` (appel `broadcast()` supprimé, job notification conservé) + PHPDoc Scribe |
| 3 | Paquets désinstallés | `composer remove laravel/reverb` (composer.json + lock + vendor propres, plus de commandes `reverb:*`), `npm uninstall laravel-echo pusher-js` (aucun usage dans `src/`, vérifié par grep avant) |
| 4 | Config | `config/broadcasting.php` : bloc de connexion `reverb` retiré (fichier framework conservé, défaut `null`) |
| 5 | Environnement | `backend/.env` + `.env.example` : lignes commentées `REVERB_*`/`VITE_REVERB_*` supprimées (`BROADCAST_CONNECTION=null` conservé) ; `frontend/.env` : `VITE_REVERB_*` supprimées |
| 6 | Docker | `docker-compose.yml` racine (stack `allo_*`, inactive) : service `reverb` supprimé |
| 7 | Frontend | `frontend/src/composables/usePolling.js` : commentaire reformulé (plus de mention Reverb) |
| 8 | Docs | Guides 00/02/04 réécrits, rapports AR-04/ChatIA marqués « retiré », index README à jour, ce rapport |

## 3. Table des fichiers

### Supprimés

| Fichier | Rôle (avant) | Pourquoi supprimé |
|---------|--------------|-------------------|
| `backend/config/reverb.php` | Config serveur Reverb (paquet) | Paquet retiré |
| `backend/app/Events/ChatMessageReceived.php` | Event broadcast d'un message de chat | Aucun listener local, uniquement `broadcast()` |
| `backend/app/Events/DeliveryRequestStatusUpdated.php` | Event broadcast d'un changement de statut | Idem |
| `backend/routes/channels.php` | Canal privé `conversation.{id}` | Plus de diffusion |
| `backend/tests/Feature/ReverbBroadcastTest.php` | 2 tests de diffusion (Event::fake + canal) | Fonctionnalité retirée |

### Modifiés

| Fichier | Rôle | Points clés |
|---------|------|-------------|
| `backend/bootstrap/app.php` | Noyau Laravel | Ligne `channels:` retirée de `withRouting()` |
| `backend/app/Models/DeliveryRequest.php` | Machine à états | `transitionTo()` : `broadcast()` supprimé, `CreateStatusChangedNotificationJob` conservé |
| `backend/app/Http/Controllers/Api/ChatMessageController.php` | CRUD chat | `broadcast()` supprimé, `CreateChatMessageNotificationJob` conservé ; PHPDoc « polling » |
| `backend/config/broadcasting.php` | Config broadcast | Bloc `reverb` retiré ; fichier standard Laravel conservé (défaut `null`) |
| `backend/.env` / `.env.example` | Environnement | `REVERB_*`/`VITE_REVERB_*` supprimées ; `BROADCAST_CONNECTION=null` conservé |
| `frontend/.env` | Environnement Vite | `VITE_REVERB_*` supprimées (propriétés exposées au frontend) |
| `frontend/src/composables/usePolling.js` | Polling | Commentaire actualisé |
| `docker-compose.yml` (racine) | Stack Docker `allo_*` (inactive) | Service `reverb` supprimé |
| `backend/composer.json` / `composer.lock` | Dépendances PHP | `laravel/reverb` retiré |
| `frontend/package.json` / `package-lock.json` | Dépendances npm | `laravel-echo`, `pusher-js` retirés |

### Conservés (volontairement)

| Fichier | Pourquoi |
|---------|----------|
| `backend/config/broadcasting.php` | Fichier standard du framework Laravel, défaut `null` — ne casse rien |
| `broadcast()` (helper) + `Illuminate\Broadcasting\*` | Noyau Laravel, inactifs sans driver |
| `backend/.env` : `BROADCAST_CONNECTION=null` | Défaut explicite sain |
| `backend/docs/Cahier_des_charges_Allo_Delivery_V5_1.md` | Document contractuel, jamais modifié |
| `CreateChatMessageNotificationJob` / `CreateStatusChangedNotificationJob` | Notifications internes en base — **indépendantes de Reverb** |

## 4. Détail technique

- **Aucun listener local** ne consommait les 2 événements (grep `Event::listen`/`dispatch` : zéro) → suppression sans risque.
- Le chat client/livreur et le suivi de statuts restent **fonctionnels en polling** : endpoints inchangés, notification interne inchangée.
- `php artisan optimize:clear` exécuté après `composer remove` (le cache `bootstrap/cache/packages.php` référençait encore `Laravel\Reverb\ReverbServiceProvider` → erreur `Class not found` sur `php artisan about` avant purge).
- Vérifié : `php artisan reverb:list` → « no commands defined in the "reverb" namespace ».

## 5. Vérifications

- Pint : à exécuter après ce rapport (`vendor/bin/pint --dirty`)
- Tests : suite complète attendue **90 passed** (les 2 tests Reverb supprimés)
- Grep de contrôle : `reverb|REVERB|ShouldBroadcast|chat/completions` dans `backend/app` + `frontend/src` → 0 occurrence (hors docs)
- Composer/npm : `vendor/laravel/reverb` absent, `laravel-echo`/`pusher-js` absents du lock

## 6. Références / suite logique

- Rapports liés : `rapport_ar04_reverb_config.md` (AR-04, marqué Retiré), `rapport_chat_ia_assistant.md` (section réactivation annulée)
- Guides mis à jour : `00_vue_ensemble.md`, `02_demandes_livraison.md`, `04_chat_temps_reel.md` (renommé de fait : chat polling)
- Réactivation future éventuelle = réinstallation complète (`composer require laravel/reverb` + événements + canaux) — non documentée comme prévue.
