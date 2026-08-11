# Rapport — Chat IA : l'assistant complète la zone de livraison au fil de la conversation

**Date :** 10 août 2026
**Périmètre :** `D:\AlloDelevry\backend` + `D:\AlloDelevry\frontend`
**Référence :** Chat IA conversationnel (assistant livreur) — rapport `rapport_chat_ia_assistant.md`
**Statut :** Terminé
**Branche :** `feature/FrontEnd`

## 2. Contexte

L'utilisateur constate que l'assistant IA ne demande jamais la **zone de livraison** au client : quand la conversation se termine, le formulaire affiche « Complète ces champs pour envoyer la demande : Zone de livraison » et force un remplissage manuel. L'IA doit poser les questions pour obtenir TOUTES les informations (y compris la zone) et les enregistrer elle-même dans `generated_data`, sans jamais renvoyer le client vers un remplissage manuel.

## 3. Tableau récapitulatif des actions

| # | Action | Résultat |
|---|--------|----------|
| 1 | `AiRequestAnalyzer::chatReply()` + `chatSystemPrompt()` : liste des zones actives transmise au modèle chat | L'IA pose une question à la fois sur TOUS les champs requis, dont la zone (choix uniquement dans la liste fournie, avec prix) ; interdits : calculer un prix, déclarer la demande terminée, renvoyer vers le formulaire manuel |
| 2 | `extractFromConversation()` + `systemPrompt()` : clé `delivery_zone` dans le JSON d'extraction + liste des zones fournie | L'extraction retourne le nom EXACT de la zone ou null |
| 3 | `normalizeResult()` : normalisation de `delivery_zone` (match insensible casse/trim contre la liste) | 10e clé du contrat : `delivery_zone` |
| 4 | `ProcessAiChatMessageJob` : chargement des zones actives du livreur (`DeliveryZone::where user_id + is_active`) | `activeZones()` formatées `[['name' => destination_zone ?: origin_zone, 'price' => fixed_price]]`, passées aux 2 appels IA ; `delivery_zone` fusionné dans `generated_data` par le merge non-null existant |
| 5 | `AiAssistantView.vue` : mapping `generated_data.delivery_zone` → `delivery_zone_id` | Ref `pendingDeliveryZone` + `applyZoneMapping()` (match insensible casse sur `destination_zone || origin_zone`) + `watch(activeZones)` re-joue le mapping quand le profil driver arrive (chargement async) ; `resetForm()` vide le ref |
| 6 | `AiAssistantView.vue` : bandeau reformulé | « Complète ces champs pour envoyer la demande : … » → « Encore quelques informations : X. Écris-les dans la conversation, l'assistant les complète automatiquement. » |

