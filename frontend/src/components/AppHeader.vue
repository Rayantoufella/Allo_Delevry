<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api/axios'
import { useAuthStore } from '../stores/auth'
import { useThemeStore } from '../stores/theme'
import { TERMINAL_STATUSES } from '../lib/statuses'
import AppIcon from './AppIcon.vue'
/* Le monogramme est importé, pas référencé depuis /public : Vite lui applique
   alors son empreinte de contenu, donc un remplacement du logo n'est jamais
   servi depuis un cache périmé. */
import logoMark from '../assets/logo-mark.png'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const theme = useThemeStore()

const isActive = (name) => route.name === name

// ---- Menu mobile ----
const menuOpen = ref(false)
watch(() => route.fullPath, () => { menuOpen.value = false })

// ---- Livraison en cours (client) : pastille de suivi dans la barre ----
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
  else if (auth.isClient && auth.driverSlug) router.push({ name: 'ai-assistant', params: { slug: auth.driverSlug } })
  else router.push({ name: 'landing' })
}

/** Le client revient chez son livreur, pas sur une page d'accueil générique. */
function goDriverPage() {
  if (auth.driverSlug) {
    router.push({ name: 'driver-public', params: { slug: auth.driverSlug } })
  }
}

const brandInitial = computed(() => auth.driver?.brand_name?.trim()?.[0]?.toUpperCase() || '·')

/**
 * Le livreur navigue par la sidebar (barre basse sur mobile), comme dans le
 * prototype. Reprendre ses entrées dans le bandeau donnait deux navigations
 * concurrentes pour les mêmes pages ; le bandeau ne garde donc que les
 * onglets d'espace, et « Notifications » a rejoint la sidebar.
 *
 * L'accueil n'en a pas non plus : le choix de l'espace s'y fait par les deux
 * grandes cartes, un onglet en doublon juste au-dessus les affaiblirait.
 */
const hasNav = computed(() => !auth.isDriver && route.name !== 'landing')

/** Le burger n'a de sens que s'il y a quelque chose derrière. */
const hasMenu = computed(() => hasNav.value || auth.isAuthenticated)

async function onLogout() {
  const slug = auth.driverSlug
  const wasClient = auth.isClient
  await auth.logout()
  // Un client déconnecté retourne chez son livreur : c'est son seul point d'entrée.
  if (wasClient && slug) {
    router.push({ name: 'driver-public', params: { slug } })
  } else {
    router.push({ name: 'landing' })
  }
}
</script>

<template>
  <header class="banner">
    <button class="logo" @click="goHome" aria-label="Accueil Allo Delivery">
      <img class="mark" :src="logoMark" alt="" width="32" height="32" />
      <span class="logo-text">Allo<span class="accent">Delivery</span></span>
    </button>

    <!-- Burger : replie la navigation sur les petits écrans. Inutile quand le
         bandeau n'a ni onglet ni action à replier (accueil d'un visiteur). -->
    <button
      v-if="hasMenu"
      class="burger icon-btn"
      :aria-expanded="menuOpen"
      :aria-label="menuOpen ? 'Fermer le menu' : 'Ouvrir le menu'"
      @click="menuOpen = !menuOpen"
    >
      <AppIcon :name="menuOpen ? 'close' : 'menu'" />
    </button>

    <!-- Navigation : rail segmenté sur desktop, panneau déroulant sur mobile -->
    <nav class="nav" :class="{ 'nav--open': menuOpen, 'nav--rail': hasNav }">
      <template v-if="hasNav">
        <!-- Visiteur : seul le livreur a un espace global sur la plateforme.
             Le client, lui, entre par le lien ou le QR code de son livreur —
             l'onglet client n'apparaît donc que si l'on connaît ce livreur. -->
        <template v-if="!auth.isAuthenticated">
          <RouterLink
            v-if="auth.driverSlug"
            class="nav-btn"
            :class="{ active: isActive('login') || isActive('register') }"
            :to="{ name: 'login', params: { slug: auth.driverSlug } }"
          >
            Espace client
          </RouterLink>
          <RouterLink
            class="nav-btn"
            :class="{ active: isActive('login-driver') || isActive('register-driver') }"
            :to="{ name: 'login-driver' }"
          >
            Espace livreur
          </RouterLink>
        </template>

        <!-- Client connecté : rattaché à un livreur -->
        <template v-else-if="auth.isClient">
          <RouterLink
            v-if="auth.driverSlug"
            class="nav-btn"
            :class="{ active: isActive('ai-assistant') }"
            :to="{ name: 'ai-assistant', params: { slug: auth.driverSlug } }"
          >
            Commander
          </RouterLink>
          <button
            v-if="auth.driverSlug"
            class="nav-btn"
            :class="{ active: isActive('driver-public') }"
            @click="goDriverPage"
          >
            Chez mon livreur
          </button>
        </template>
      </template>

      <!-- Actions repliées dans le menu sur mobile -->
      <div class="nav-actions">
        <span v-if="auth.isAuthenticated" class="small muted user-name">{{ auth.user?.name }}</span>
        <button v-if="auth.isAuthenticated" class="btn-outline logout-btn" @click="onLogout">Déconnexion</button>
      </div>
    </nav>

    <div class="spacer"></div>

    <!-- Marque du livreur auquel le client est rattaché -->
    <button
      v-if="auth.isClient && auth.driver?.brand_name"
      class="brand-chip"
      title="Page de mon livreur"
      @click="goDriverPage"
    >
      <span class="brand-mark">{{ brandInitial }}</span>
      <span class="brand-name">{{ auth.driver.brand_name }}</span>
    </button>

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

    <button
      class="icon-btn"
      :aria-label="theme.isDark ? 'Passer en mode clair' : 'Passer en mode sombre'"
      @click="theme.toggle()"
    >
      <AppIcon :name="theme.isDark ? 'sun' : 'moon'" />
    </button>

    <div class="desktop-actions">
      <template v-if="auth.isAuthenticated">
        <span class="small muted">{{ auth.user?.name }}</span>
        <button class="btn-outline" @click="onLogout">Déconnexion</button>
      </template>
    </div>
  </header>
