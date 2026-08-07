<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api, { apiError } from '../api/axios'
import { formatPrice, formatDateTime } from '../lib/statuses'
import StatusBadge from '../components/StatusBadge.vue'
import StatusTimeline from '../components/StatusTimeline.vue'

const route = useRoute()
const auth = useAuthStore()
const token = computed(() => route.params.privateToken)

const tracking = ref(null)
const loading = ref(true)
const notFound = ref(false)
const errorMsg = ref('')

let pollTimer = null

async function fetchTracking() {
  const { data } = await api.get(`/tracking/${token.value}`)
  return data
}

function startPolling() {
  stopPolling()
  pollTimer = setInterval(async () => {
    try {
      const { data } = await api.get(`/tracking/${token.value}`)
      tracking.value = data
    } catch {
      // silently ignore poll errors
    }
  }, 4000)
}

function stopPolling() {
  if (pollTimer) clearInterval(pollTimer)
  pollTimer = null
}

onMounted(() => {
  loadTracking()
})

onBeforeUnmount(() => {
  stopPolling()
})

async function loadTracking() {
  loading.value = true
  notFound.value = false
  errorMsg.value = ''
  try {
    const { data } = await api.get(`/tracking/${token.value}`)
    tracking.value = data
    startPolling()
  } catch (err) {
    if (err.response?.status === 404) {
      notFound.value = true
    } else {
      errorMsg.value = apiError(err, 'Erreur lors du chargement du suivi.')
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <!-- LOADING -->
  <div v-if="loading" class="flex-col" style="gap: 16px; padding-top: 48px">
    <div class="skeleton" style="width: 200px; height: 28px"></div>
    <div class="skeleton" style="width: 120px; height: 24px"></div>
    <div class="skeleton" style="width: 100%; height: 200px; margin-top: 24px"></div>
  </div>

  <!-- 404 -->
  <div v-else-if="notFound" class="not-found">
    <h2>Lien de suivi invalide</h2>
    <p class="muted mt-8">Ce lien de suivi n'existe pas ou a expiré.</p>
    <router-link class="btn btn-primary mt-16" :to="{ name: 'landing' }">
      Retour à l'accueil
    </router-link>
  </div>

  <!-- ERROR -->
  <div v-else-if="errorMsg" class="card" style="text-align: center; padding: 32px">
    <p class="error-msg">{{ errorMsg }}</p>
  </div>

  <!-- TRACKING -->
  <div v-else-if="tracking" class="tracking-page">
    <!-- Header -->
    <div class="tracking-header">
      <div>
        <h2>Suivi de livraison</h2>
        <span class="bold small mt-8" style="display: block">
          #{{ tracking.tracking_number }}
        </span>
      </div>
      <StatusBadge :status="tracking.status" />
    </div>

    <!-- Timeline -->
    <div class="card mt-16">
      <h3 class="mb-16">Progression</h3>
      <StatusTimeline
        :history="tracking.timeline || []"
        :current="tracking.status"
      />
    </div>

    <!-- Infos -->
    <div class="grid-2 mt-16">
      <!-- Client / Expéditeur -->
      <div class="card">
        <h4 class="mb-16">📤 Expéditeur</h4>
        <div v-if="tracking.client" class="flex-col" style="gap: 6px">
          <span class="bold small">{{ tracking.client.name }}</span>
          <span class="small muted">{{ tracking.client.phone }}</span>
        </div>
        <span v-else class="small faint">Non renseigné</span>
      </div>

      <!-- Livreur -->
      <div class="card">
        <h4 class="mb-16">🛵 Livreur</h4>
        <div v-if="tracking.driver" class="flex-col" style="gap: 6px">
          <span class="bold small">{{ tracking.driver.brand_name || tracking.driver.name }}</span>
          <span class="small muted">{{ tracking.driver.phone }}</span>
        </div>
        <span v-else class="small faint">Non assigné</span>
      </div>
    </div>

    <!-- Adresses -->
    <div class="card mt-16">
      <h4 class="mb-16">📍 Adresses</h4>
      <div class="flex-col" style="gap: 10px">
        <div>
          <span class="faint small">Ramassage</span>
          <p class="small bold">{{ tracking.pickup_address }}</p>
        </div>
        <div class="divider" style="margin: 4px 0"></div>
        <div>
          <span class="faint small">Livraison</span>
          <p class="small bold">{{ tracking.delivery_address }}</p>
        </div>
      </div>
    </div>

    <!-- Service & Zone -->
    <div class="grid-2 mt-16">
      <div v-if="tracking.service" class="card">
        <h4>🛠️ Service</h4>
        <p class="small bold mt-8">{{ tracking.service.name }}</p>
      </div>
      <div v-if="tracking.delivery_zone" class="card">
        <h4>🗺️ Zone</h4>
        <p class="small bold mt-8">
          {{ tracking.delivery_zone.origin_zone }} → {{ tracking.delivery_zone.destination_zone }}
        </p>
      </div>
    </div>

    <!-- Dates -->
    <div class="card mt-16">
      <h4 class="mb-16">📅 Dates</h4>
      <div class="grid-3" style="gap: 16px">
        <div>
          <span class="faint small">Créée le</span>
          <p class="small">{{ formatDateTime(tracking.created_at) }}</p>
        </div>
        <div v-if="tracking.scheduled_at">
          <span class="faint small">Souhaitée</span>
          <p class="small">{{ formatDateTime(tracking.scheduled_at) }}</p>
        </div>
        <div v-if="tracking.picked_up_at">
          <span class="faint small">Récupérée</span>
          <p class="small">{{ formatDateTime(tracking.picked_up_at) }}</p>
        </div>
        <div v-if="tracking.delivered_at">
          <span class="faint small">Livrée</span>
          <p class="small bold" style="color: var(--brand)">{{ formatDateTime(tracking.delivered_at) }}</p>
        </div>
      </div>
    </div>

    <!-- Preuves -->
    <div v-if="tracking.proofs && tracking.proofs.length" class="card mt-16">
      <h4 class="mb-16">📸 Preuves de livraison</h4>
      <div class="proofs-grid">
        <div v-for="(proof, i) in tracking.proofs" :key="i" class="proof-item">
          <img
            :src="proof.file_url"
            :alt="proof.proof_type"
            class="proof-img"
            loading="lazy"
          />
          <span class="small muted" style="text-align: center">
            {{ proof.proof_type === 'pickup_photo' ? 'Récupération' : proof.proof_type === 'photo' ? 'Livraison' : proof.proof_type }}
          </span>
          <span v-if="proof.receiver_name" class="faint small" style="text-align: center">
            Reçu par : {{ proof.receiver_name }}
          </span>
        </div>
      </div>
    </div>

    <!-- Chat (lecture seule) -->
    <div class="card mt-16">
      <h4 class="mb-16">💬 Messages</h4>
      <div v-if="tracking.chat_messages && tracking.chat_messages.length" class="chat-read">
        <div
          v-for="(msg, i) in tracking.chat_messages"
          :key="i"
          class="chat-msg-read"
        >
          <span class="bold small">{{ msg.sender_name || 'Anonyme' }}</span>
          <p class="small muted">{{ msg.content }}</p>
          <span class="faint" style="font-size: 0.7rem">
            {{ formatDateTime(msg.created_at) }}
          </span>
        </div>
        <p v-if="!auth.isAuthenticated" class="small faint mt-8" style="text-align: center">
          <router-link :to="{ name: 'login' }">Connectez-vous</router-link> pour répondre aux messages.
        </p>
      </div>
      <p v-else class="small muted" style="text-align: center; padding: 16px 0">
        Aucun message pour le moment.
      </p>
    </div>
  </div>
</template>

<style scoped>
.not-found {
  text-align: center;
  padding: 64px 0;
}

.tracking-page {
  max-width: 740px;
  margin: 0 auto;
  padding-bottom: 48px;
}

.tracking-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

/* Preuves */
.proofs-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 14px;
}

.proof-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.proof-img {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border);
  background: var(--card-soft);
}

/* Chat lecture seule */
.chat-read {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: 300px;
  overflow-y: auto;
  padding: 4px;
}

.chat-msg-read {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 12px;
  background: var(--card-soft);
  border-radius: var(--radius-sm);
}

@media (max-width: 768px) {
  .tracking-header { flex-direction: column; }
}
</style>
