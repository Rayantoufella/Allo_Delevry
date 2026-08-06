# TEMPLATE — Rapport d'action / de feature (Allo Delivery)

> **Règle projet :** toute action ou feature traitée sur le backend DOIT produire un rapport
> complet à partir de ce modèle, puis mettre à jour l'index `docs/rapport/README.md`.
> L'objectif : comprendre le projet à 100 % (chaque fichier, chaque action).

## 1. En-tête

```markdown
# Rapport — {ID} : {Titre}

**Date :** {jj mois aaaa}
**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** {cahier des charges — Fxx, Jira AR-xx}
**Statut :** {Terminé / En cours / Différé}
**Branche :** {feature/xxx} — commit(s) : {hash}
```

## 2. Contexte

{2-5 lignes : pourquoi cette action/feature, quel besoin du cahier des charges}

## 3. Tableau récapitulatif des actions

| # | Action | Résultat (commit, fichiers, décision) |
|---|--------|--------------------------------------|
| 1 | {action} | {ce qui a été fait / décidé} |
| 2 | … | … |

## 4. Rôle des fichiers (obligatoire)

> **Table exhaustive : TOUT fichier touché ou concerné par l'action/feature doit être listé.**
> Les fichiers récurrents (CRUD génériques) sont regroupés par motif, mais tous cités.

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/...` | {1-2 lignes} | {détail important : logique, piège, liaison} |
| … | … | … |

## 5. Détail technique

{éléments clés : endpoint, logique métier, règles de sécurité, choix}

## 6. Vérifications

- Pint : {passé / non concerné}
- Tests : {X passed (Y assertions) — commande utilisée}
- Manuel : {si pertinent}

## 7. Références / suite logique

- {branches, PR, rapports liés}
- {suite prévue}
