<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../api/axios'
import { usePolling } from '../../composables/usePolling'
import { useAuthStore } from '../../stores/auth'
import { TERMINAL_STATUSES, formatPrice } from '../../lib/statuses'

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
  return res.data.data
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

// ---- Revenus du jour (estimated sinon collected) ----
const dailyRevenue = computed(() => {
  const estimated = Number(dash.value.estimated_revenue ?? 0)
  const collected = Number(dash.value.collected_revenue ?? 0)
  const value = estimated > 0 ? estimated : collected
  return { value, hasData: (dash.value.estimated_revenue != null || dash.value.collected_revenue != null) }
})

const isMission = computed(() => route.name === 'driver-mission')
const isZones = computed(() => route.name === 'driver-profile' && route.query?.section === 'zones')

function goZones() {
  router.push({ name: 'driver-profile', query: { section: 'zones' } })
}

onMounted(() => {
  start()
  loadProfile()
})
</script>

<template>
  <aside class="driver-sidebar">
    <!-- Marque + présence -->
    <div class="sidebar-brand">
      <div class="brand-avatar">{{ brandLetter }}</div>
      <div class="brand-text">
        <div class="name">{{ brandName }}</div>
        <div class="sub">
          <span class="dot-online"></span>
          {{ brandCity ? `${brandCity} · ` : '' }}en ligne
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <button
      class="sidebar-link"
      :class="{ active: route.name === 'driver-dashboard' }"
      @click="router.push({ name: 'driver-dashboard' })"
    >
      <span class="side-icon">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 11 12 4l8 7" />
          <path d="M6 10v10h12V10" />
        </svg>
      </span>
      <span>Tableau de bord</span>
    </button>

    <button
      class="sidebar-link"
      :class="{ active: route.name === 'driver-requests' }"
      @click="router.push({ name: 'driver-requests' })"
    >
      <span class="side-icon">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 12h-6l-2 3h-4l-2-3H2" />
          <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
        </svg>
      </span>
      <span>Demandes</span>
      <span v-if="pendingCount > 0" class="counter">{{ pendingCount }}</span>
    </button>

    <button
      class="sidebar-link"
      :class="{ active: isMission }"
      :disabled="!canOpenMission"
      :title="canOpenMission ? 'Mission en cours' : 'Aucune mission active'"
      @click="canOpenMission && router.push(missionTarget)"
    >
      <span class="side-icon">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M1 3h15v13H1z" />
          <path d="M16 8h4l3 3v5h-7V8z" />
          <circle cx="5.5" cy="18.5" r="2.5" />
          <circle cx="18.5" cy="18.5" r="2.5" />
        </svg>
      </span>
      <span>Mission active</span>
    </button>

    <button
      class="sidebar-link"
      :class="{ active: route.name === 'driver-profile' && !isZones }"
      @click="router.push({ name: 'driver-profile' })"
    >
      <span class="side-icon">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="8" r="4" />
          <path d="M4 21c0-4 3.6-6 8-6s8 2 8 6" />
        </svg>
      </span>
      <span>Profil &amp; marque</span>
    </button>

    <button class="sidebar-link" :class="{ active: isZones }" @click="goZones()">
      <span class="side-icon">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 21s-7-6.1-7-11a7 7 0 1 1 14 0c0 4.9-7 11-7 11z" />
          <circle cx="12" cy="10" r="2.6" />
        </svg>
      </span>
      <span>Zones &amp; tarifs</span>
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
  </aside>
</template>

<style scoped>
/* --- Marque (prototype : avatar vert, pas de séparateur) --- */
.sidebar-brand {
  gap: 11px;
  padding: 8px 10px 16px;
  border-bottom: none;
  margin-bottom: 0;
}
.brand-avatar {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--green);
  color: var(--green-ink);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 20px;
  flex-shrink: 0;
}
.brand-text { min-width: 0; }
.sidebar-brand .name { font-size: 15px; }
.sidebar-brand .sub { display: flex; align-items: center; gap: 6px; }

/* --- Liens : icônes SVG --- */
.side-icon {
  display: flex;
  width: 20px;
  justify-content: center;
  flex-shrink: 0;
  color: var(--fg-3);
}
.sidebar-link.active .side-icon,
.sidebar-link:hover .side-icon { color: var(--green); }

.sidebar-link:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.sidebar-link .counter {
  margin-left: auto;
  min-width: 22px;
  text-align: center;
}

/* --- Pied : objectif de revenus --- */
.foot-label { font-size: 12px; color: var(--fg-2); font-weight: 700; }
.progress {
  height: 6px;
  border-radius: 4px;
  background: var(--surface-2);
  margin-top: 10px;
  overflow: hidden;
}
.progress-fill {
  height: 100%;
  background: var(--green);
  border-radius: 4px;
  transition: width 0.4s ease;
}
.foot-sub { font-size: 11px; color: var(--fg-3); margin-top: 6px; font-weight: 600; }
</style>
