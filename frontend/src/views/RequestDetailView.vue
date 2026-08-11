<script setup>
import AppIcon from "../components/AppIcon.vue"
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api, { apiError } from '../api/axios'
import { STATUS, TERMINAL_STATUSES, formatPrice, formatDateTime } from '../lib/statuses'
import StatusBadge from '../components/StatusBadge.vue'
import StatusTimeline from '../components/StatusTimeline.vue'
import ChatPanel from '../components/ChatPanel.vue'
import ImageLightbox from '../components/ImageLightbox.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const id = computed(() => route.params.id)

const request = ref(null)
const loading = ref(true)
const errorMsg = ref('')

// Actions
const cancelling = ref(false)
const confirming = ref(false)
const actionError = ref('')

// Proofs
const proofs = ref([])
const lightbox = ref('')

let pollTimer = null

onMounted(() => {
  loadRequest()
  loadProofs()
})

onBeforeUnmount(() => {
  stopPolling()
})

async function loadRequest() {
  loading.value = true
  errorMsg.value = ''
  try {
    const { data } = await api.get(`/delivery-requests/${id.value}`)
    request.value = data
    startPolling()
  } catch (err) {
    if (err.response?.status === 404) {
      errorMsg.value = 'Demande introuvable.'
    } else {
      errorMsg.value = apiError(err, 'Erreur lors du chargement de la demande.')
    }
  } finally {
    loading.value = false
  }
}

async function loadProofs() {
  try {
    const { data } = await api.get('/delivery-proofs', {
      params: { delivery_request_id: id.value },
    })
    proofs.value = data.data || data || []
  } catch {
    // silent
  }
}

function startPolling() {
  stopPolling()
  pollTimer = setInterval(async () => {
    try {
      const { data } = await api.get(`/delivery-requests/${id.value}`)
      request.value = data
    } catch {
      // silent
    }
  }, 4000)
}

function stopPolling() {
  if (pollTimer) clearInterval(pollTimer)
  pollTimer = null
}

// ACTIONS
async function cancelRequest() {
  if (!confirm('Êtes-vous sûr de vouloir annuler cette demande ?')) return
  cancelling.value = true
  actionError.value = ''
  try {
    const { data } = await api.post(`/delivery-requests/${id.value}/cancel`)
    request.value = data
  } catch (err) {
    actionError.value = apiError(err, "Erreur lors de l'annulation.")
  } finally {
    cancelling.value = false
  }
}

async function confirmPrice() {
  if (!confirm('Accepter le prix proposé par le livreur ?')) return
  confirming.value = true
  actionError.value = ''
  try {
    const { data } = await api.post(`/delivery-requests/${id.value}/confirm-price`)
    request.value = data
  } catch (err) {
    actionError.value = apiError(err, "Erreur lors de l'acceptation du prix.")
  } finally {
    confirming.value = false
  }
}
</script>

