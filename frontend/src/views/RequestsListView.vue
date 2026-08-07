<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api/axios'
import { apiError } from '../api/axios'
import { STATUS, TERMINAL_STATUSES } from '../lib/statuses'
import RequestCard from '../components/driver/RequestCard.vue'

/**
 * Demandes reçues (livreur) — GET /delivery-requests paginé.
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
  <div class="page container">
    <div class="mb-16">
      <h1>Demandes reçues</h1>
      <p class="muted small">Toutes les demandes de livraison qui vous sont adressées.</p>
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
        <RequestCard v-for="r in filtered" :key="r.id" :request="r" />
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
          {{ loadingMore ? 'Chargement…' : 'Charger plus de demandes' }}
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.tabs {
  display: flex;
  gap: 8px;
}
.tab {
  border-radius: 999px;
  padding: 8px 16px;
}
.skel-card {
  height: 130px;
}
.empty {
  text-align: center;
  padding: 36px 20px;
}
.center {
  text-align: center;
}
</style>
