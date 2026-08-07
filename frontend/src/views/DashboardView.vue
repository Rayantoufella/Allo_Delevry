<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api/axios'
import { apiError } from '../api/axios'
import { usePolling } from '../composables/usePolling'
import { formatPrice, timeAgo } from '../lib/statuses'
import StatCard from '../components/driver/StatCard.vue'
import RequestCard from '../components/driver/RequestCard.vue'

/**
 * Tableau de bord livreur — GET /dashboard (wrapper { success, data }) + polling 10 s.
 */
const router = useRouter()

const { data, loading, error, start } = usePolling(async () => {
  const res = await api.get('/dashboard')
  return res.data.data
}, 10000)

const dash = computed(() => data.value || {})

// Profil public : bandeau d'incitation si aucun profil créé.
const profiles = ref([])
const profileLoading = ref(true)

async function loadProfiles() {
  try {
    const res = await api.get('/driver-profiles')
    profiles.value = res.data.data ?? res.data ?? []
  } catch {
    profiles.value = []
  } finally {
    profileLoading.value = false
  }
}

const hasProfile = computed(() => profiles.value.length > 0)
const hasError = computed(() => !!error.value && !data.value)

onMounted(() => {
  start()
  loadProfiles()
})
</script>

<template>
  <div class="page container">
    <div class="flex-between wrap mb-16">
      <div>
        <h1>Tableau de bord</h1>
        <p class="muted small">Vue d'ensemble de votre activité de livraison.</p>
      </div>
      <RouterLink class="btn btn-primary" :to="{ name: 'driver-requests' }">Voir toutes les demandes</RouterLink>
    </div>

    <!-- Bandeau profil manquant -->
    <div v-if="!profileLoading && !hasProfile" class="card banner-profil mb-16">
      <div class="flex-between wrap">
        <div>
          <h3>Créez votre profil public ✨</h3>
          <p class="muted small">Votre marque, vos services et vos zones : les clients vous trouvent et commandent via votre page publique et votre QR code.</p>
        </div>
        <button class="btn btn-primary" @click="router.push({ name: 'driver-profile' })">Configurer mon profil</button>
      </div>
    </div>

    <!-- Squelettes -->
    <template v-if="loading && !data">
      <div class="stats">
        <div v-for="i in 6" :key="i" class="skeleton stat-skel"></div>
      </div>
    </template>

    <!-- Erreur initiale -->
    <div v-else-if="hasError" class="card">
      <h3>Impossible de charger le tableau de bord</h3>
      <p class="muted small mt-8">{{ apiError(error, 'Erreur de chargement.') }}</p>
      <button class="btn btn-outline mt-16" @click="start()">Réessayer</button>
    </div>

    <template v-else>
      <!-- Cartes stats -->
      <div class="stats">
        <StatCard label="Demandes reçues" :value="dash.total_requests ?? '—'" icon="📥" />
        <StatCard label="Missions actives" :value="dash.active_missions ?? '—'" icon="🛵" accent="green" />
        <StatCard label="En attente" :value="dash.pending_requests ?? '—'" icon="⏳" accent="yellow" sub="Nouvelles demandes à traiter" />
        <StatCard label="Livrées" :value="dash.delivered_missions ?? '—'" icon="🏁" accent="green" />
        <StatCard label="Revenus estimés" :value="formatPrice(dash.estimated_revenue)" icon="💰" />
        <StatCard label="Encaissés" :value="formatPrice(dash.collected_revenue)" icon="💵" accent="green" />
        <StatCard
          label="Note moyenne"
          :value="dash.average_rating != null ? `⭐ ${dash.average_rating}/5` : '—'"
          icon="⭐"
          accent="yellow"
          :sub="dash.average_rating != null ? 'Basée sur les avis clients' : 'Aucun avis pour le moment'"
        />
        <StatCard
          label="Notifications"
          :value="dash.unread_notifications ?? 0"
          icon="🔔"
          :accent="(dash.unread_notifications ?? 0) > 0 ? 'red' : ''"
          :sub="(dash.unread_notifications ?? 0) > 0 ? 'Non lues' : 'Tout est à jour'"
        />
      </div>

      <!-- Notifications non lues -->
      <div v-if="(dash.unread_notifications ?? 0) > 0" class="mt-16">
        <button class="btn btn-outline" @click="router.push({ name: 'driver-notifications' })">
          🔔 {{ dash.unread_notifications }} notification{{ dash.unread_notifications > 1 ? 's' : '' }} non lue{{ dash.unread_notifications > 1 ? 's' : '' }} — voir
        </button>
      </div>

      <div class="grid-2 mt-24">
        <!-- Demandes récentes -->
        <section>
          <div class="flex-between mb-16">
            <h3>Demandes récentes</h3>
            <RouterLink class="small" :to="{ name: 'driver-requests' }">Tout voir →</RouterLink>
          </div>
          <div v-if="(dash.recent_requests || []).length" class="flex-col">
            <RequestCard v-for="r in dash.recent_requests" :key="r.id" :request="r" />
          </div>
          <div v-else class="card card-soft">
            <p class="muted small">Aucune demande pour le moment. Vos nouvelles demandes apparaîtront ici.</p>
          </div>
        </section>

        <!-- Messages récents -->
        <section>
          <div class="flex-between mb-16">
            <h3>Messages récents</h3>
            <RouterLink class="small" :to="{ name: 'driver-requests' }">Voir les demandes →</RouterLink>
          </div>
          <div v-if="(dash.recent_messages || []).length" class="flex-col">
            <div v-for="m in dash.recent_messages" :key="m.id" class="card msg">
              <div class="flex-between">
                <span class="bold small">{{ m.sender_name || 'Client' }}</span>
                <span class="faint small">{{ timeAgo(m.created_at) }}</span>
              </div>
              <p class="small muted mt-8">{{ m.content }}</p>
            </div>
          </div>
          <div v-else class="card card-soft">
            <p class="muted small">Aucun message récent. Le chat des demandes apparaîtra ici.</p>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(215px, 1fr));
  gap: 14px;
}
.stat-skel {
  height: 108px;
}
.banner-profil {
  border: 1px dashed var(--border-strong);
  background: var(--card-soft);
}
.msg p {
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
</style>
