# Rapport — AR-03 : Réponses API uniformes

**Date :** 25 juillet 2026 — semaine 1
**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** Cahier des charges — API REST sécurisée
**Statut :** Terminé (Jira AR-03)

---

## 1. Contexte

Chaque contrôleur répondait avec son propre format (JSON brut, `response()->json`, erreurs variées).
L'API doit exposer des réponses cohérentes pour faciliter le travail du frontend Vue.js.

## 2. Tableau récapitulatif des actions

| # | Action | Résultat |
|---|--------|----------|
| 1 | Trait `ApiResponse` | `6695583` — `success(data, message, status)` → `{success, message, data}` ; `error(message, status, errors)` → `{success, message, errors}` ; `paginated(ResourceCollection)` |
| 2 | Refactor contrôleur de base | `6695583` — `Controller` abstrait utilise `ApiResponse` + `AuthorizesRequests` |
| 3 | AuthController aligné | `6695583` — register/login/me/logout utilisent le format uniforme |
| 4 | Pint + CI | `c4c1efc` — configuration Laravel Pint + workflow CI ; `e1471ca` — formatage global appliqué |

## 3. Détail

### 3.1 Format de réponse standard

```json
{
  "success": true,
  "message": "...",
  "data": { }
}
```

En cas d'erreur : `{"success": false, "message": "...", "errors": {...}}`.

### 3.2 Outillage

- **Pint** : règles Laravel standard (`vendor/bin/pint --dirty` avant chaque livraison).
- **CI GitHub Actions** : vérification du formatage + tests à chaque push.

## 4. Vérifications

- Tous les contrôleurs API existants utilisent le trait (ou `response()->json(Resource)` pour les ressources seules).
- Pint propre sur tout le dépôt.

## 5. Références

- Branche : `feature/5.3-api-response`.
- Suite logique : AR-04 (config Reverb).
