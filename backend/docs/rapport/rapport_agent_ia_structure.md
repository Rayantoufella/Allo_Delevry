# Rapport — Agent IA restructuré (app/Services) + worker queue opérationnel

**Date :** 08/08/2026 · **Branche :** worktree local (à merger par l'utilisateur)

## Contexte

L'utilisateur a signalé « le traitement de l'IA n'a rien dans le backend ». Diagnostic complet :

1. **Le code IA existait et fonctionnait** : `AiRequestAnalyzer` (appel réel OpenRouter), job, endpoint — prouvé par un appel réel au fournisseur (JSON structuré correct).
2. **Cause 1 — clé périmée** : le seul essai réel (07/08 12:08) a renvoyé `HTTP 401 User not found` (ancienne clé). La clé actuelle `.env` (`sk-or-v1-629…c8a`) est **valide** (vérifié : `GET /api/v1/auth/key` → 200, tier gratuit).
3. **Cause 2 — worker queue absent du stack actif** : le stack réellement lancé est **Sail** (`backend/compose.yaml`, conteneurs `backend-laravel.test-1`…) qui n'avait **pas de service queue** → un draft posté restait `pending` à vie (ex : draft #18). C'est la vraie raison du « l'IA ne fait rien » côté utilisateur.
4. Les 20 drafts en base sont des **fakes du seeder** (messages lorem ipsum) — aucun vrai essai utilisateur.

## Fichier | Rôle | Points clés

| Fichier | Rôle | Points clés |
|---|---|---|
| `app/Services/AiRequestAnalyzer.php` | Service d'analyse IA restructuré | `analyze()` = orchestrateur ; méthodes privées dédiées : `ensureApiKeyIsConfigured()`, `requestRawContent()` (appel HTTP + vérifs), `buildPayload()`, `systemPrompt()`, `buildUserMessage()`, `decodeRawContent()`, `normalizeResult()` (RG11). Contrat 9 champs inchangé, messages d'erreur/log identiques, zéro accès DB. |
| `app/Jobs/AnalyzeAiRequestDraftJob.php` | Job queue allégé | `handle()` court ; helpers privés : `activeServices()` (catalogue actif une seule requête), `validateResult()` (échec → draft `failed`), `matchServiceId()` (exact puis insensible à la casse). `tries=3`, `timeout=60`, `backoff [10,60]`, `failed()` inchangés. |
| `backend/compose.yaml` | **Stack Docker actif (Sail)** | Ajout du service `queue` (image `sail-8.4/app`, commande `php artisan queue:work database --tries=3 --sleep=3 --timeout=60`, `restart: unless-stopped`, réseau `sail`). Conteneur `backend-queue-1` démarré. |

## Preuves

- Appel réel OpenRouter via `AiRequestAnalyzer` : `recipient_name=Sara`, `amount_to_collect=420`, `service=Envoi de colis` ✅
- Pipeline queue : draft #18 `pending` → job poussé → worker → `done` (16 s) ✅
- Tests : `--filter=AiRequestDraftAnalysis` **9 passed (37 assertions)** ; suite complète **77 passed (252 assertions)** ✅
- Pint `--dirty` : **passed** ✅

## Correction de l'analyse de nettoyage (à ne pas supprimer)

- `backend/compose.yaml` : **À GARDER** — c'est le stack actif (les conteneurs tournent depuis ce fichier), contrairement à `docker-compose.yml` (racine) qui n'est pas lancé actuellement.
- `docker/mysql/init.sql` : toujours orphelin (non monté) — suppression possible.

## Suite proposée

- Re-tester le flux complet utilisateur : login client → page livreur → Assistant IA → analyse réelle (le worker tourne désormais en continu).
