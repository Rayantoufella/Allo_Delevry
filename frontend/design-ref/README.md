# Référence Design — Prototype "Allo Delivery.html"

Source de vérité : `frontend/design-ref/screen*.html` (DOM rendu du prototype, styles inline complets).
Le prototype utilise des **styles inline React** — traduire chaque bloc en `<style scoped>` de la vue Vue correspondante, avec les mêmes valeurs.

## Règles de traduction

1. **Les tokens CSS restent identiques** (déjà dans `src/style.css`) :
   `--bg:#0b0d0c; --bg-2:#0f1211; --surface:#161a18; --surface-2:#1d221f; --surface-3:#252b27;`
   `--fg:#f3f6f4; --fg-2:#a4afa8; --fg-3:#6c7772; --border:#28302b; --border-2:#333c36;`
   `--green:#22c56f; --green-2:#3ad884; --green-ink:#04140b; --blue:#5b9dff; --amber:#f5b544; --violet:#a98bff; --red:#ff6a6a;`
   `--shadow:0 24px 60px -20px rgba(0,0,0,.7)`
2. **Police** : Manrope (déjà chargée).
3. **Anthropométrie boutons** (proto) :
   - Bouton primaire : `background:var(--green); color:var(--green-ink); border-radius:13px; padding:15px; font-weight:800; font-size:15.5px` (pleine largeur dans les formulaires)
   - Bouton outline secondaire : `background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:13px 16px; font-weight:800; font-size:14px; color:var(--fg)`
4. **Inputs** (proto) : `width:100%; padding:13px 15px; border-radius:12px; border:1px solid var(--border); background:var(--surface); color:var(--fg); font-size:15px; margin-bottom:16px`
   Labels : `font-size:12.5px; font-weight:700; color:var(--fg-2); margin-bottom:6px` (pas de majuscule !)
5. **Layout racine** : `min-height:100vh; background:var(--bg); color:var(--fg); display:flex; flex-direction:column`
6. **Header sticky** (écrans intérieurs) : `position:sticky; top:0; z-index:40; display:flex; align-items:center; gap:18px; padding:0 22px; height:64px; background:color-mix(in srgb,var(--bg) 82%,transparent); backdrop-filter:blur(18px); border-bottom:1px solid var(--border)`
   - Logo : carré 32px, radius 9px, bg `var(--green)`, `A` 18px 800 `var(--green-ink)` + texte `Allo<span green>Delivery</span>` 17px 800
   - Segmented control : `display:flex; gap:4px; padding:4px; background:var(--surface-2); border:1px solid var(--border); border-radius:12px; margin-left:8px` ; boutons internes `padding:7px 14px; border-radius:9px; border:none; font-weight:800; font-size:13px` ; actif : `background:var(--surface); color:var(--fg)` ; inactif : `transparent; color:var(--fg-2)`
   - Bouton thème : carré 40×40, radius 11px, `border:1px solid var(--border); background:var(--surface); font-size:17px`
7. **Animation** : `.au { animation:fadeUp .5s cubic-bezier(.2,.7,.3,1) both }` avec `@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}`
8. **Background décor** (landing) : `radial-gradient(900px 500px at 80% -10%,color-mix(in srgb,var(--green) 26%,transparent),transparent 60%),radial-gradient(700px 500px at 5% 110%,color-mix(in srgb,var(--green) 14%,transparent),transparent 55%)`
9. Réutiliser les classes globales existantes : `.card`, `.badge badge-*`, `.btn*`, `.field`, `.input`, `.avatar`, `.dot-online`, `.counter` — mais **reprendre les valeurs exactes des fichiers screen*** pour chaque écran.

## Mapping écran → fichier Vue

