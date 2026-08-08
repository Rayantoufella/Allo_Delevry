<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api, { apiError } from '../api/axios'
import { formatPrice, formatDateTime, timeAgo } from '../lib/statuses'
import StatusBadge from '../components/StatusBadge.vue'
import StatusTimeline from '../components/StatusTimeline.vue'
import ImageLightbox from '../components/ImageLightbox.vue'
import { STATUS } from '../lib/statuses'

const route = useRoute()
const auth = useAuthStore()
const token = computed(() => route.params.privateToken)

const tracking = ref(null)
const lightbox = ref('')
const loading = ref(true)
const notFound = ref(false)
const errorMsg = ref('')

let pollTimer = null

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

/** Status message for the prototype-style state display */
function statusMessage(status) {
  const messages = {
    [STATUS.EN_ATTENTE]: "Demande créée — en attente de la proposition de prix du livreur.",
    [STATUS.PRIX_PROPOSE]: "Le livreur a proposé un prix. En attente de votre confirmation.",
    [STATUS.CONFIRMEE]: "Demande confirmée. Le livreur prépare la récupération du colis.",
    [STATUS.COLIS_RECUPERE]: "Colis récupéré par le livreur.",
    [STATUS.EN_LIVRAISON]: "En route vers vous !",
    [STATUS.LIVREE]: "Colis livré avec succès. Merci !",
  }
  return messages[status] || ''
}

const driverLabel = computed(() => {
  if (!tracking.value?.driver) return null
  const d = tracking.value.driver
  return d.brand_name ? `${d.brand_name} · livreur` : (d.name || 'Livreur')
})

const driverInitials = computed(() => {
  if (!tracking.value?.driver) return '?'
  const d = tracking.value.driver
  const name = d.brand_name || d.name || '?'
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2)
})

const proofTypeLabel = (type) => {
  const labels = { photo: 'Livraison', pickup_photo: 'Récupération', signature: 'Signature', ticket: 'Ticket', pickup_id_card: "Carte d'identité" }
  return labels[type] || type
}
</script>