<template>
  <!-- LOADING -->
  <div v-if="loading" class="flex-col" style="gap: 1rem; padding-top: 2rem; align-items: center">
    <div class="skeleton" style="width: 12.5rem; height: 1.75rem"></div>
    <div class="skeleton" style="width: 7.5rem; height: 1.5rem"></div>
    <div class="skeleton" style="width: 100%; max-width: 46.25rem; height: 18.75rem; margin-top: 1rem"></div>
  </div>

  <!-- ERROR -->
  <div v-else-if="errorMsg" class="card" style="text-align: center; padding: 2rem; max-width: 31.25rem; margin: 3rem auto">
    <p class="error-msg">{{ errorMsg }}</p>
    <router-link
      v-if="auth.driverSlug"
      class="btn btn-outline"
      style="margin-top: 1rem"
      :to="{ name: 'ai-assistant', params: { slug: auth.driverSlug } }"
    >
      ← Retour à la commande
    </router-link>
  </div>

  <!-- DETAIL -->
  <div v-else-if="request" class="detail-page">
    <!-- Header -->
    <div class="detail-header">
      <div>
        <div class="detail-title-row">
          <h2 style="font-size: 1.75rem">#{{ request.tracking_number }}</h2>
          <StatusBadge :status="request.status" />
        </div>
        <span class="small faint" style="display: block; margin-top: 0.5rem">
          Créée le {{ formatDateTime(request.created_at) }}
        </span>
      </div>
    </div>

    <!-- Action error -->
    <p v-if="actionError" class="error-msg" style="margin-top: 0.75rem">{{ actionError }}</p>

    <!-- ===== ACTIONS selon statut ===== -->

    <!-- EN ATTENTE : Annuler -->
    <div v-if="request.status === STATUS.EN_ATTENTE" class="action-card">
      <p class="small muted">Votre demande est en attente de traitement par le livreur.</p>
      <button
        class="action-btn action-btn--danger"
        :disabled="cancelling"
        @click="cancelRequest"
      >
        <span v-if="cancelling" class="spinner spinner-sm"></span>
        <span v-else>Annuler la demande</span>
      </button>
    </div>

    <!-- PRIX PROPOSÉ (demande antérieure — compat legacy) : Accepter / Refuser.
         Seule cette carte est concernée : pour une demande normale le statut
         passe directement en_attente → confirmee quand le livreur accepte,
         le client n'a rien à confirmer. -->
    <div v-if="request.status === STATUS.PRIX_PROPOSE" class="action-card">
      <div class="price-proposal">
        <span class="price-label">Prix proposé</span>
        <span class="price-value">{{ formatPrice(request.proposed_price) }}</span>
      </div>
      <div class="action-btns">
        <button
          class="action-btn action-btn--green"
          :disabled="confirming"
          @click="confirmPrice"
        >
          <span v-if="confirming" class="spinner spinner-sm"></span>
          <span v-else><AppIcon name="check" :size="18" /> Accepter</span>
        </button>
        <button
          class="action-btn action-btn--danger"
          :disabled="cancelling"
          @click="cancelRequest"
        >
          <span v-if="cancelling" class="spinner spinner-sm"></span>
          <span v-else>Annuler</span>
        </button>
      </div>
    </div>

    <!-- EN LIVRAISON : le livreur est en route, il confirme son arrivée de son côté -->
    <div v-if="request.status === STATUS.EN_LIVRAISON" class="action-card">
      <h4 style="margin-bottom: 1rem"><AppIcon name="truck" :size="18" /> Le livreur est en route</h4>
      <p class="small muted" style="margin-bottom: 0.5rem">
        Restez prêt ! Le livreur confirme lui-même son arrivée et vous remettra la commande.
      </p>
      <p class="small" style="color: var(--green)"><AppIcon name="check" :size="14" /> Aucune action requise de votre côté.</p>
    </div>

    <!-- LIVREUR ARRIVÉ : remise confirmée par le livreur (RG06) -->
    <div v-if="request.status === STATUS.LIVREUR_ARRIVE" class="action-card">
      <h4 style="margin-bottom: 1rem"><AppIcon name="home" :size="18" /> Le livreur vous attend</h4>
      <p class="small muted" style="margin-bottom: 0.5rem">
        Votre commande est arrivée ! Le livreur finalise la remise et mettra à jour le statut.
      </p>
      <p class="small" style="color: var(--green)"><AppIcon name="check" :size="14" /> Livreur sur place — plus rien à faire de votre côté.</p>
    </div>

    <!-- LIVRÉE -->
    <div v-if="request.status === STATUS.LIVREE" class="action-card">
      <h4 style="margin-bottom: 1rem">Demande livrée !</h4>
      <p class="small muted">Votre colis a bien été livré le {{ formatDateTime(request.delivered_at) }}.</p>
    </div>

    <!-- STATUTS TERMINAUX NÉGATIFS -->
    <div
      v-if="[STATUS.REFUSEE, STATUS.ECHEC, STATUS.ANNULEE].includes(request.status)"
      class="action-card"
    >
      <p class="small" style="color: var(--red)">
        Cette demande est
        <span class="bold">{{ request.status === STATUS.REFUSEE ? 'refusée' : request.status === STATUS.ECHEC ? 'en échec' : 'annulée' }}</span>.
      </p>
      <router-link
        v-if="auth.driverSlug"
        class="btn btn-outline"
        style="margin-top: 1rem"
        :to="{ name: 'ai-assistant', params: { slug: auth.driverSlug } }"
      >
        ← Retour à la commande
      </router-link>
    </div>

    <!-- ===== DÉTAILS =====
         Deux colonnes : le détail de la course à gauche, ses pièces à droite.
         Les cartes d'action restent au-dessus, pleine largeur : c'est la seule
         décision demandée au client, elle ne se range pas dans une colonne. -->
    <div class="detail-grid">
      <div class="detail-col">

    <!-- Destinataire & adresses -->
    <div class="card">
      <h4 style="margin-bottom: 1rem"><AppIcon name="pin" :size="18" /> Détails de la livraison</h4>
      <div class="ticket-rows">
        <div>
          <span class="faint small">Destinataire</span>
          <p class="small bold">{{ request.recipient_name }} — {{ request.recipient_phone }}</p>
        </div>
        <div class="ticket-divider" style="margin: 0.25rem 0"></div>
        <div>
          <span class="faint small">Retrait</span>
          <p class="small bold">{{ request.pickup_address }}</p>
        </div>
        <div class="ticket-divider" style="margin: 0.25rem 0"></div>
        <div>
          <span class="faint small">Livraison</span>
          <p class="small bold">{{ request.delivery_address }}</p>
        </div>
      </div>
    </div>

    <!-- Dates -->
    <div class="card">
      <h4 style="margin-bottom: 1rem"><AppIcon name="calendar" :size="18" /> Dates</h4>
      <div class="grid-3" style="gap: 1rem">
        <div v-if="request.scheduled_at">
          <span class="faint small">Créneau</span>
          <p class="small">{{ formatDateTime(request.scheduled_at) }}</p>
        </div>
        <div v-if="request.picked_up_at">
          <span class="faint small">Récupérée</span>
          <p class="small">{{ formatDateTime(request.picked_up_at) }}</p>
        </div>
        <div v-if="request.delivered_at">
          <span class="faint small">Livrée</span>
          <p class="small bold" style="color: var(--green)">{{ formatDateTime(request.delivered_at) }}</p>
        </div>
      </div>
    </div>

    <!-- Preuves -->
    <div v-if="proofs.length" class="card">
      <h4 style="margin-bottom: 1rem"><AppIcon name="camera" :size="18" /> Preuves</h4>
      <div class="proofs-grid">
        <div v-for="(proof, i) in proofs" :key="i" class="proof-item">
          <img
            :src="proof.file_url"
            :alt="proof.proof_type"
            class="proof-img zoomable"
            loading="lazy"
            title="Agrandir"
            @click="lightbox = proof.file_url"
          />
          <span class="small muted" style="text-align: center">
            {{ proof.proof_type === 'pickup_photo' ? 'Récupération' : proof.proof_type === 'photo' ? 'Livraison' : proof.proof_type }}
          </span>
        </div>
      </div>
    </div>

    <!-- Chat -->
    <div class="card">
      <h4 style="margin-bottom: 1rem"><AppIcon name="chat" :size="18" /> Chat</h4>
      <ChatPanel :delivery-request-id="request.id" />
    </div>
      </div>

      <!-- Colonne des pièces : étapes, ticket, code de remise -->
      <div class="detail-col">
        <div class="card">
          <h4 style="margin-bottom: 1rem"><AppIcon name="chart" :size="18" /> Étapes de la livraison</h4>
          <StatusTimeline
            :history="request.timeline || []"
            :current="request.status"
          />
        </div>

        <div class="ticket-card">
          <h4 style="margin-bottom: 1rem">Ticket de livraison</h4>
          <div class="ticket-rows">
            <div class="ticket-row">
              <span class="ticket-label">Suivi</span>
              <span class="ticket-value">#{{ request.tracking_number }}</span>
            </div>
            <div class="ticket-row">
              <span class="ticket-label">Service</span>
              <span class="ticket-value">{{ request.service?.name || '—' }}</span>
            </div>
            <div class="ticket-row">
              <span class="ticket-label">Destinataire</span>
              <span class="ticket-value">{{ request.recipient_name }}</span>
            </div>
            <div class="ticket-row">
              <span class="ticket-label">Livraison</span>
              <span class="ticket-value">{{ request.delivery_address }}</span>
            </div>
            <div class="ticket-divider"></div>
            <div class="ticket-row">
              <span class="ticket-label" style="font-weight: 800; color: var(--fg)">Total à encaisser</span>
              <span class="ticket-value" style="color: var(--green)">
                {{ request.amount_to_collect ? formatPrice(request.amount_to_collect) : '—' }}
              </span>
            </div>
          </div>
        </div>

        <div v-if="request.status === STATUS.LIVREE" class="code-card">
          <div class="code-label">Demande livrée</div>
          <div class="code-big"><AppIcon name="check" :size="22" /></div>
        </div>
      </div>
    </div>

    <ImageLightbox v-if="lightbox" :src="lightbox" alt="Preuve" @close="lightbox = ''" />
  </div>