| Écran proto (design-ref/*.html) | Fichier Vue | Point clés |
|---|---|---|
| `screen2-login-client` | `AuthLoginView.vue` (role client) | **Split 2 colonnes** : gauche panneau VERT (`background:var(--green);color:var(--green-ink)`, padding 56px, titre 20px 800, gros titre clamp(30px,3.4vw,46px) 800 lh1.05 ls-0.03em, `max-width:38ch` 16px 600, 3 mini-étiquettes 13px 700, cercle décoratif blanc 12% à droite-bas) ; droite formulaire (`max-width:380px`, title 30px 800, inputs, bouton vert 100%, lien "Créer un compte") |
| `screen6-login-driver.html` | `AuthLoginView.vue` (role driver) | Idem mais panneau **surface** (`background:var(--surface)`) : "Espace livreur / Ta marque. Tes règles. Tes revenus." + stats 1240 livraisons / 4.9★ en dessous |
| `screen3-driver-public.html` | `DriverPublicView.vue` | Header rayon "profil" : avatar 64×64 radius 18px, nom 26px 800, badge "✓ Vérifié" vert, description 14px fg-2, ligne stats "★ 4.9 · 1 240 livraisons · ~14 min" ; bouton code/QR ; 2 cartes CTA : "✦ IA — Décrire ma demande" (icône violet) et "Choisir un service" ; section "CATALOGUE DES SERVICES" (label uppercase 11px 800) + grille services (`.svc-card` surface, radius 16, hover border green) ; badge "dès 15 DH" |
| `screen4-request-form.html` | `RequestFormView.vue` | Titre "Vérifier la demande" 2xl 800 + sous-titre ; latences : tab (Remplissage manuel / Assistant IA) en sticky segments ; "Type de service" chips ; champs ; "Méthode de paiement" 2 cards radio (💵 / 🏦) ; zone (4 cards avec prix) ; récap "Produit 0 DH / Frais 20 DH / Total 20 DH" via `.strip` ; bouton "Envoyer la demande →" pleine large verte |
| `screen5-tracking.html` | `TrackingView.vue` | "Suivi privé" + tracking code big 800 + StatutBadge ; livreur card avatar + "Rayan · livreur" + dot ; infos "Arrivée estimée"; sidebar `Étapes de la livraison` (5 étapes : Demande confirmée / Colis récupéré / En route / Arrivée imminente / Colis livré) ; ilot code de remise (fond vert pâle) ; JSON ticket card |
| `screen11-zones-tarifs.html` | `ProfileView.vue` (zone) | Rows zone : input nom + compteur "312 livraisons" + input prix + "DH" + bouton ✕ ; bouton "+ Ajouter une zone" |
| `screen10-profile-driver.html` | `ProfileView.vue` | Profil brand card (avatar + "Changer le logo"), champs informatifs, "Catalogue des services" (liste + bouton + Ajouter), "Ta page publique" (lien unique + boutons Copier / Aperçu) |
| `screen7-dashboard.html` | `DashboardView.vue` | `driver-layout` 2 colonnes : sidebar 250px (`bg:var(--bg-2)`, brand + nav 4 items : Tableau de bord, Demandes (badge 4), Mission active, Profil & marque, Zones & tarifs) + footer carte "Revenus du jour" vert ; contenu : titre "Tableau de bord" + "Bonjour Rayan", 4 stat cards (grands nombres, hausse verte), graph barres 7 jours (L M M J V S D) simple CSS, notifs panels, "Missions" liste compacte |
| `screen8-requests-list.html` | `RequestsListView.vue` | Titre "Demandes reçues" + sous-titre, rows type liste (service, statut badge, deux adresses "Talborjt → Cité Dakhla", prix, durée) |
| `screen9-request-detail-driver.html` | `MissionView.vue` / `RequestDetailView.vue` | *Partial* |

## Notes

- Le header vue globale (AppHeader.vue) doit, en mode login/register/driver-public, afficher le **segmented control** "Espace client / Espace livreur" du proto au lieu des liens nav actuels.
- Les écrans payload de la démo (GPS, paiement) sont déjà gérés : garder l'UX actuelle (UI-only).