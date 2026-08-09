<script setup>
import { computed } from 'vue'
/**
 * Jeu d'icônes unique de l'application.
 *
 * L'UI mélangeait des emojis dans les cartes et des icônes ligne dans la
 * sidebar : deux vocabulaires visuels pour le même rôle. Tout passe ici.
 *
 * Les tracés du bloc « prototype » sont repris **au caractère près** de
 * `design-ref` (méthode `icon()` du prototype) : ce sont eux qui donnent à
 * l'app sa silhouette d'icônes. Les redessiner « en mieux » suffit à faire
 * diverger l'UI du modèle, c'est pourquoi ils ne doivent pas être retouchés.
 *
 * Conventions :
 *  - tracé seul, `currentColor` : la couleur vient du contexte, jamais de l'icône
 *  - 20px et stroke 1.8 par défaut (valeurs du prototype), surchargeables
 *  - `aria-hidden` par défaut : une icône accompagne un texte et ne le double pas.
 *    Si elle est seule (bouton icône), passer `label` — le SVG devient alors
 *    `role="img"` avec un `<title>` accessible.
 */
const props = defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 20 },
  stroke: { type: [Number, String], default: 1.8 },
  label: { type: String, default: '' },
})

// Chaque entrée est la liste des `d` de ses tracés, sur une grille 24×24.
const PATHS = {
  /* ---------- Jeu du prototype : tracés repris tels quels ----------
     Ne pas « corriger » ces `d` : ce sont ceux du modèle de référence. */

  // — Types de service (catalogue livreur) —
  box: ['M3.3 7.5 12 3l8.7 4.5v9L12 21l-8.7-4.5z', 'M3.3 7.5 12 12l8.7-4.5', 'M12 12v9'],
  doc: ['M6 3h8l4 4v14H6z', 'M14 3v4h4', 'M9 12h6M9 16h6'],
  cart: ['M3 4h2l2.4 11.5a1 1 0 0 0 1 .8h8.4a1 1 0 0 0 1-.8L20 8H6', 'M9 20h.01M17 20h.01'],
  food: ['M4 3v7a3 3 0 0 0 3 3v8M7 3v6M10 3v6M20 3c-2 0-3 2-3 5s1 4 3 4v9'],
  pill: ['M10.5 3.5 3.5 10.5a4 4 0 0 0 5.7 5.7l7-7a4 4 0 0 0-5.7-5.7z', 'M7 7l6 6'],

  // — Navigation & livraison —
  user: ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M4 21a8 8 0 0 1 16 0'],
  bolt: ['M13 2 4 14h7l-1 8 9-12h-7z'],
  chat: ['M4 5h16v11H9l-4 4v-4H4z'],
  grid: ['M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'],
  ticket: ['M4 6h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4z', 'M14 6v12'],
  home: ['M4 11 12 4l8 7', 'M6 10v10h12V10'],
  inbox: ['M4 13h4l1 3h6l1-3h4', 'M4 13 6 4h12l2 9v7H4z'],
  map: ['M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2z', 'M9 4v14M15 6v14'],
  chart: ['M4 20V4', 'M4 20h16', 'M8 16l3-4 3 3 4-6'],
  truck: ['M3 6h11v9H3z', 'M14 9h4l3 3v3h-7z', 'M7 18a2 2 0 1 0 0.01 0M17 18a2 2 0 1 0 0.01 0'],
  cash: ['M3 6h18v10H3z', 'M12 11a2 2 0 1 0 0.01 0', 'M7 6v10M17 6v10'],
  star: ['M12 3l2.6 5.5 6 .8-4.4 4.2 1.1 6L12 16.8 6.7 19.5l1.1-6L3.4 9.3l6-.8z'],

  /* ---------- Écrans absents du prototype ----------
     Dessinés dans le même style : grille 24, stroke 1.8, extrémités rondes. */

  // — Livraison —
  pin: ['M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z', 'M12 10a2 2 0 1 0 .01 0'],
  flag: ['M4 21V4', 'M4 4h12l-2 4 2 4H4'],
  route: ['M6 19a2 2 0 1 0 .01 0M18 5a2 2 0 1 0 .01 0', 'M8 19h6a4 4 0 0 0 0-8h-4a4 4 0 0 1 0-8h6'],

  // — Argent —
  bank: ['M3 10 12 4l9 6', 'M5 10v9M19 10v9M9 10v9M15 10v9', 'M3 20h18'],

  // — Temps —
  clock: ['M12 3a9 9 0 1 0 .01 0', 'M12 7v5l3.5 2'],
  calendar: ['M4 6h16v14H4z', 'M4 10h16', 'M8 3v4M16 3v4'],

  // — Communication —
  bell: ['M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9', 'M13.7 21a2 2 0 0 1-3.4 0'],

  // — Sécurité —
  lock: ['M5 11h14v10H5z', 'M8 11V7a4 4 0 0 1 8 0v4'],
  shield: ['M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z'],

  // — Actions —
  camera: ['M3 8h4l2-3h6l2 3h4v12H3z', 'M12 17a4 4 0 1 0 .01 0'],
  refresh: ['M21 12a9 9 0 1 1-3-6.7', 'M21 4v5h-5'],
  download: ['M12 4v11', 'M8 11l4 4 4-4', 'M4 20h16'],
  external: ['M14 4h6v6', 'M20 4 11 13', 'M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5'],
  close: ['M6 6l12 12M18 6 6 18'],
  check: ['M4 12.5 9 17.5 20 6.5'],
  eye: ['M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6z', 'M12 9.5a2.5 2.5 0 1 0 .01 0'],
  clipboard: ['M9 4h6v3H9z', 'M9 5.5H6v15h12v-15h-3'],
  pen: ['M4 20h4L20 8l-4-4L4 16z'],
  ban: ['M12 3a9 9 0 1 0 .01 0', 'M6 6l12 12'],
  menu: ['M4 7h16M4 12h16M4 17h16'],
  attach: ['M20 11 12 19a4.5 4.5 0 0 1-6.4-6.4l8-8a3 3 0 0 1 4.3 4.3l-8 8a1.5 1.5 0 0 1-2.2-2.2l7.4-7.4'],
  send: ['M12 19V5', 'M6 11l6-6 6 6'],
  phone: ['M6 3h3l2 5-2.5 1.5a11 11 0 0 0 5 5L15 12l5 2v3a2 2 0 0 1-2.2 2A16 16 0 0 1 4 5.2 2 2 0 0 1 6 3z'],
  compass: ['M12 3a9 9 0 1 0 .01 0', 'M15.5 8.5 13.5 13.5 8.5 15.5 10.5 10.5z'],
  link: ['M10 13a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7L11.5 5.8', 'M14 11a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.5-1.5'],
  plus: ['M12 5v14M5 12h14'],

  // — Signalement —
  warning: ['M12 4 2.5 20h19z', 'M12 10v4', 'M12 17.5a.4.4 0 1 0 .01 0'],
  sparkle: ['M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z'],
  party: ['M4 20 9 7l8 8z', 'M14 4v2M18 6l1.5-1.5M19 10h2'],

  // — Thème —
  sun: ['M12 8a4 4 0 1 0 .01 0', 'M12 2v2M12 20v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2 12h2M20 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4'],
  moon: ['M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z'],
}

