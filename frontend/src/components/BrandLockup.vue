<script setup>
import { computed } from 'vue'
import { useThemeStore } from '../stores/theme'
import lockupLight from '../assets/logo-full.png'
import lockupDark from '../assets/logo-full-dark.png'

/**
 * Verrouillage complet de la marque : monogramme, mot-marque et baseline.
 *
 * Deux fichiers, pas un seul : le mot-marque « ALLO » et la baseline sont d'un
 * vert quasi noir, dont le rapport de contraste tombe à 1.14 sur le fond
 * `--bg` du thème sombre — il y disparaît. La variante sombre n'éclaircit que
 * cette encre ; le vert de la marque et l'illustration restent identiques.
 * Les deux sont produits par `scripts/build-logo.mjs`.
 *
 * On choisit en JavaScript plutôt qu'en CSS pour ne télécharger que la
 * variante réellement affichée.
 */
const theme = useThemeStore()

const src = computed(() => (theme.isDark ? lockupDark : lockupLight))
</script>

<template>
  <img
    class="brand-lockup"
    :src="src"
    alt="Allo Delivery — vos livraisons, notre priorité"
    width="360"
    height="282"
  />
</template>

<style scoped>
.brand-lockup {
  display: block;
  width: auto;
  /* La hauteur porte le dimensionnement : le verrouillage est presque carré,
     le caler sur la largeur le rendrait énorme dans une colonne de formulaire. */
  height: 6.5rem;
  max-width: 100%;
  object-fit: contain;
}

/* Sous cette hauteur d'écran, le formulaire prime sur la marque : le
   verrouillage repousserait le bouton de connexion sous la ligne de flottaison. */
@media (max-height: 700px) {
  .brand-lockup {
    display: none;
  }
}
</style>
