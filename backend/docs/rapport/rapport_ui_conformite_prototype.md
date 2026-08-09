# Rapport — UI-01 : Mise en conformité de l'UI avec le prototype

**Date :** 9 août 2026
**Périmètre :** `D:\AlloDelevry\frontend`
**Référence :** prototype `Allo Delivery.html` — source décompilée dans `frontend/design-ref/`
**Statut :** Terminé
**Branche :** `feature/FrontEnd`

## 2. Contexte

Le prototype `Allo Delivery.html` est un export bundlé : le DOM et la logique React
sont encodés dans un `<script type="__bundler/template">`. Les valeurs de design
citées ici viennent de ce template décodé, pas d'une capture d'écran — notamment
la méthode `icon()` du composant, qui définit le jeu d'icônes complet en tracés
SVG 24×24.

L'implémentation Vue avait dérivé du modèle sur plusieurs points visibles :
icônes redessinées (dont une confusion colis/camion), bandeau aux mauvaises
dimensions, navigation livreur en double, champs de formulaire d'une autre
grammaire, couleurs de statut désaccordées. Le présent lot ramène l'UI sur le
prototype.

## 3. Tableau récapitulatif des actions

| # | Action | Résultat |
|---|--------|----------|
| 1 | Décodage du prototype | Template + ressources extraits du bundle ; jeu d'icônes et tables `STATUS` / `STEPS` récupérés à la source |
| 2 | Jeu d'icônes | 17 tracés du prototype repris au caractère près ; `package` pointait sur le tracé du **camion** — corrigé ; stroke par défaut 1.5 → 1.8 |
| 3 | Icône par service | `serviceIcons.js` : mappage libellé → icône (colis/doc/courses/repas/pharmacie), appliqué au catalogue, aux missions et aux pilules du formulaire |
| 4 | Bandeau | Bouton icône 34 → 40 px, logo 30 → 32 px, mot-marque `Allo`+`Delivery` vert, onglets posés dans le rail segmenté `--surface-2` |
| 5 | Navigation livreur | Retirée du bandeau (doublon de la sidebar) ; « Notifications » déplacé dans la sidebar avec son compteur |
| 6 | Formulaires | Champs soulignés → boîtes `--surface-2` radius 12 ; libellés en casse normale ; la règle de libellé ne matchait aucun champ (corrigée) |
| 7 | Couleurs de statut | 4 statuts sur 9 réalignés sur la table du prototype ; ajout de `.badge-grey` pour « annulée » |
| 8 | Suivi | `StatusTimeline` rend désormais les deux traitements du prototype : les 5 étapes canoniques (client) et le journal (livreur) |
| 9 | Divers | Accueil recentré à 1200 px ; accent IA vert au lieu de violet ; valeurs de KPI dépolychromées ; SVG en dur remplacés par `AppIcon` |

## 4. Rôle des fichiers

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `src/components/AppIcon.vue` | Jeu d'icônes unique | Bloc « prototype » = 17 tracés repris tels quels, à ne pas retoucher ; bloc « écrans absents du prototype » pour le reste ; `ALIASES` couvre les anciens noms (`package`, `scooter`, `banknote`) |
| `src/lib/serviceIcons.js` | **Nouveau** — libellé de service → icône | Règles par mots-clés, plus spécifique d'abord ; normalisation NFD pour que « médicament » matche « medicament » ; repli sur `box` |
| `src/lib/statuses.js` | Contrat de statuts côté UI | Couleurs alignées sur la table `STATUS` du prototype ; `icon` passe d'emoji à nom `AppIcon` ; ajout de `STATUS_STEP` (rang dans la progression) |
| `src/components/StatusTimeline.vue` | Suivi d'une demande | Prop `variant` : `steps` (5 jalons toujours affichés) / `history` (journal) ; l'étape `approach` n'est pas un statut backend |
| `src/components/AppHeader.vue` | Bandeau | `hasNav` masque les onglets chez le livreur et sur l'accueil ; `hasMenu` conditionne le burger ; override mobile préfixé `.banner` pour la spécificité |
| `src/styles/header.css` | Géométrie du bandeau | `.nav--rail` porte le rail segmenté, `.banner nav` reste neutre ; `.icon-btn` 2.5 rem |
| `src/styles/components.css` | Briques partagées | Champs en boîte ; libellé ciblé via `.field > span:first-child:not([class])` ; `.badge-grey` |
| `src/styles/driver-shell.css` | Coquille livreur | Entrée active : surface + graisse, plus de bordure verte ; gap aligné sur le prototype |
| `src/components/driver/DriverSidebar.vue` | Navigation livreur | Icônes `truck` / `map` / `bell` (19 px) ; entrée Notifications + compteur `unread_notifications` ; l'icône hérite de `currentColor` |
| `src/components/driver/StatCard.vue` | KPI du tableau de bord | Prop `accent` supprimée : les 4 valeurs partagent la couleur du texte, seul le delta est vert |
| `src/components/driver/RequestCard.vue` | Ligne de mission | Icône du service au lieu d'un « colis » figé |
| `src/components/client/ServiceCard.vue` | Carte du catalogue | Idem ; hover −3 px |
| `src/views/LandingView.vue` | Accueil | `max-width: 75rem; margin: 0 auto` rétabli (le prototype cadre bien à 1200 px) ; padding latéral 2.5 rem ; SVG → `AppIcon` |
| `src/views/DriverPublicView.vue` | Page publique du livreur | Accent IA vert (le violet est réservé au statut « colis récupéré ») ; SVG → `AppIcon` |
| `src/views/RequestFormView.vue` | Formulaire de demande | Pilules de service : rectangle 11 px, filet 1.5 px, libellé en `--fg`, icône du service |
| `src/views/RequestDetailView.vue` | Détail client | Surtitre « Prix proposé » en vert |
| `src/views/DashboardView.vue` | Tableau de bord | Icônes KPI `truck` / `cash` / `bolt` / `star` ; `accent` retiré |
| `src/views/MissionView.vue` | Mission livreur | `StatusTimeline variant="history"` |
| `src/views/AuthLoginView.vue`, `AuthRegisterView.vue` | Écrans d'authentification | Badge « Compte professionnel » via `AppIcon` |