</template>

<style scoped>
.desktop-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

/* Le logo ne se coupe jamais sur deux lignes, même quand la barre est serrée. */
.logo {
  flex: none;
  white-space: nowrap;
}

/* Les actions du menu ne servent qu'au repli mobile */
.nav-actions {
  display: none;
}

.burger {
  display: none;
}

.brand-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.75rem 0.375rem 0.375rem;
  border-radius: 62.4375rem;
  border: 0.0625rem solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-weight: 700;
  font-size: 0.82rem;
  cursor: pointer;
  transition: border-color 0.2s;
  max-width: 12.5rem;
}
.brand-chip:hover {
  border-color: var(--green);
}
.brand-mark {
  flex: none;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  background: var(--green);
  color: var(--green-ink);
  display: grid;
  place-items: center;
  font-weight: 800;
  font-size: 0.8rem;
}
.brand-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tracking-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5625rem 0.875rem;
  border-radius: 0.6875rem;
  border: 0.0625rem solid var(--border);
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
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  background: var(--green);
  animation: chip-pulse 2s infinite;
}
@keyframes chip-pulse {
  0% { box-shadow: 0 0 0 0 rgba(34, 197, 111, 0.55); }
  70% { box-shadow: 0 0 0 0.5rem rgba(34, 197, 111, 0); }
  100% { box-shadow: 0 0 0 0 rgba(34, 197, 111, 0); }
}

/* ===== Mobile : la navigation se replie sous la barre ===== */
@media (max-width: 768px) {
  .burger {
    display: grid;
    place-items: center;
    order: 3;
  }

  .desktop-actions {
    display: none;
  }

  .banner .nav {
    display: none;
  }

  /* Déplié, le menu n'est plus un rail segmenté mais un panneau : il faut
     défaire le fond et la bordure du rail, sinon la boîte du desktop se
     retrouve dessinée à l'intérieur du panneau.
     Sélecteur préfixé par `.banner` pour passer devant `.banner .nav--rail`,
     de même spécificité — l'ordre des feuilles n'est pas garanti au build. */
  .banner .nav--open {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.125rem;
    position: absolute;
    top: 4rem;
    left: 0;
    right: 0;
    padding: 0.625rem;
    margin: 0;
    background: var(--bg);
    border: none;
    border-radius: 0;
    border-bottom: 0.0625rem solid var(--border);
    box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.18);
  }

  .nav--open .nav-btn {
    text-align: left;
    padding: 0.75rem 0.875rem;
    font-size: 0.875rem;
  }

  .nav-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 0.5rem;
    padding-top: 0.75rem;
    border-top: 0.0625rem solid var(--border);
  }

  .brand-name {
    display: none;
  }

  .brand-chip {
    padding: 0.375rem;
  }

  .tracking-chip {
    font-size: 0.74rem;
    padding: 0.5rem 0.625rem;
  }
}

@media (max-width: 520px) {
  .logo-text {
    display: none;
  }
}
</style>
