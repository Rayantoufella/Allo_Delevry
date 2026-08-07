<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useThemeStore } from '../stores/theme'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const theme = useThemeStore()

const isHome = computed(() => route.path === '/')

const isActive = (name) => route.name === name

function goHome() {
  if (auth.isDriver) router.push({ name: 'driver-dashboard' })
  else if (auth.isClient) router.push({ name: 'my-requests' })
  else router.push({ name: 'landing' })
}

async function onLogout() {
  await auth.logout()
  router.push({ name: 'landing' })
}
</script>

<template>
  <header class="banner">
    <button class="logo btn-ghost" @click="goHome" title="Allo Delivery">
      <span class="mark">A</span>
      <span>Allo Delivery</span>
    </button>

    <!-- Visiteur : choix d'espace (comme le prototype) -->
    <nav v-if="!auth.isAuthenticated">
      <RouterLink class="nav-btn" :class="{ active: isActive('login') || isActive('register') }" :to="{ name: 'login' }">Espace client</RouterLink>
      <RouterLink class="nav-btn" :class="{ active: isActive('login-driver') || isActive('register-driver') }" :to="{ name: 'login-driver' }">Espace livreur</RouterLink>
    </nav>

    <!-- Client connecté -->
    <nav v-else-if="auth.isClient">
      <RouterLink class="nav-btn" :class="{ active: isActive('my-requests') }" :to="{ name: 'my-requests' }">Mes demandes</RouterLink>
    </nav>

    <!-- Livreur connecté -->
    <nav v-else-if="auth.isDriver">
      <RouterLink class="nav-btn" :class="{ active: isActive('driver-dashboard') }" :to="{ name: 'driver-dashboard' }">Tableau de bord</RouterLink>
      <RouterLink class="nav-btn" :class="{ active: isActive('driver-requests') || isActive('driver-mission') }" :to="{ name: 'driver-requests' }">Demandes</RouterLink>
      <RouterLink class="nav-btn" :class="{ active: isActive('driver-notifications') }" :to="{ name: 'driver-notifications' }">Notifications</RouterLink>
      <RouterLink class="nav-btn" :class="{ active: isActive('driver-profile') }" :to="{ name: 'driver-profile' }">Profil</RouterLink>
    </nav>

    <div class="spacer"></div>

    <button class="icon-btn" :title="theme.isDark ? 'Mode clair' : 'Mode sombre'" @click="theme.toggle()">
      {{ theme.isDark ? '☀' : '🌙' }}
    </button>

    <template v-if="auth.isAuthenticated">
      <span class="small muted">{{ auth.user?.name }}</span>
      <button class="btn-outline" @click="onLogout">Déconnexion</button>
    </template>
  </header>
</template>
