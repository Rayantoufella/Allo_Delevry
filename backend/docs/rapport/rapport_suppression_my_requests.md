# Suppression de la page /my/requests (espace client)

**Statut** : Terminé — commit `128f453` (feature/FrontEnd, poussé sur `origin/feature/FrontEnd`)

## Contexte

La page « Mes demandes » (`/my/requests`, vue `MyRequestsView.vue`) listait les demandes
du client. L'utilisateur a demandé sa suppression : le client commande désormais
uniquement via le chat IA de son livreur (`/drivers/:slug/ai`), qui remplit le
formulaire au fil de la conversation.

## Changements

| Fichier | Modification |
|---|---|
| `frontend/src/router/index.js` | Route `my-requests` supprimée ; `/my/requests` → `redirect: clientHomeRoute()` (chat IA du livreur mémorisé) ; guard : role mismatch, slug mismatch et routes d'auth → `clientHomeRoute()` |
| `frontend/src/components/AppHeader.vue` | `goHome()` client → `ai-assistant` (slug) ; onglet « Mes demandes » remplacé par « Commander » (→ chat IA) + « Chez mon livreur » (→ page publique) |
| `frontend/src/views/AuthLoginView.vue` | Après login client → `ai-assistant` (slug) |
| `frontend/src/views/AuthRegisterView.vue` | Après inscription client → `ai-assistant` (slug) |
| `frontend/src/views/LandingView.vue` | `goClient()` client connecté → `ai-assistant` |
| `frontend/src/views/RequestDetailView.vue` | « Retour à mes demandes » → « Retour à la commande » (→ chat IA) |
| `frontend/src/views/MyRequestsView.vue` | **Supprimé** (126 lignes) |

## Comportement

- `/my/requests` (ancien lien direct, favori, historique) → redirigé vers le chat IA
  du livreur du client (`clientHomeRoute()` : slug depuis le store auth, sinon landing).
- Client connecté : logo, connexion, inscription et boutons « retour » mènent au chat IA.
- Driver connecté : inchangé (dashboard).

## Vérifications

- `npm run build` : 139 modules, 0 erreur, plus aucun chunk `MyRequestsView`.
- QA navigateur live : login client → redirection vers `/drivers/rayan-express/ai` ;
  `/my/requests` → redirect fonctionnel ; chat IA opérationnel (message → job →
  réponse IA + extraction de la demande).
