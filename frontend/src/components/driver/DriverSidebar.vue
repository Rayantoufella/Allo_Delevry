<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../api/axios'
import { usePolling } from '../../composables/usePolling'
import { useAuthStore } from '../../stores/auth'
import { TERMINAL_STATUSES, formatPrice } from '../../lib/statuses'
import AppIcon from '../AppIcon.vue'

/**
 * Sidebar permanente de l'espace livreur (conforme au prototype UI).
 * Charge GET /dashboard (polling 10 s) pour :
 *  - le compteur vert "Demandes" (pending_requests)
 *  - l'état du lien "Mission active" (active_missions → id depuis recent_requests)
 *  - les revenus du jour (estimated_revenue / collected_revenue)
 */
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const { data, start } = usePolling(async () => {
  const res = await api.get('/dashboard')
  return res.data
}, 10000)

const dash = computed(() => data.value || {})

// ---- Marque : profil public (nom, ville, logo) ----
const profile = ref(null)

async function loadProfile() {
  try {
    const res = await api.get('/driver-profiles')
    const rows = res.data.data ?? res.data ?? []
    profile.value = rows[0] ?? null
  } catch {
    profile.value = null
  }
}

const brandName = computed(() => profile.value?.brand_name || auth.user?.name || 'Mon profil')
const brandCity = computed(() => profile.value?.city || '')
const brandLetter = computed(() => (brandName.value || 'A').trim().charAt(0).toUpperCase())
const isAvailable = computed(() => profile.value ? profile.value.is_available !== false : true)

// ---- Mission active : premier recent_request non terminal ----
const activeMission = computed(() => {
  const rows = dash.value.recent_requests || []
  return rows.find((r) => !TERMINAL_STATUSES.includes(r.status)) || null
})

const canOpenMission = computed(() => (dash.value.active_missions ?? 0) > 0)
const missionTarget = computed(() => {
  if (activeMission.value) {
    return { name: 'driver-mission', params: { id: activeMission.value.id } }
  }
  // Une mission active existe mais hors des 5 récentes : on retombe sur la liste.
  return { name: 'driver-requests' }
})

const pendingCount = computed(() => dash.value.pending_requests ?? 0)
const unreadCount = computed(() => dash.value.unread_notifications ?? 0)

// ---- Revenus du jour (estimated sinon collected) ----
const dailyRevenue = computed(() => {
  const estimated = Number(dash.value.estimated_revenue ?? 0)
  const collected = Number(dash.value.collected_revenue ?? 0)
  const value = estimated > 0 ? estimated : collected
  return { value, hasData: (dash.value.estimated_revenue != null || dash.value.collected_revenue != null) }
})

const isMission = computed(() => route.name === 'driver-mission')
const isZones = computed(() => route.name === 'driver-zones')

function goZones() {
  router.push({ name: 'driver-zones' })
}

onMounted(() => {
  start()
  loadProfile()
})
</script>

