# Rapport — AR-30 : Jobs queue asynchrones + uploads des preuves

**Date :** 5 août 2026
**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** Cahier des charges — F13 (notifications), F14 (preuves/code), §9.2 (workflow IA), RG06
**Statut :** Terminé (Jira AR-30) — branche `feature/Queue-Jobs`, poussée

---

## 1. Contexte

La création d'une demande déclenchait une notification **synchrone** dans la requête HTTP, et le code de
confirmation n'avait aucune expiration automatique (pas de scheduler dans le projet). L'utilisateur a
demandé une infrastructure de **jobs asynchrones** (queue `database`), sans scheduler ni e-mail.

## 2. Tableau récapitulatif des actions

| # | Action | Fichiers | Résultat |
|---|--------|----------|----------|
| 1 | Job notification création de demande | `CreateDeliveryRequestNotificationJob` | Notification interne `delivery_request_created` en arrière-plan |
| 2 | Job notification changement de statut | `CreateStatusChangedNotificationJob` | Notifie l'autre partie (client↔driver), libellés FR des 9 statuts, ignoré si `changedBy` null |
| 3 | Job notification message chat | `CreateChatMessageNotificationJob` | Notifie l'autre partie, corps limité à 120 caractères |
| 4 | Job expiration du code | `ExpireConfirmationCodeJob` | Expire `confirmation_code_expires_at` (delay 30 min via `generateCode()`) |
| 5 | Job purge GPS | `PruneGpsLocationsJob` | Supprime les positions GPS > 7 jours (non dispatché : GPS hors périmètre) |
| 6 | Hooks | `DeliveryRequest::transitionTo()`, `DeliveryRequestController::storeForDriver()/generateCode()`, `ChatMessageController::store()` | `dispatch(...)->afterCommit()` |
| 7 | Canal de log dédié | `config/logging.php` | Canal `jobs` (daily, `storage/logs/jobs.log`, `LOG_JOBS_LEVEL`, `LOG_JOBS_DAYS=14`) + `failed(Throwable)` sur chaque job |
| 8 | Worker queue | `docker-compose.yml` | Service `queue` (`allo_queue`, `queue:work database --tries=3 --sleep=3 --timeout=60`) |
| 9 | Uploads réels des preuves | `Store/UpdateDeliveryProofRequest`, `DeliveryProofController`, `DeliveryProofResource` | Fichier image (jpg/jpeg/png/webp ≤ 2 Mo) → disque `public` dossier `proofs/`, `file_url`, suppression de l'ancien fichier à l'update |
| 10 | Dockerfile PHP | `docker/php/Dockerfile` | GD recompilé avec JPEG (libjpeg62-turbo-dev, libfreetype6-dev, `--with-jpeg --with-freetype`) — `imagejpeg()` manquait |
| 11 | `storage:link` | — | Symlink `public/storage` pour exposer les fichiers |

## 3. Détail des jobs

Chaque job : `tries = 3`, `timeout = 30`, `backoff = [10, 60]`, `failed(Throwable)` → `Log::channel('jobs')`.

### 3.1 Exemple de hook (expiration du code)

```php
ExpireConfirmationCodeJob::dispatch($deliveryRequest)->delay(now()->addMinutes(30))->afterCommit();
```

## 4. Tests

- `DeliveryRequestNotificationJobTest` (3), `StatusChangedNotificationJobTest` (4),
  `ChatMessageNotificationJobTest` (4), `ExpireConfirmationCodeJobTest` (4), `PruneGpsLocationsJobTest` (2).
- `DeliveryProofUploadTest` (5) + `DeliveryRequestSecurityFlowTest` adapté (uploads `UploadedFile::fake()`,
  `Queue::fake()` pour le job d'expiration dans le test RG06).

## 5. Vérifications

- Pint propre ; **37 tests passed (84 assertions)** à la livraison.
- E2E manuel : job dispatché → traité par `allo_queue` → notification en base.
- Commits `57536c7` (jobs), `62a273b` (uploads), `ec74c25` (tests) + `d76d756` (hygiène) — push OK.
- Décision utilisateur : **pas de scheduler, pas de mail, pas de job IA** (Grok appelé directement le moment venu).

## 6. Références

- Branche : `feature/Queue-Jobs` → PR : https://github.com/Rayantoufella/Allo_Delevry/pull/new/feature/Queue-Jobs
- Suite logique : AR-39 (dashboard) puis AR-37 (ticket PDF).