<template>
  <!-- LOADING -->
  <div v-if="loading" class="flex-col" style="gap: 16px; padding-top: 48px; align-items: center">
    <div class="skeleton" style="width: 200px; height: 28px"></div>
    <div class="skeleton" style="width: 120px; height: 24px"></div>
    <div class="skeleton" style="width: 100%; max-width: 740px; height: 200px; margin-top: 24px"></div>
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
  <div v-else-if="errorMsg" class="card" style="text-align: center; padding: 32px; max-width: 500px; margin: 48px auto">
    <p class="error-msg">{{ errorMsg }}</p>
  </div>

  <!-- TRACKING -->
  <div v-else-if="tracking" class="tracking-page">
    <!-- Header -->
    <div class="tracking-header">
      <div>
        <div class="tracking-title-row">
          <h2 style="font-size: 1.75rem">Suivi privé</h2>
          <span class="tracking-code">#{{ tracking.tracking_number }}</span>
        </div>
        <StatusBadge :status="tracking.status" />
      </div>
    </div>

    <!-- Message d'état -->
    <div v-if="statusMessage(tracking.status)" class="status-msg">
      <p class="small bold" style="line-height: 1.5">{{ statusMessage(tracking.status) }}</p>
    </div>

    <!-- Livreur -->
    <div v-if="tracking.driver" class="driver-card">
      <div class="flex" style="gap: 12px; align-items: center">
        <div class="avatar-sm">{{ driverInitials }}</div>
        <div>
          <p class="bold small">{{ driverLabel }}</p>
          <div class="flex" style="gap: 4px; margin-top: 2px">
            <span class="dot-online"></span>
            <span class="small" style="color: var(--green)">en ligne</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Arrivée estimée (info placeholder) -->
    <div v-if="tracking.scheduled_at" class="est-card">
      <div class="flex" style="gap: 8px; align-items: center">
        <span style="font-size: 1.1rem">⏱️</span>
        <div>
          <span class="small bold">Arrivée estimée</span>
          <p class="small muted" style="margin-top: 2px">{{ formatDateTime(tracking.scheduled_at) }}</p>
        </div>
      </div>
    </div>

    <!-- Timeline -->
    <div class="card">
      <h3 style="margin-bottom: 16px">Étapes de la livraison</h3>
      <StatusTimeline
        :history="tracking.timeline || []"
        :current="tracking.status"
      />
    </div>

    <!-- Ticket de livraison (blurred background card) -->
    <div class="ticket-card">
      <h4 style="margin-bottom: 16px">Ticket de livraison</h4>
      <div class="ticket-rows">
        <div class="ticket-row">
          <span class="ticket-label">Suivi</span>
          <span class="ticket-value">#{{ tracking.tracking_number }}</span>
        </div>
        <div class="ticket-row">
          <span class="ticket-label">Service</span>
          <span class="ticket-value">{{ tracking.service?.name || '—' }}</span>
        </div>
        <div class="ticket-row">
          <span class="ticket-label">Destinataire</span>
          <span class="ticket-value">{{ tracking.recipient_name || '—' }}</span>
        </div>
        <div class="ticket-row">
          <span class="ticket-label">Livraison</span>
          <span class="ticket-value">{{ tracking.delivery_address }}</span>
        </div>
        <div class="ticket-row">
          <span class="ticket-label">Paiement</span>
          <span class="ticket-value">Espèces à la livraison</span>
        </div>
        <div class="ticket-divider"></div>
        <div class="ticket-row">
          <span class="ticket-label" style="font-weight: 800; color: var(--fg)">Total à encaisser</span>
          <span class="ticket-value" style="color: var(--green); font-size: 1.1rem">{{ tracking.amount_to_collect ? formatPrice(tracking.amount_to_collect) : '—' }}</span>
        </div>
      </div>
    </div>

    <!-- Code de remise -->
    <div class="code-card" v-if="tracking.confirmation_code || tracking.status === STATUS.LIVREE">
      <div class="code-label">Code de remise</div>
      <div class="code-big">{{ tracking.confirmation_code || '—' }}</div>
    </div>

    <!-- Preuves -->
    <div v-if="tracking.proofs && tracking.proofs.length" class="card">
      <h4 style="margin-bottom: 16px">📸 Preuves de livraison</h4>
      <div class="proofs-grid">
        <div v-for="(proof, i) in tracking.proofs" :key="i" class="proof-item">
          <img
            :src="proof.file_url"
            :alt="proof.proof_type"
            class="proof-img zoomable"
            loading="lazy"
            title="Agrandir"
            @click="lightbox = proof.file_url"
          />
          <span class="small muted" style="text-align: center">
            {{ proofTypeLabel(proof.proof_type) }}
          </span>
          <span v-if="proof.receiver_name" class="faint small" style="text-align: center">
            Reçu par : {{ proof.receiver_name }}
          </span>
        </div>
      </div>
    </div>

    <!-- Chat (lecture seule) -->
    <div class="card">
      <h4 style="margin-bottom: 16px">💬 Messages</h4>
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

    <ImageLightbox v-if="lightbox" :src="lightbox" alt="Preuve" @close="lightbox = ''" />
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
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.tracking-page > .card,
.tracking-page > .ticket-card,
.tracking-page > .code-card,
.tracking-page > .driver-card,
.tracking-page > .est-card {
  margin-top: 0;
}

.tracking-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.tracking-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.tracking-code {
  display: inline-flex;
  align-items: center;
  padding: 9px 14px;
  border-radius: 11px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-weight: 700;
  font-size: 13px;
  font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
}

/* Status message */
.status-msg {
  padding: 14px 18px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
}

/* Driver card */
.driver-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 16px 18px;
}

/* Estimated arrival */
.est-card {
  padding: 14px 18px;
  background: color-mix(in srgb, var(--green) 8%, var(--surface));
  border: 1px solid color-mix(in srgb, var(--green) 20%, var(--border));
  border-radius: 12px;
}

/* Ticket card */
.ticket-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 18px 22px;
}

.ticket-rows {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.ticket-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.ticket-label {
  font-size: 0.82rem;
  color: var(--fg-2);
  font-weight: 600;
}

.ticket-value {
  font-size: 0.82rem;
  font-weight: 800;
}

.ticket-divider {
  height: 1px;
  background: var(--border);
  margin: 4px 0;
}

/* Code de remise */
.code-card {
  background: color-mix(in srgb, var(--green) 10%, var(--surface));
  border: 1px solid color-mix(in srgb, var(--green) 20%, var(--border));
  border-radius: 16px;
  padding: 22px;
  text-align: center;
}

.code-label {
  font-size: 13px;
  font-weight: 700;
  color: var(--fg-2);
  margin-bottom: 8px;
}

.code-big {
  font-size: 2.4rem;
  font-weight: 800;
  color: var(--green);
  letter-spacing: 0.12em;
  font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
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
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--surface-2);
}
.zoomable { cursor: zoom-in; }

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
  background: var(--surface-2);
  border-radius: 10px;
}

@media (max-width: 768px) {
  .tracking-header { flex-direction: column; }
}
</style>
