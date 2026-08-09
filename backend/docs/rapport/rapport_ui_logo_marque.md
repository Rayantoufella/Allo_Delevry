# Rapport — UI-02 : Intégration du logo de la marque

**Date :** 9 août 2026
**Périmètre :** `D:\AlloDelevry\frontend`
**Source :** `backend/docs/LOGO/image.png`
**Statut :** Terminé
**Branche :** `feature/FrontEnd`

## 2. Contexte

Le logo fourni est un PNG **1254 × 1254 en RGB, sans canal alpha** : son fond
blanc est opaque. Posé tel quel dans l'interface, il aurait formé un carré blanc
sur le thème sombre, qui est le thème par défaut de l'application. Il fallait
donc le détourer, en extraire les deux verrouillages utiles (monogramme seul,
verrouillage complet) et produire les tailles employées par l'app.

Le bandeau affichait jusqu'ici une pastille verte portant un « A » typographique,
héritée du prototype ; l'onglet du navigateur montrait encore le logo violet du
starter Vue.

## 3. Tableau récapitulatif des actions

| # | Action | Résultat |
|---|--------|----------|
| 1 | Script de génération | `frontend/scripts/build-logo.mjs`, sans dépendance (décodage/encodage PNG via `zlib`) — `npm run logo` |
| 2 | Détourage | Remplissage par diffusion depuis les bords, et non seuil global : préserve les blancs *intérieurs* de l'illustration |
| 3 | Découpe | Bandes de lignes vides détectées automatiquement → monogramme (y 169-760) et verrouillage complet (y 169-1013) |
| 4 | Variante sombre | L'encre du mot-marque remappée vers `--fg` : son contraste tombait à **1.14** sur `--bg`, elle y était invisible |
| 5 | Bandeau | Le « A » typographique remplacé par le monogramme, dans la case de 32 px du prototype, sur tuile blanche |
| 6 | Écrans d'authentification | Verrouillage complet au-dessus du formulaire, variante choisie selon le thème |
| 7 | Onglet & iOS | `favicon.png` et `apple-touch-icon.png` régénérés ; le `favicon.svg` violet du starter Vue supprimé |
| 8 | Métadonnées | `theme-color` au vert de la marque, `description` renseignée |

## 4. Rôle des fichiers

| Fichier | Rôle | Points clés |
|---------|------|-------------|
| `scripts/build-logo.mjs` | **Nouveau** — génère tous les assets depuis la source | Sans dépendance ; relancer après tout remplacement du logo source |
| `src/assets/logo-mark.png` | **Nouveau** — monogramme 128×128, transparent | 128 px couvre un affichage à 32 px jusqu'à 4× de densité |
| `src/assets/logo-full.png` | **Nouveau** — verrouillage complet 360×282 | Fonds clairs |
| `src/assets/logo-full-dark.png` | **Nouveau** — idem, encre éclaircie | Fonds sombres |
| `public/favicon.png` | Onglet du navigateur | Tuile blanche arrondie **cuite dans le fichier** (voir §5) |
| `public/apple-touch-icon.png` | Écran d'accueil iOS | Carré plein : iOS applique son propre masque |
| `public/favicon.svg` | *Supprimé* | Logo violet du starter Vue, plus référencé |
| `src/components/BrandLockup.vue` | **Nouveau** — verrouillage adaptatif | Choisit la variante en JS pour ne télécharger que celle affichée |
| `src/components/AppHeader.vue` | Bandeau | Importe le monogramme (empreinte de contenu Vite → pas de cache périmé) |
| `src/styles/header.css` | Case du logo | Tuile blanche 32 px, radius 9 px |
| `src/styles/auth-split.css` | Écrans d'authentification | Espacement du verrouillage au-dessus du formulaire |
| `src/views/AuthLoginView.vue`, `AuthRegisterView.vue` | Connexion / inscription | Affichent `BrandLockup` |
| `index.html` | Métadonnées du document | Icônes, `theme-color`, `description` |
| `package.json` | Scripts | `npm run logo` |

## 5. Détail technique

**Détourage.** Un seuil global sur le blanc aurait percé l'illustration : les
pointillés de la route, le contour du scooter et le point du repère sont blancs
eux aussi. Le script part donc des bords et ne retire que le blanc qui leur est
connexe. L'alpha reste binaire à pleine résolution ; le lissage des bords naît du
sous-échantillonnage, fait en **alpha prémultiplié** — sans quoi le blanc des
pixels transparents déteindrait sur les bords et cernerait le logo d'un liseré.

**Variante sombre.** Deux critères mesurés sur le fichier distinguent l'encre du
vert de marque : luminance ≤ 60 pour les aplats, et `g - b` ≤ 16 là où le vert de
marque est à 96-128. Le remap s'arrête au bloc de texte : l'illustration garde son
encre d'origine, car le scooter y est cerné d'un liseré blanc qui suffit à le
détacher d'un fond sombre — l'éclaircir le ferait fondre dans ce liseré.

**Tuile blanche.** Le contre-poinçon du « A » est ouvert vers le bas : le blanc
qui porte l'illustration communique avec le fond et disparaît au détourage. Sur
fond sombre, le scooter — encre foncée — se retrouve donc sur du sombre. À 32 px,
sans tuile, le logo tourne à la tache verte. Le bandeau pose donc une tuile
blanche en CSS (le thème garde la main) ; les favicons, auxquels aucune feuille
de style ne s'applique, l'ont cuite dans le fichier.

**Poids.** Monogramme 17 Ko, verrouillages 74 et 69 Ko. Le verrouillage n'est
chargé que sur les écrans d'authentification, et une seule variante à la fois.

## 6. Vérifications

- `npm run logo` : 5 fichiers produits, dimensions et poids attendus
- `npx vite build` : OK, assets empreintés (`logo-mark-DSApvQ6G.png`…)
- Serveur de dev : `/favicon.png`, `/apple-touch-icon.png`, `/src/assets/logo-mark.png` servis en 200 ; `index.html` porte bien les trois métadonnées
- Contrôle de rendu : composition des variantes sur `--bg` sombre (#0b0d0c) et
  clair (#f4f5f3), et du monogramme à 32 / 38 / 64 px sur les deux fonds — c'est
  ce contrôle qui a révélé le besoin de tuile
- **Non vérifié :** le rendu dans un vrai navigateur. L'extension n'était pas
  connectée ; les contrôles ci-dessus sont des compositions calculées hors du
  navigateur.

## 7. Références / suite logique

- Rapport lié : [rapport_ui_conformite_prototype.md](rapport_ui_conformite_prototype.md)
- Le prototype ne prévoyait pas de logo : la case de 32 px du bandeau et son rayon
  de 9 px sont conservés, seul leur contenu change.
- Restes du starter Vue encore présents et sans rapport avec le projet :
  `src/components/HelloWorld.vue` (non importé), `public/icons.svg`,
  `src/assets/vite.svg`, `src/assets/vue.svg`, `src/assets/hero.png`. Ils n'ont
  pas été touchés, hors périmètre.