## 5. Détail technique

**Jeu d'icônes.** Le prototype définit ses icônes dans `icon(name, size=22)`, en
tracés `stroke` sur grille 24×24. `AppIcon` portait un jeu redessiné : les formes
ne correspondaient pas, et `package` était le tracé du camion (`M3 6h11v9H3z` +
cabine + roues). Une carte « colis » affichait donc un camion. Les 17 noms du
prototype sont désormais copiés tels quels ; les icônes propres aux écrans que le
prototype ne couvre pas (caméra, cadenas, presse-papier…) restent dans un second
bloc, dessiné dans le même style.

**Navigation livreur.** Le bandeau reprenait « Tableau de bord / Demandes /
Notifications / Profil », déjà présents dans la sidebar. Le prototype n'a qu'une
navigation pour cet espace : la sidebar. Le bandeau ne garde donc que les onglets
d'espace, et « Notifications » — qui n'existait que dans le bandeau — a rejoint la
sidebar. Sur mobile la sidebar devient la barre basse fixe, la page reste donc
accessible.

**Champs de formulaire.** Deux problèmes distincts. D'une part `.input` était un
champ souligné alors que le prototype n'utilise que des boîtes `--surface-2`
radius 12 — les écrans d'authentification, eux, étaient déjà conformes, si bien
que l'application mélangeait deux grammaires. D'autre part la règle de libellé
visait `.field label`, alors que les vues écrivent
`<label class="field"><span>…</span>` : aucun libellé n'était stylé. Le sélecteur
cible maintenant aussi le premier `<span>` nu du `.field`, `:not([class])`
écartant les `<span>` porteurs de leur propre rôle (l'avatar de « Profil &
marque »).

**Couleurs de statut.** Le prototype lit comme une progression : ambre → bleu →
violet → vert. Quatre statuts en sortaient (`confirmee` en vert, `colis_recupere`
en vert, `en_livraison` en bleu, `annulee` en rouge). Ils sont réalignés ;
`annulee` passe en gris neutre — une annulation n'est pas une erreur.

**Suivi.** Le prototype distingue « Étapes de la livraison » (5 jalons toujours
affichés, cochés au fur et à mesure) et « Historique des statuts » (journal à
pastilles colorées). Un seul composant rendait l'historique dans les deux cas :
le client ne voyait donc jamais ce qui restait à venir. `variant` sépare les deux.

## 6. Vérifications

