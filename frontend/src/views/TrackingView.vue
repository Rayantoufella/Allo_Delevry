<script setup>
import AppIcon from "../components/AppIcon.vue"
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
  <div v-if="loading" class="flex-col" style="gap: 1rem; padding-top: 3rem; align-items: center">
    <div class="skeleton" style="width: 12.5rem; height: 1.75rem"></div>
    <div class="skeleton" style="width: 7.5rem; height: 1.5rem"></div>
    <div class="skeleton" style="width: 100%; max-width: 46.25rem; height: 12.5rem; margin-top: 1.5rem"></div>
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
  <div v-else-if="errorMsg" class="card" style="text-align: center; padding: 2rem; max-width: 31.25rem; margin: 3rem auto">
    <p class="error-msg">{{ errorMsg }}</p>
  </div>

  <!-- TRACKING -->
  <div v-else-if="tracking" class="tracking-page">
    <!-- En-tête : identité de la course à gauche, échéance à droite -->
    <div class="tracking-header">
      <div>
        <div class="small muted" style="font-weight: 700">Suivi privé</div>
        <div class="tracking-title-row">
          <h2 style="font-size: 1.75rem">#{{ tracking.tracking_number }}</h2>
          <StatusBadge :status="tracking.status" />
        </div>
      </div>
      <div v-if="tracking.scheduled_at" class="tracking-eta">
        <div class="small muted" style="font-weight: 700">Arrivée estimée</div>
        <div class="tracking-eta-value">{{ formatDateTime(tracking.scheduled_at) }}</div>
      </div>
    </div>

    <!-- Deux colonnes : le fil de la course à gauche, ses pièces à droite. -->
    <div class="tracking-grid">
      <div class="tracking-col">
        <!-- Message d'état -->
        <div v-if="statusMessage(tracking.status)" class="status-msg">
          <p class="small bold" style="line-height: 1.5">{{ statusMessage(tracking.status) }}</p>
        </div>

        <!-- Livreur -->
        <div v-if="tracking.driver" class="driver-card">
          <div class="flex" style="gap: 0.75rem; align-items: center">
            <div class="avatar-sm">{{ driverInitials }}</div>
            <div>
              <p class="bold small">{{ driverLabel }}</p>
              <div class="flex" style="gap: 0.25rem; margin-top: 0.125rem">
                <span class="dot-online"></span>
                <span class="small" style="color: var(--green)">en ligne</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Preuves -->
        <div v-if="tracking.proofs && tracking.proofs.length" class="card">
          <h4 style="margin-bottom: 1rem"><AppIcon name="camera" :size="18" /> Preuves de livraison</h4>
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
          <h4 style="margin-bottom: 1rem"><AppIcon name="chat" :size="18" /> Messages</h4>
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
              <template v-if="auth.driverSlug">
                <router-link :to="{ name: 'login', params: { slug: auth.driverSlug } }">
                  Connectez-vous
                </router-link>
                pour répondre aux messages.
              </template>
              <template v-else>
                Ouvrez le lien de votre livreur pour vous connecter et répondre aux messages.
              </template>
            </p>
          </div>
          <p v-else class="small muted" style="text-align: center; padding: 1rem 0">
            Aucun message pour le moment.
          </p>
        </div>
      </div>

      <!-- Colonne des pièces : étapes, ticket, code de remise -->
      <div class="tracking-col">
        <div class="card">
          <h3 style="margin-bottom: 1rem">Étapes de la livraison</h3>
          <StatusTimeline
            :history="tracking.timeline || []"
            :current="tracking.status"
          />
        </div>

        <!-- Ticket de livraison -->
        <div class="ticket-card">
          <h4 style="margin-bottom: 1rem">Ticket de livraison</h4>
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
      </div>
    </div>

    <ImageLightbox v-if="lightbox" :src="lightbox" alt="Preuve" @close="lightbox = ''" />
  </div>
</template>

<style scoped>
.not-found {
  text-align: center;
  padding: 4rem 0;
}

/* Le suivi occupe toute la colonne de l'espace client : le prototype le compose
   en deux colonnes (1.5fr / 1fr), pas en un ruban de cartes empilées. */
.tracking-page {
  padding-bottom: 3rem;
  display: flex;
  flex-direction: column;
  gap: 1.125rem;
}

.tracking-grid {
  display: grid;
  grid-template-columns: 1.5fr minmax(0, 1fr);
  gap: 1.125rem;
  align-items: start;
}

/* `min-width: 0` sur les colonnes : sans lui, une longue adresse dans le ticket
   impose sa largeur intrinsèque à la piste et déforme toute la grille. */
.tracking-col {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.tracking-col > .card,
.tracking-col > .ticket-card,
.tracking-col > .code-card,
.tracking-col > .driver-card {
  margin-top: 0;
}

.tracking-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
}

/* L'échéance était une carte pleine largeur ; le prototype la pose en regard du
   numéro de suivi, à droite de l'en-tête. */
.tracking-eta {
  text-align: right;
}

.tracking-eta-value {
  font-size: 1.375rem;
  font-weight: 800;
  color: var(--green);
  line-height: 1.2;
}

/* Une colonne dès que la grille n'a plus la place de tenir ses deux pistes. */
@media (max-width: 900px) {
  .tracking-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

.tracking-title-row {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  flex-wrap: wrap;
}

.tracking-code {
  display: inline-flex;
  align-items: center;
  padding: 0.5625rem 0.875rem;
  border-radius: 0.6875rem;
  border: 0.0625rem solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-weight: 700;
  font-size: 0.8125rem;
  font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
}

/* Status message */
.status-msg {
  padding: 0.875rem 1.125rem;
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  border-radius: 0.75rem;
}

/* Driver card */
.driver-card {
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  border-radius: 1rem;
  padding: 1rem 1.125rem;
}

/* Ticket card */
.ticket-card {
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  border-radius: 1rem;
  padding: 1.125rem 1.375rem;
}

.ticket-rows {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
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
  height: 0.0625rem;
  background: var(--border);
  margin: 0.25rem 0;
}

/* Code de remise */
.code-card {
  background: color-mix(in srgb, var(--green) 10%, var(--surface));
  border: 0.0625rem solid color-mix(in srgb, var(--green) 20%, var(--border));
  border-radius: 1rem;
  padding: 1.375rem;
  text-align: center;
}

.code-label {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--fg-2);
  margin-bottom: 0.5rem;
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
  grid-template-columns: repeat(auto-fill, minmax(8.75rem, 1fr));
  gap: 0.875rem;
}

.proof-item {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.proof-img {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 0.625rem;
  border: 0.0625rem solid var(--border);
  background: var(--surface-2);
}
.zoomable { cursor: zoom-in; }

/* Chat lecture seule */
.chat-read {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-height: 18.75rem;
  overflow-y: auto;
  padding: 0.25rem;
}

.chat-msg-read {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  padding: 0.5rem 0.75rem;
  background: var(--surface-2);
  border-radius: 0.625rem;
}

@media (max-width: 768px) {
  .tracking-header { flex-direction: column; }
}
</style>