## 4. Rôle des fichiers

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Services/AiRequestAnalyzer.php` | Prompts chat + extraction enrichis des zones ; contrat 10 clés | `chatSystemPrompt(array $activeZones = [])`, `systemPrompt(array $activeZones = [])`, `normalizeResult(..., array $activeZones = [])` — paramètres optionnels, compatibilité `analyze()` et tests conservée ; nom de zone = `destination_zone ?: origin_zone` (même logique que le frontend) |
| `app/Jobs/ProcessAiChatMessageJob.php` | Fournit les zones actives du livreur à l'IA | `activeZones()` : filtrage `is_active`, noms vides exclus, `price` en float ; fusion `delivery_zone` dans `generated_data` via `array_merge` + `array_filter` non-null existants |
| `frontend/src/views/AiAssistantView.vue` | Mapping zone extraite + bandeau conversationnel | `pendingDeliveryZone` (L119), `applyZoneMapping()` (L125), `watch(activeZones)` (L139), appel dans `applyDraft()` ; bandeau `ai-status-warn` reformulé (template ~L529) ; tolérant si `delivery_zone` absent (backend pas encore déployé) |

## 5. Détail technique

- **Contrat ajouté :** `generated_data.delivery_zone` = nom exact de la zone (ex. « centre ville »), normalisé côté backend contre les zones actives du livreur ; le frontend le résout vers `delivery_zone_id` par match insensible à la casse sur `destination_zone || origin_zone`.
- **Prompt chat :** « Zones de livraison disponibles : "houda-salam-dakhla" (14 DH), "centre ville" (26 DH) » + règle « si une adresse de livraison est donnée, demande la zone correspondante » + « si le choix n'est pas clair, liste les options ».
- **Règles strictes ajoutées :** jamais de calcul de prix, jamais « remplis le formulaire manuellement », jamais de déclaration de demande terminée tant que les infos essentielles manquent.
- **Compatibilité :** tous les nouveaux paramètres sont optionnels (`= []`) ; si le livreur n'a aucune zone, l'IA ne demande pas la zone et `delivery_zone` reste null (comportement inchangé).
- **Ordre async frontend :** le mapping est robuste quel que soit l'ordre d'arrivée (profil driver chargé en async vs extraction IA) grâce au `watch(activeZones)`.

## 5bis. Déduction déterministe de la zone (complément)

Même avec les prompts enrichis, l'extraction peut renvoyer `delivery_zone: null` (modèle peu fiable, cas limites). Pour que le client n'ait JAMAIS à choisir la zone manuellement, le job **déduit la zone depuis l'adresse de livraison** de façon déterministe (aucun appel IA) :

- **Rôle :** `ProcessAiChatMessageJob` — nouvelle méthode `deduceDeliveryZone(?string $deliveryAddress, array $activeZones): ?string` + helper `significantTokens(string $text): array`.
- **Logique de fusion (section 4b de `handle()`) :** la zone extraite par l'IA a la priorité ; sinon déduction depuis la NOUVELLE adresse de livraison (extraction d'abord, `generated_data` en secours) ; si aucune déduction, l'ancienne zone éventuelle est conservée telle quelle (le client change d'avis sans perdre son choix).
- **Stratégie de correspondance** (insensible à la casse, trim) :
  1. nom complet de la zone présent dans l'adresse, ou adresse contenue dans le nom (ex. adresse « houda » → zone « houda-salam-dakhla ») ;
  2. un **mot significatif partagé** (≥ 3 caractères) : ex. « vers houda quartier » → token « houda » de « houda-salam-dakhla » (le premier jet ne matcher que les chaînes complètes échouait sur ce cas réel).
  - En cas de multiples candidats, la zone au **nom le plus long** gagne ; retourne le nom EXACT de la zone (le frontend le mappe déjà via `applyZoneMapping()`).
- **Piège corrigé en cours de route :** la version initiale court-circuitait la déduction quand une ancienne zone existait déjà dans `generated_data` (extraction null → « centre ville » conservé au lieu de la nouvelle adresse). La fusion relit désormais l'adresse NOUVELLE en priorité.

## 6. Vérifications

- Pint : passé (`vendor/bin/pint --dirty --format agent`)
- Tests : **85 passed (289 assertions)** — suite complète `php artisan test --compact` (dont `--filter=AiChatMessage` **12 passed, 44 assertions**, avec 3 nouveaux tests : déduction depuis l'adresse, priorité à l'extraction IA, aucun match → pas de clé `delivery_zone`)
- Build frontend : inchangé (aucune modification frontend dans ce complément)
- Manuel (navigateur, live) : message « la zone de livraison c'est centre ville » envoyé dans le chat → récapitulatif **« Formulaire pré-rempli, vérifie et envoie »** (bandeau vert), carte **« centre ville » cochée** → « Frais de livraison : 26 DH — tarif fixé par le livreur », bouton « Envoyer la demande → » débloqué ; bandeau « Encore quelques informations : Zone de livraison. Écris-les dans la conversation… » confirmé sur conversation incomplète
- **Limite externe constatée :** la QA live de la déduction a été bloquée par des `HTTP 429` OpenRouter (quota de la clé gratuite épuisé à 21h30) — le draft passe `failed` avec `error_message` propre et l'UI affiche la bulle d'erreur + lien « Remplir le formulaire manuellement » ; la déduction elle-même est couverte par les tests unitaires (aucun appel IA)
- Worker : `backend-queue-1` redémarré (le code `queue:work` est chargé en mémoire — restart obligatoire après modification du job)

## 7. Références / suite logique

- Rapport lié : `rapport_chat_ia_assistant.md` (architecture du chat IA)
- Note : après modification de `app/Jobs/*`, toujours redémarrer le conteneur `backend-queue-1` pour charger le nouveau code.
