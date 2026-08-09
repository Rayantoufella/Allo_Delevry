<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from './components/AppHeader.vue'

const route = useRoute()

/**
 * Coquille de la page. Trois archétypes, décrits dans `styles/layout.css` :
 *
 *  - `flush` — l'espace livreur : sidebar collée au bord de la fenêtre, sous le
 *    header. Il ne passe ni par le conteneur centré ni par le rythme vertical
 *    des autres pages, sans quoi la sidebar se retrouve décalée de la marge du
 *    conteneur et détachée du header.
 *  - `full` — accueil et écrans d'authentification : ces vues composent
 *    elles-mêmes leur pleine page (dégradés jusqu'aux bords, panneau scindé sur
 *    toute la hauteur). Déclaré par `meta.layout` dans le routeur.
 *  - défaut — la colonne centrée de l'espace client.
 */
const layout = computed(() => {
  if (route.meta.role === 'driver') return 'flush'
  return route.meta.layout === 'full' ? 'full' : 'container'
})
</script>

<template>
  <AppHeader />
  <main
    class="page"
    :class="{ 'page--flush': layout === 'flush', 'page--full': layout === 'full' }"
  >
    <div :class="layout === 'container' ? 'container' : `shell-${layout}`">
      <RouterView />
    </div>
  </main>
</template>
