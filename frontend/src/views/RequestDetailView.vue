<script setup>
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
const confirmingDelivery = ref(false)
const deliveryCode = ref('')
const actionError = ref('')

// Review
const showReview = ref(false)
const reviewRating = ref(5)
const reviewComment = ref('')
const reviewSubmitting = ref(false)
const reviewError = ref('')
const hasReview = ref(false)

// Proofs
const proofs = ref([])
const lightbox = ref('')

let pollTimer = null

onMounted(() => {
  loadRequest()
  loadProofs()
  checkReview()
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

async function checkReview() {
  try {
    const { data } = await api.get('/reviews')
    const reviews = data.data || data || []
    hasReview.value = reviews.some(r => r.delivery_request_id === Number(id.value))
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

async function confirmDelivery() {
  if (!deliveryCode.value || deliveryCode.value.length !== 6) {
    actionError.value = 'Le code doit contenir 6 chiffres.'
    return
  }
  confirmingDelivery.value = true
  actionError.value = ''
  try {
    const { data } = await api.post(`/delivery-requests/${id.value}/confirm-delivery`, {
      code: deliveryCode.value,
    })
    request.value = data
    deliveryCode.value = ''
  } catch (err) {
    actionError.value = apiError(err, 'Code incorrect ou expiré.')
  } finally {
    confirmingDelivery.value = false
  }
}

async function submitReview() {
  reviewError.value = ''
  reviewSubmitting.value = true
  try {
    await api.post('/reviews', {
      delivery_request_id: Number(id.value),
      rating: reviewRating.value,
      comment: reviewComment.value.trim() || undefined,
    })
    hasReview.value = true
    showReview.value = false
  } catch (err) {
    reviewError.value = apiError(err, "Erreur lors de l'envoi de l'avis.")
  } finally {
    reviewSubmitting.value = false
  }
}
</script>

<template>
  <!-- LOADING -->
  <div v-if="loading" class="flex-col" style="gap: 16px; padding-top: 32px; align-items: center">
    <div class="skeleton" style="width: 200px; height: 28px"></div>
    <div class="skeleton" style="width: 120px; height: 24px"></div>
    <div class="skeleton" style="width: 100%; max-width: 740px; height: 300px; margin-top: 16px"></div>
  </div>

  <!-- ERROR -->
  <div v-else-if="errorMsg" class="card" style="text-align: center; padding: 32px; max-width: 500px; margin: 48px auto">
    <p class="error-msg">{{ errorMsg }}</p>
    <router-link class="btn btn-outline" style="margin-top: 16px" :to="{ name: 'my-requests' }">
      ← Retour à mes demandes
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
        <span class="small faint" style="display: block; margin-top: 8px">
          Créée le {{ formatDateTime(request.created_at) }}
        </span>
      </div>
    </div>

    <!-- Action error -->
    <p v-if="actionError" class="error-msg" style="margin-top: 12px">{{ actionError }}</p>

    <!-- ===== ACTIONS selon statut ===== -->

    <!-- EN ATTENTE : Annuler -->
    <div v-if="request.status === STATUS.EN_ATTENTE" class="action-card">
      <p class="small muted">Votre demande est en attente de traitement par le livreur.</p>
      <button
        class="action-btn action-btn--danger"
        :disabled="cancelling"
        @click="cancelRequest"
      >
        <span v-if="cancelling" class="spinner"></span>
        <span v-else>Annuler la demande</span>
      </button>
    </div>

    <!-- PRIX PROPOSÉ : Accepter / Refuser -->
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
          <span v-if="confirming" class="spinner"></span>
          <span v-else>✅ Accepter</span>
        </button>
        <button
          class="action-btn action-btn--danger"
          :disabled="cancelling"
          @click="cancelRequest"
        >
          <span v-if="cancelling" class="spinner"></span>
          <span v-else>Annuler</span>
        </button>
      </div>
    </div>

    <!-- EN LIVRAISON : Code de confirmation -->
    <div v-if="request.status === STATUS.EN_LIVRAISON" class="action-card">
      <h4 style="margin-bottom: 16px">🔐 Code de confirmation</h4>
      <p class="small muted" style="margin-bottom: 16px">
        Demandez le code de 6 chiffres à votre livreur et saisissez-le ci-dessous pour confirmer la réception.
      </p>
      <div class="form-field">
        <label>Code de remise</label>
        <input
          v-model="deliveryCode"
          type="text"
          class="form-input"
          maxlength="6"
          placeholder="000000"
          style="max-width: 200px; letter-spacing: 0.3em; text-align: center; font-size: 1.3rem"
        />
      </div>
      <button
        class="action-btn action-btn--green"
        style="margin-top: 8px"
        :disabled="confirmingDelivery || deliveryCode.length !== 6"
        @click="confirmDelivery"
      >
        <span v-if="confirmingDelivery" class="spinner"></span>
        <span v-else>📦 Confirmer la livraison</span>
      </button>
    </div>

    <!-- LIVRÉE : Avis -->
    <div v-if="request.status === STATUS.LIVREE" class="action-card">
      <h4 style="margin-bottom: 16px">🎉 Demande livrée !</h4>
      <p class="small muted">Votre colis a bien été livré le {{ formatDateTime(request.delivered_at) }}.</p>

      <div v-if="hasReview" style="margin-top: 8px">
        <p class="small" style="color: var(--green)">✅ Vous avez déjà laissé un avis pour cette demande.</p>
      </div>

      <div v-else-if="!showReview" style="margin-top: 16px">
        <button class="action-btn action-btn--green" @click="showReview = true">
          ⭐ Donner mon avis
        </button>
      </div>

      <div v-else class="review-form">
        <h4 style="margin-bottom: 8px">Votre avis</h4>
        <div class="rating-select">
          <button
            v-for="n in 5"
            :key="n"
            class="star-btn"
            :class="{ active: reviewRating >= n }"
            @click="reviewRating = n"
          >
            ★
          </button>
        </div>
        <div class="form-field" style="margin-top: 8px">
          <label>Commentaire <span class="faint-label">(optionnel)</span></label>
          <textarea
            v-model="reviewComment"
            class="form-textarea"
            rows="3"
            placeholder="Partagez votre expérience…"
          ></textarea>
        </div>
        <p v-if="reviewError" class="error-msg">{{ reviewError }}</p>
        <button
          class="action-btn action-btn--green"
          style="margin-top: 8px"
          :disabled="reviewSubmitting"
          @click="submitReview"
        >
          <span v-if="reviewSubmitting" class="spinner"></span>
          <span v-else>Envoyer l'avis</span>
        </button>
      </div>
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
      <router-link class="btn btn-outline" style="margin-top: 16px" :to="{ name: 'my-requests' }">
        ← Retour à mes demandes
      </router-link>
    </div>

    <!-- ===== TICKET DE LIVRAISON ===== -->
    <div class="ticket-card">
      <h4 style="margin-bottom: 16px">Ticket de livraison</h4>
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

    <!-- ===== Code de remise ===== -->
    <div v-if="request.status === STATUS.LIVREE || request.confirmation_code" class="code-card">
      <div class="code-label">Code de remise</div>
      <div class="code-big">{{ request.confirmation_code || '—' }}</div>
    </div>

    <!-- ===== DÉTAILS ===== -->

    <!-- Destinataire & adresses -->
    <div class="card">
      <h4 style="margin-bottom: 16px">📍 Détails de la livraison</h4>
      <div class="ticket-rows">
        <div>
          <span class="faint small">Destinataire</span>
          <p class="small bold">{{ request.recipient_name }} — {{ request.recipient_phone }}</p>
        </div>
        <div class="ticket-divider" style="margin: 4px 0"></div>
        <div>
          <span class="faint small">Retrait</span>
          <p class="small bold">{{ request.pickup_address }}</p>
        </div>
        <div class="ticket-divider" style="margin: 4px 0"></div>
        <div>
          <span class="faint small">Livraison</span>
          <p class="small bold">{{ request.delivery_address }}</p>
        </div>
      </div>
    </div>

    <!-- Dates -->
    <div class="card">
      <h4 style="margin-bottom: 16px">📅 Dates</h4>
      <div class="grid-3" style="gap: 16px">
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

    <!-- Timeline -->
    <div class="card">
      <h4 style="margin-bottom: 16px">📊 Étapes de la livraison</h4>
      <StatusTimeline
        :history="request.timeline || []"
        :current="request.status"
      />
    </div>

    <!-- Preuves -->
    <div v-if="proofs.length" class="card">
      <h4 style="margin-bottom: 16px">📸 Preuves</h4>
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
      <h4 style="margin-bottom: 16px">💬 Chat</h4>
      <ChatPanel :delivery-request-id="request.id" />
    </div>

    <ImageLightbox v-if="lightbox" :src="lightbox" alt="Preuve" @close="lightbox = ''" />
  </div>
</template>

<style scoped>
.detail-page {
  max-width: 740px;
  margin: 0 auto;
  padding-bottom: 48px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-page > * {
  margin-top: 0;
}

.detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.detail-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

/* Action cards */
.action-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 20px 24px;
}

/* Price proposal */
.price-proposal {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.price-label {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--fg-2);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.price-value {
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--green);
}

.action-btns {
  display: flex;
  gap: 10px;
  margin-top: 14px;
}

/* Action buttons */
.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: none;
  border-radius: 13px;
  padding: 13px 20px;
  font-family: inherit;
  font-size: 14px;
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
  border: 1px solid var(--border);
}

.action-btn--danger:hover:not(:disabled) {
  border-color: var(--red);
}

/* Rating */
.rating-select {
  display: flex;
  gap: 4px;
}

.star-btn {
  background: none;
  border: none;
  font-size: 1.8rem;
  cursor: pointer;
  color: var(--fg-3);
  transition: color 0.15s;
  padding: 2px;
}

.star-btn.active {
  color: var(--amber);
}

.star-btn:hover {
  color: var(--amber);
}

/* Review form */
.review-form {
  border-top: 1px solid var(--border);
  padding-top: 16px;
  margin-top: 12px;
}

/* Form elements */
.form-field {
  display: flex;
  flex-direction: column;
}

.form-field label {
  display: block;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--fg-2);
  margin-bottom: 6px;
}

.faint-label {
  color: var(--fg-3);
  font-weight: 500;
}

.form-input {
  width: 100%;
  padding: 13px 15px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 15px;
  font-family: inherit;
  transition: border-color 0.2s;
}

.form-input:focus {
  outline: none;
  border-color: var(--green) !important;
}

.form-textarea {
  width: 100%;
  padding: 13px 15px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 15px;
  font-family: inherit;
  resize: vertical;
  min-height: 70px;
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

/* Spinner */
.spinner {
  display: inline-block;
  width: 18px;
  height: 18px;
  border: 2.5px solid var(--border);
  border-top-color: var(--green);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.grid-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
}

@media (max-width: 768px) {
  .detail-header { flex-direction: column; }
  .grid-3 { grid-template-columns: 1fr; }
}
</style>