<template>
  <aside class="driver-sidebar">
    <div class="sidebar-sticky">
    <!-- Marque + présence -->
    <div class="sidebar-brand">
      <div class="brand-avatar">{{ brandLetter }}</div>
      <div class="brand-text">
        <div class="name">{{ brandName }}</div>
        <!-- `isAvailable` était calculé mais jamais lu : la marque s'affichait
             « en ligne » même pour un livreur en pause. -->
        <div class="sub">
          <span :class="isAvailable ? 'dot-online' : 'dot-paused'"></span>
          {{ brandCity ? `${brandCity} · ` : '' }}{{ isAvailable ? 'en ligne' : 'en pause' }}
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <button
      class="sidebar-link"
      :class="{ active: route.name === 'driver-dashboard' }"
      @click="router.push({ name: 'driver-dashboard' })"
    >
      <span class="side-icon"><AppIcon name="home" :size="19" /></span>
      <span class="side-label"><span class="lbl-long">Tableau de bord</span><span class="lbl-short">Accueil</span></span>
    </button>

    <button
      class="sidebar-link"
      :class="{ active: route.name === 'driver-requests' }"
      @click="router.push({ name: 'driver-requests' })"
    >
      <span class="side-icon"><AppIcon name="inbox" :size="19" /></span>
      <span class="side-label"><span class="lbl-long">Demandes</span><span class="lbl-short">Demandes</span></span>
      <span v-if="pendingCount > 0" class="counter">{{ pendingCount }}</span>
    </button>

    <button
      class="sidebar-link"
      :class="{ active: isMission }"
      :disabled="!canOpenMission"
      :title="canOpenMission ? 'Mission en cours' : 'Aucune mission active'"
      @click="canOpenMission && router.push(missionTarget)"
    >
      <span class="side-icon"><AppIcon name="truck" :size="19" /></span>
      <span class="side-label"><span class="lbl-long">Mission active</span><span class="lbl-short">Mission</span></span>
    </button>

    <!-- Les notifications vivaient dans le bandeau, en doublon de cette
         navigation. Elles rejoignent la sidebar, seul lieu de navigation de
         l'espace livreur (barre basse sur mobile). -->
    <button
      class="sidebar-link"
      :class="{ active: route.name === 'driver-notifications' }"
      @click="router.push({ name: 'driver-notifications' })"
    >
      <span class="side-icon"><AppIcon name="bell" :size="19" /></span>
      <span class="side-label"><span class="lbl-long">Notifications</span><span class="lbl-short">Notifs</span></span>
      <span v-if="unreadCount > 0" class="counter">{{ unreadCount }}</span>
    </button>

    <button
      class="sidebar-link"
      :class="{ active: route.name === 'driver-profile' && !isZones }"
      @click="router.push({ name: 'driver-profile' })"
    >
      <span class="side-icon"><AppIcon name="user" :size="19" /></span>
      <span class="side-label"><span class="lbl-long">Profil &amp; marque</span><span class="lbl-short">Profil</span></span>
    </button>

    <button class="sidebar-link" :class="{ active: isZones }" @click="goZones()">
      <span class="side-icon"><AppIcon name="map" :size="19" /></span>
      <span class="side-label"><span class="lbl-long">Zones &amp; tarifs</span><span class="lbl-short">Zones</span></span>
    </button>

    <!-- Revenus du jour -->
    <div v-if="dailyRevenue.hasData" class="sidebar-footer">
      <div class="foot-label">Revenus du jour</div>
      <div class="amount">{{ formatPrice(dailyRevenue.value) }}</div>
      <div class="progress">
        <div
          class="progress-fill"
          :style="{ width: (dailyRevenue.value > 0 ? Math.min(100, Math.round((dailyRevenue.value / 1850) * 100)) : 0) + '%' }"
        ></div>
      </div>
      <div class="foot-sub">
        <template v-if="dailyRevenue.value > 0">
          {{ Math.min(100, Math.round((dailyRevenue.value / 1850) * 100)) }}% de l'objectif (1 850 DH)
        </template>
        <template v-else>Objectif du jour : 1 850 DH</template>
      </div>
    </div>
    </div>
  </aside>
</template>

<style scoped>
/* --- Marque (prototype : avatar vert, pas de séparateur) --- */
.sidebar-brand {
  gap: 0.6875rem;
  padding: 0.5rem 0.625rem 1rem;
  border-bottom: none;
  margin-bottom: 0;
}
.brand-avatar {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.75rem;
  background: var(--green);
  color: var(--green-ink);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 1.25rem;
  flex-shrink: 0;
}
.brand-text { min-width: 0; }
/* Une marque longue tient sur deux lignes au plus, sans repousser le reste
   de la colonne. */
.sidebar-brand .name {
  font-size: 0.9375rem;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
/* La ligne de présence reste sur une seule ligne : dans les 240px de la colonne
   elle se cassait en deux et déséquilibrait le bloc de marque. */
.sidebar-brand .sub {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  min-width: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* --- Liens : icônes SVG ---
   L'icône prend la couleur de son entrée (`currentColor`) : la teinter en vert
   sur l'entrée active ajoutait un second signal par-dessus le fond `--surface`,
   là où le prototype n'en donne qu'un. */
.side-icon {
  display: flex;
  width: 1.25rem;
  justify-content: center;
  flex-shrink: 0;
}

.sidebar-link:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.sidebar-link .counter {
  margin-left: auto;
  min-width: 1.375rem;
  text-align: center;
}

/* Libellé court : réservé à la barre de navigation basse, où cinq entrées
   doivent tenir sans défilement sur un écran de 375 px. */
.lbl-short { display: none; }
@media (max-width: 560px) {
  .lbl-long { display: none; }
  .lbl-short { display: inline; }
}

/* --- Pied : objectif de revenus --- */
.foot-label { font-size: 0.75rem; color: var(--fg-2); font-weight: 700; }
.progress {
  height: 0.375rem;
  border-radius: 0.25rem;
  background: var(--surface-2);
  margin-top: 0.625rem;
  overflow: hidden;
}
.progress-fill {
  height: 100%;
  background: var(--green);
  border-radius: 0.25rem;
  transition: width 0.4s ease;
}
.foot-sub { font-size: 0.6875rem; color: var(--fg-3); margin-top: 0.375rem; font-weight: 600; }
</style>
