<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api/axios'
import { useAuthStore } from '../stores/auth'
import { useThemeStore } from '../stores/theme'
import { TERMINAL_STATUSES } from '../lib/statuses'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const theme = useThemeStore()

const isHome = computed(() => route.path === '/')

const isActive = (name) => route.name === name

// ---- Livraison en cours (client) : pastille de suivi dans la barre, comme le prototype ----
const activeRequest = ref(null)
let activeTimer = null

async function loadActiveRequest() {
  if (!auth.isClient) {
    activeRequest.value = null
    return
  }
  try {
    const { data } = await api.get('/delivery-requests', { params: { page: 1 } })
    const rows = data.data ?? data ?? []
    activeRequest.value = rows.find((r) => !TERMINAL_STATUSES.includes(r.status)) || null
  } catch {
    // silencieux : la pastille est un raccourci, pas une fonctionnalité critique
  }
}

function startActivePolling() {
  stopActivePolling()
  if (!auth.isClient) return
  loadActiveRequest()
  activeTimer = setInterval(loadActiveRequest, 15000)
}

function stopActivePolling() {
  if (activeTimer) clearInterval(activeTimer)
  activeTimer = null
}

watch(() => auth.isClient, startActivePolling)
onMounted(startActivePolling)
onBeforeUnmount(stopActivePolling)

function openActiveRequest() {
  if (activeRequest.value) {
    router.push({ name: 'request-detail', params: { id: activeRequest.value.id } })
  }
}

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

    <!-- Pastille livraison en cours (client) -->
    <button
      v-if="auth.isClient && activeRequest"
      class="tracking-chip"
      title="Voir ma livraison en cours"
      @click="openActiveRequest"
    >
      <span class="dot"></span>
      {{ activeRequest.tracking_number || `#${activeRequest.id}` }}
    </button>

    <button class="icon-btn" :title="theme.isDark ? 'Mode clair' : 'Mode sombre'" @click="theme.toggle()">
      {{ theme.isDark ? '☀' : '🌙' }}
    </button>

    <template v-if="auth.isAuthenticated">
      <span class="small muted">{{ auth.user?.name }}</span>
      <button class="btn-outline" @click="onLogout">Déconnexion</button>
    </template>
  </header>
</template>

<style scoped>
.tracking-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 14px;
  border-radius: 11px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-weight: 700;
  font-size: 0.82rem;
  font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
  cursor: pointer;
  transition: border-color 0.2s;
}
.tracking-chip:hover {
  border-color: var(--green);
}
.tracking-chip .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--green);
  animation: chip-pulse 2s infinite;
}
@keyframes chip-pulse {
  0% { box-shadow: 0 0 0 0 rgba(34, 197, 111, 0.55); }
  70% { box-shadow: 0 0 0 8px rgba(34, 197, 111, 0); }
  100% { box-shadow: 0 0 0 0 rgba(34, 197, 111, 0); }
}
</style>
