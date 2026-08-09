<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api/axios'
import { apiError } from '../api/axios'
import { STATUS, TERMINAL_STATUSES } from '../lib/statuses'
import DriverSidebar from '../components/driver/DriverSidebar.vue'
import RequestCard from '../components/driver/RequestCard.vue'

/**
 * Demandes reçues (livreur) — GET /delivery-requests paginé ({ data, meta }).
 * Filtres par statut appliqués côté client sur les demandes chargées.
 */
const items = ref([])
const meta = ref(null)
const loading = ref(true)
const loadingMore = ref(false)
const error = ref('')

const tabs = [
  { key: 'all', label: 'Toutes' },
  { key: 'pending', label: 'En attente' },
  { key: 'active', label: 'En cours' },
  { key: 'done', label: 'Terminées' },
]
const activeTab = ref('all')

const PENDING = [STATUS.EN_ATTENTE, STATUS.PRIX_PROPOSE]
const ACTIVE = [STATUS.CONFIRMEE, STATUS.COLIS_RECUPERE, STATUS.EN_LIVRAISON]
const DONE = TERMINAL_STATUSES

// Compteurs par onglet (pile du header + badges des onglets).
const counts = computed(() => ({
  all: items.value.length,
  pending: items.value.filter((r) => PENDING.includes(r.status)).length,
  active: items.value.filter((r) => ACTIVE.includes(r.status)).length,
  done: items.value.filter((r) => DONE.includes(r.status)).length,
}))

const filtered = computed(() => {
  if (activeTab.value === 'all') return items.value
  const set =
    activeTab.value === 'pending' ? PENDING
    : activeTab.value === 'active' ? ACTIVE
    : DONE
  return items.value.filter((r) => set.includes(r.status))
})

const canLoadMore = computed(() => {
  return meta.value && meta.value.current_page < meta.value.last_page
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/delivery-requests', { params: { page: 1 } })
    items.value = res.data.data ?? res.data ?? []
    meta.value = res.data.meta ?? null
  } catch (err) {
    error.value = apiError(err)
  } finally {
    loading.value = false
  }
}

async function loadMore() {
  if (loadingMore.value || !canLoadMore.value) return
  loadingMore.value = true
  try {
    const res = await api.get('/delivery-requests', {
      params: { page: (meta.value?.current_page || 1) + 1 },
    })
    items.value = [...items.value, ...(res.data.data ?? [])]
    meta.value = res.data.meta ?? null
  } catch (err) {
    error.value = apiError(err)
  } finally {
    loadingMore.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="driver-layout">
    <DriverSidebar />

    <main class="driver-main">
      <div class="flex-between wrap mb-16">
        <div>
          <h2>Demandes reçues</h2>
          <p class="muted small">Accepte, refuse ou propose un prix. Tu ne vois que les demandes qui te sont adressées.</p>
        </div>
        <span class="pill-pending">Demandes : {{ items.length }}</span>
      </div>

      <!-- Onglets de filtre -->
      <div class="tabs wrap">
        <button
          v-for="t in tabs"
          :key="t.key"
          class="btn tab"
          :class="activeTab === t.key ? 'btn-primary' : 'btn-ghost'"
          @click="activeTab = t.key"
        >
          {{ t.label }}
          <span v-if="counts[t.key] > 0" class="tab-count">{{ counts[t.key] }}</span>
        </button>
      </div>

      <p v-if="!loading && activeTab !== 'all'" class="small faint mt-8">
        Filtre appliqué sur les {{ filtered.length }} demande{{ filtered.length > 1 ? 's' : '' }} chargée{{ filtered.length > 1 ? 's' : '' }}.
      </p>

      <!-- Erreur -->
      <div v-if="error" class="card mt-16">
        <h3>Impossible de charger les demandes</h3>
        <p class="muted small mt-8">{{ error }}</p>
        <button class="btn btn-outline mt-16" @click="load()">Réessayer</button>
      </div>

      <!-- Squelettes -->
      <div v-else-if="loading" class="flex-col mt-16">
        <div v-for="i in 4" :key="i" class="skeleton skel-card"></div>
      </div>

      <!-- Liste -->
      <template v-else>
        <div v-if="filtered.length" class="flex-col mt-16">
          <RequestCard v-for="r in filtered" :key="r.id" :request="r" arrow />
        </div>

        <div v-else class="card card-soft mt-16 empty">
          <p class="muted">Aucune demande {{ activeTab === 'all' ? '' : 'dans cette catégorie' }} pour le moment.</p>
          <p v-if="activeTab === 'all'" class="faint small mt-8">
            Quand un client créera une demande sur votre page publique, elle apparaîtra ici avec son statut.
          </p>
        </div>

        <!-- Charger plus -->
        <div v-if="canLoadMore && filtered.length" class="mt-16 center">
          <button class="btn btn-outline" :disabled="loadingMore" @click="loadMore()">
            {{ loadingMore ? 'Chargement…' : 'Charger plus' }}
          </button>
        </div>
      </template>
    </main>
  </div>
</template>

<style scoped>
.tabs {
  display: flex;
  gap: 0.5rem;
}
.tab {
  border-radius: 62.4375rem;
  padding: 0.5rem 1rem;
  display: inline-flex;
  align-items: center;
  gap: 0.4375rem;
}
.tab-count {
  background: var(--surface-3);
  color: var(--fg-2);
  border-radius: 62.4375rem;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.375rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.6875rem;
  font-weight: 800;
}
.btn-primary .tab-count {
  background: rgba(4, 20, 11, 0.18);
  color: var(--green-ink);
}
.pill-pending {
  background: var(--green);
  color: var(--green-ink);
  border-radius: 62.4375rem;
  padding: 0.5rem 1rem;
  font-size: 0.8125rem;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  white-space: nowrap;
}
.skel-card {
  height: 8.125rem;
}
.empty {
  text-align: center;
  padding: 2.25rem 1.25rem;
}
.center {
  text-align: center;
}
</style>