- Build : `npx vite build` — OK
- Serveur de dev : `npx vite` — 200, modules servis, pas d'erreur de compilation
- Résolution des icônes : script de contrôle sur les 29 noms statiques employés
  dans les vues → tous définis (le seul « manquant » est la liaison dynamique
  `:name="icon"` de `StatCard`, dont les 4 valeurs sont vérifiées à l'appel)
- Mappage service → icône : testé sur « Envoi de colis », « Documents & plis »,
  « Courses & achats », « Plats & repas », « Pharmacie », « Médicaments »,
  « Épicerie » et un libellé sans correspondance
- Backend : non touché — Pint et Pest sans objet
- **Non vérifié :** aucun rendu visuel. L'extension navigateur n'était pas
  connectée ; le travail est fait à partir de la source du prototype, pas d'une
  comparaison d'écrans.

## 6 bis. Passe de composition (couche finale)

Une seconde passe a porté sur le **placement** des blocs, pas sur leur style.

**La cause commune.** `.container` n'avait aucune largeur maximale, sur la foi
d'un commentaire affirmant que « le prototype étale son contenu sur toute la
largeur ». C'est faux — la même erreur que sur l'accueil. Le prototype pose :

```html
<div style="…align-items:center;padding:0 20px">
  <div style="width:100%;max-width:1080px;padding:28px 0 60px">
```

Tous les écrans client s'étiraient donc d'un bord à l'autre. `.container` est
ramené à 1080 px centrés.

**Trois coquilles au lieu d'une.** `App.vue` appliquait `.container` à tout ce
qui n'était pas l'espace livreur — y compris l'accueil et les écrans
d'authentification, qui sont pleine page dans le prototype. Le panneau scindé de
la connexion se retrouvait encadré de marges, sans occuper la hauteur. Le choix
se fait maintenant sur `route.meta.layout` : `container` (client), `full`
(accueil, authentification), `flush` (livreur).

| Écran | Avant | Après |
|---|---|---|
| Page publique livreur | coquille dupliquée localement → **marge horizontale doublée** | celle de `.container` |
| Catalogue des services | liste en une colonne | grille `auto-fill` à partir de 240 px |
| Formulaire de demande | 680 px | 840 px (largeur du prototype) |
| Assistant IA | 700 px | 760 px |
| Suivi client | ruban de 8 cartes en une colonne de 740 px | deux colonnes 1.5fr / 1fr ; l'échéance passe en tête, à droite du numéro |
| Détail d'une demande | idem | deux colonnes ; les cartes d'action restent pleine largeur au-dessus |
| Tableau de bord | « Notifications » et « Missions » en demi-colonnes | Notifications en regard du graphique, Missions pleine largeur |
| Profil & marque | « Ta page publique » pleine largeur pour un QR code | colonne latérale 1.4fr / 1fr |
| Mission livreur | 1.25fr / 1fr, gouttière 14 px | 1.3fr / 1fr, gouttière 18 px |

Toutes les grilles ajoutées déclarent leurs pistes en `minmax(0, …)` et leurs
colonnes en `min-width: 0` : une adresse longue imposerait sinon sa largeur
intrinsèque à la piste et ferait défiler la page.

## 6 ter. Disponibilité retirée, lien client mis en avant

**Section « Disponibilité » supprimée du tableau de bord** — l'interrupteur
en ligne / en pause, sa légende, l'appel `PATCH /driver-profiles/{id}` associé et
leurs styles. Le réglage n'est pas perdu pour autant : la case « Disponible pour
accepter de nouvelles missions » du formulaire de « Profil & marque » reste le
seul endroit où il se change — il y était déjà, en double.

**Lien client à sa place.** Un compte client est rattaché au livreur qui l'a
amené : sans ce lien, personne ne peut commander chez lui. C'est donc la
première chose dont un livreur a besoin, et elle n'existait que dans « Profil &
marque ». Le tableau de bord porte maintenant une carte « Mon lien client » —
QR code, lien, bouton **Copier le lien**, bouton **Aperçu client** — à la place
libérée par la disponibilité. Sans profil public, la carte invite à le créer
plutôt que d'afficher un lien vide.

**Composition du lien mutualisée** dans `src/lib/driverLink.js`
(`publicUrl`, `prettyLink`, `qrUrl`, `copyPublicLink`) : les deux écrans
l'affichent, la recomposer de chaque côté les aurait laissés diverger.
`copyPublicLink` renvoie le lien lui-même quand `navigator.clipboard` échoue —
ce qui arrive hors contexte sécurisé (HTTP) ou sur refus de permission — pour
que le livreur puisse au moins le sélectionner à la main.

**Effet de bord corrigé.** `DriverSidebar` calculait `isAvailable` sans jamais
le lire : la marque s'affichait « en ligne » même pour un livreur en pause. Le
calcul est maintenant utilisé (« en ligne » / « en pause », pastille ambre sans
halo).

| Fichier | Changement |
|---------|-----------|
| `src/lib/driverLink.js` | **Nouveau** — composition et copie du lien public |
| `src/views/DashboardView.vue` | Section disponibilité retirée ; carte « Mon lien client » ; toast |
| `src/views/ProfileView.vue` | Utilise `driverLink.js` au lieu de recomposer l'URL |
| `src/components/driver/DriverSidebar.vue` | Présence réelle au lieu d'« en ligne » figé |
| `src/styles/utilities.css` | `.dot-paused` |

## 7. Références / suite logique

- Référence de traduction : `frontend/design-ref/README.md` (mapping écran → vue).
  Deux points de ce README sont contredits par la source du prototype et n'ont pas
  été suivis : les inputs y sont donnés sur `var(--surface)` alors que le
  formulaire du prototype utilise `--surface-2`, et la carte IA y est décrite en
  violet alors qu'elle est verte.
- Suite : passe visuelle écran par écran une fois l'extension navigateur
  disponible, en particulier sur `MissionView` et `ProfileView`, les deux vues les
  plus longues et les moins couvertes ici.
