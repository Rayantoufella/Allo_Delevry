<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useThemeStore } from '../stores/theme'

const router = useRouter()
const auth = useAuthStore()
const theme = useThemeStore()

const isHome = computed(() => router.currentRoute.value.path === '/')

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
    <button class="btn btn-ghost logo" @click="goHome" title="Allo Delivery">
      <span class="mark">A</span>
      <span>Allo Delivery</span>
    </button>

    <nav v-if="auth.isClient">
      <RouterLink class="btn btn-ghost" :to="{ name: 'my-requests' }">Mes demandes</RouterLink>
    </nav>
    <nav v-else-if="auth.isDriver">
      <RouterLink class="btn btn-ghost" :to="{ name: 'driver-dashboard' }">Tableau de bord</RouterLink>
      <RouterLink class="btn btn-ghost" :to="{ name: 'driver-requests' }">Demandes</RouterLink>
      <RouterLink class="btn btn-ghost" :to="{ name: 'driver-notifications' }">Notifications</RouterLink>
      <RouterLink class="btn btn-ghost" :to="{ name: 'driver-profile' }">Profil</RouterLink>
    </nav>

    <div class="spacer"></div>

    <button class="icon-btn" :title="theme.isDark ? 'Mode clair' : 'Mode sombre'" @click="theme.toggle()">
      {{ theme.isDark ? '☀' : '🌙' }}
    </button>

    <template v-if="auth.isAuthenticated">
      <span class="small muted">{{ auth.user?.name }}</span>
      <button class="btn btn-outline" @click="onLogout">Déconnexion</button>
    </template>
    <template v-else-if="isHome || !auth.isAuthenticated">
      <RouterLink class="btn btn-outline" :to="{ name: 'login' }">Espace client</RouterLink>
      <RouterLink class="btn btn-primary" :to="{ name: 'login-driver' }">Espace livreur</RouterLink>
    </template>
  </header>
</template>