</template>

<style scoped>
/* Le détail occupe toute la colonne de l'espace client : le prototype compose
   l'écran en deux colonnes, pas en un ruban de cartes empilées. */
.detail-page {
  padding-bottom: 3rem;
  display: flex;
  flex-direction: column;
  gap: 1.125rem;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1.5fr minmax(0, 1fr);
  gap: 1.125rem;
  align-items: start;
}

/* `min-width: 0` sur les colonnes : sans lui, une longue adresse dans le ticket
   impose sa largeur intrinsèque à la piste et déforme toute la grille. */
.detail-col {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.detail-col > .card,
.detail-col > .ticket-card,
.detail-col > .code-card {
  margin-top: 0;
}

/* Une colonne dès que la grille n'a plus la place de tenir ses deux pistes. */
@media (max-width: 900px) {
  .detail-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

.detail-page > * {
  margin-top: 0;
}

.detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.detail-title-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

/* Action cards */
.action-card {
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  border-radius: 1rem;
  padding: 1.25rem 1.5rem;
}

/* Price proposal */
.price-proposal {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

/* Surtitre vert du bloc « prix proposé » : c'est la seule décision que le
   client ait à prendre sur cet écran, le prototype la signale par la couleur
   d'accent et non par un gris de libellé secondaire. */
.price-label {
  font-size: 0.78rem;
  font-weight: 800;
  color: var(--green);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.price-value {
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--green);
}

.action-btns {
  display: flex;
  gap: 0.625rem;
  margin-top: 0.875rem;
}

/* Action buttons */
.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  border: none;
  border-radius: 0.8125rem;
  padding: 0.8125rem 1.25rem;
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 800;
  cursor: pointer;
  transition: transform 0.15s, opacity 0.2s;
}

.action-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.action-btn:active:not(:disabled) {
  transform: scale(0.98);
}

.action-btn--green {
  background: var(--green);
  color: var(--green-ink);
}

.action-btn--danger {
  background: var(--surface-2);
  color: var(--red);
  border: 0.0625rem solid var(--border);
}

.action-btn--danger:hover:not(:disabled) {
  border-color: var(--red);
}

/* Form elements */
.form-field {
  display: flex;
  flex-direction: column;
}

.form-field label {
  display: block;
  font-size: 0.7813rem;
  font-weight: 700;
  color: var(--fg-2);
  margin-bottom: 0.375rem;
}

.faint-label {
  color: var(--fg-3);
  font-weight: 500;
}

.form-input {
  width: 100%;
  padding: 0.8125rem 0.9375rem;
  border-radius: 0.75rem;
  border: 0.0625rem solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 0.9375rem;
  font-family: inherit;
  transition: border-color 0.2s;
}

.form-input:focus {
  outline: none;
  border-color: var(--green) !important;
}

.form-textarea {
  width: 100%;
  padding: 0.8125rem 0.9375rem;
  border-radius: 0.75rem;
  border: 0.0625rem solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 0.9375rem;
  font-family: inherit;
  resize: vertical;
  min-height: 4.375rem;
  line-height: 1.5;
  transition: border-color 0.2s;
}

.form-textarea:focus {
  outline: none;
  border-color: var(--green) !important;
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
}

/* Carte de fin de livraison */
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
.grid-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
}

@media (max-width: 768px) {
  .detail-header { flex-direction: column; }
  .grid-3 { grid-template-columns: 1fr; }
}
</style>