/* Anciens noms restés dans les vues. Le cas de `package` n'était pas cosmétique :
   il portait le tracé du camion, si bien qu'un « colis » s'affichait en camion. */
const ALIASES = {
  package: 'box',
  scooter: 'bolt',
  banknote: 'cash',
}

const paths = PATHS[props.name] || PATHS[ALIASES[props.name]] || []

/* La taille est exprimée en `rem` : un attribut `width` en px ne suivrait pas
   l'échelle de la racine et l'icône rapetisserait à mesure que le texte grandit.
   `size` reste donné en pixels de référence (base 16) pour rester lisible. */
const remSize = computed(() => `${Number(props.size) / 16}rem`)
</script>

<template>
  <svg
    :style="{ width: remSize, height: remSize }"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    :stroke-width="stroke"
    stroke-linecap="round"
    stroke-linejoin="round"
    :role="label ? 'img' : undefined"
    :aria-hidden="label ? undefined : 'true'"
    :focusable="false"
    class="app-icon"
  >
    <title v-if="label">{{ label }}</title>
    <path v-for="(d, i) in paths" :key="i" :d="d" />
  </svg>
</template>

<style scoped>
.app-icon {
  /* Alignement optique : le centre de l'œil d'une icône tombe légèrement plus
     haut que la ligne de base du texte qu'elle accompagne. */
  display: inline-block;
  vertical-align: -0.18em;
  flex: none;
}
</style>
