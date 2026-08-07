<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api, { apiError } from '../api/axios'
import { STATUS, TERMINAL_STATUSES, formatPrice, formatDateTime } from '../lib/statuses'
import StatusBadge from '../components/StatusBadge.vue'
import StatusTimeline from '../components/StatusTimeline.vue'
import ChatPanel from '../components/ChatPanel.vue'

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
  <div v-if="loading" class="flex-col" style="gap: 16px; padding-top: 32px">
    <div class="skeleton" style="width: 200px; height: 28px"></div>
    <div class="skeleton" style="width: 120px; height: 24px"></div>
    <div class="skeleton" style="width: 100%; height: 300px; margin-top: 16px"></div>
  </div>

  <!-- ERROR -->
  <div v-else-if="errorMsg" class="card" style="text-align: center; padding: 32px">
    <p class="error-msg">{{ errorMsg }}</p>
    <router-link class="btn btn-outline mt-16" :to="{ name: 'my-requests' }">
      ← Retour à mes demandes
    </router-link>
  </div>

  <!-- DETAIL -->
  <div v-else-if="request" class="detail-page">
    <!-- Header -->
    <div class="detail-header">
      <div>
        <h2>#{{ request.tracking_number }}</h2>
        <span class="small faint mt-8" style="display: block">
          Créée le {{ formatDateTime(request.created_at) }}
        </span>
      </div>
      <StatusBadge :status="request.status" />
    </div>

    <!-- Action error -->
    <p v-if="actionError" class="error-msg mt-16">{{ actionError }}</p>

    <!-- ===== ACTIONS selon statut ===== -->

    <!-- EN ATTENTE : Annuler -->
    <div v-if="request.status === STATUS.EN_ATTENTE" class="card mt-16 action-card">
      <p class="small muted">Votre demande est en attente de traitement par le livreur.</p>
      <button
        class="btn btn-danger mt-8"
        :disabled="cancelling"
        @click="cancelRequest"
      >
        <span v-if="cancelling" class="spinner"></span>
        <span v-else>❌ Annuler la demande</span>
      </button>
    </div>

    <!-- PRIX PROPOSÉ : Accepter / Refuser -->
    <div v-if="request.status === STATUS.PRIX_PROPOSE" class="card mt-16 action-card">
      <div class="price-proposal">
        <span class="price-label">Prix proposé par le livreur</span>
        <span class="price-value">{{ formatPrice(request.proposed_price) }}</span>
      </div>
      <div class="flex" style="gap: 10px; margin-top: 14px">
        <button
          class="btn btn-primary"
          :disabled="confirming"
          @click="confirmPrice"
        >
          <span v-if="confirming" class="spinner"></span>
          <span v-else>✅ Accepter le prix</span>
        </button>
        <button
          class="btn btn-danger"
          :disabled="cancelling"
          @click="cancelRequest"
        >
          <span v-if="cancelling" class="spinner"></span>
          <span v-else>❌ Refuser</span>
        </button>
      </div>
    </div>

    <!-- EN LIVRAISON : Code de confirmation -->
    <div v-if="request.status === STATUS.EN_LIVRAISON" class="card mt-16 action-card">
      <h4 class="mb-16">🔐 Code de confirmation</h4>
      <p class="small muted mb-16">
        Demandez le code de 6 chiffres à votre livreur et saisissez-le ci-dessous pour confirmer la réception.
      </p>
      <div class="field">
        <label for="delivery-code">Code à 6 chiffres</label>
        <input
          id="delivery-code"
          v-model="deliveryCode"
          type="text"
          class="input"
          maxlength="6"
          placeholder="000000"
          style="max-width: 200px; letter-spacing: 0.3em; text-align: center; font-size: 1.3rem"
        />
      </div>
      <button
        class="btn btn-primary mt-8"
        :disabled="confirmingDelivery || deliveryCode.length !== 6"
        @click="confirmDelivery"
      >
        <span v-if="confirmingDelivery" class="spinner"></span>
        <span v-else>📦 Confirmer la livraison</span>
      </button>
    </div>

    <!-- LIVRÉE : Avis -->
    <div v-if="request.status === STATUS.LIVREE" class="card mt-16 action-card">
      <h4 class="mb-16">🎉 Demande livrée !</h4>
      <p class="small muted">Votre colis a bien été livré le {{ formatDateTime(request.delivered_at) }}.</p>

      <div v-if="hasReview" class="mt-8">
        <p class="small" style="color: var(--brand)">✅ Vous avez déjà laissé un avis pour cette demande.</p>
      </div>

      <div v-else-if="!showReview" class="mt-16">
        <button class="btn btn-primary" @click="showReview = true">
          ⭐ Donner mon avis
        </button>
      </div>

      <div v-else class="review-form mt-16">
        <h4>Votre avis</h4>
        <div class="rating-select mt-8">
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
        <div class="field mt-8">
          <label for="review-comment">Commentaire <span class="faint">(optionnel)</span></label>
          <textarea
            id="review-comment"
            v-model="reviewComment"
            rows="3"
            placeholder="Partagez votre expérience…"
          ></textarea>
        </div>
        <p v-if="reviewError" class="error-msg">{{ reviewError }}</p>
        <button
          class="btn btn-primary mt-8"
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
      class="card mt-16 action-card"
    >
      <p class="small" style="color: var(--danger)">
        Cette demande est
        <span class="bold">{{ request.status === STATUS.REFUSEE ? 'refusée' : request.status === STATUS.ECHEC ? 'en échec' : 'annulée' }}</span>.
      </p>
      <router-link class="btn btn-outline mt-16" :to="{ name: 'my-requests' }">
        ← Retour à mes demandes
      </router-link>
    </div>

    <!-- ===== DÉTAILS ===== -->

    <!-- Adresses -->
    <div class="card mt-16">
      <h4 class="mb-16">📍 Détails de la livraison</h4>
      <div class="flex-col" style="gap: 10px">
        <div>
          <span class="faint small">Destinataire</span>
          <p class="small bold">{{ request.recipient_name }} — {{ request.recipient_phone }}</p>
        </div>
        <div class="divider" style="margin: 4px 0"></div>
        <div>
          <span class="faint small">Ramassage</span>
          <p class="small bold">{{ request.pickup_address }}</p>
        </div>
        <div class="divider" style="margin: 4px 0"></div>
        <div>
          <span class="faint small">Livraison</span>
          <p class="small bold">{{ request.delivery_address }}</p>
        </div>
      </div>
    </div>

    <!-- Montants & colis -->
    <div class="grid-2 mt-16">
      <div class="card">
        <h4>💰 Montants</h4>
        <div class="flex-col mt-8" style="gap: 6px">
          <div v-if="request.product_amount" class="flex-between">
            <span class="small muted">Produit</span>
            <span class="small bold">{{ formatPrice(request.product_amount) }}</span>
          </div>
          <div v-if="request.amount_to_collect" class="flex-between">
            <span class="small muted">À encaisser</span>
            <span class="small bold">{{ formatPrice(request.amount_to_collect) }}</span>
          </div>
          <div v-if="request.proposed_price" class="flex-between">
            <span class="small muted">Prix livraison</span>
            <span class="small bold" style="color: var(--brand)">{{ formatPrice(request.proposed_price) }}</span>
          </div>
        </div>
      </div>
      <div class="card">
        <h4>📦 Colis</h4>
        <p v-if="request.package_description" class="small mt-8">{{ request.package_description }}</p>
        <p v-else class="small faint mt-8">Aucune description</p>
      </div>
    </div>

    <!-- Dates -->
    <div class="card mt-16">
      <h4 class="mb-16">📅 Dates</h4>
      <div class="grid-3" style="gap: 16px">
        <div>
          <span class="faint small">Créée le</span>
          <p class="small">{{ formatDateTime(request.created_at) }}</p>
        </div>
        <div v-if="request.scheduled_at">
          <span class="faint small">Souhaitée</span>
          <p class="small">{{ formatDateTime(request.scheduled_at) }}</p>
        </div>
        <div v-if="request.picked_up_at">
          <span class="faint small">Récupérée</span>
          <p class="small">{{ formatDateTime(request.picked_up_at) }}</p>
        </div>
        <div v-if="request.delivered_at">
          <span class="faint small">Livrée</span>
          <p class="small bold" style="color: var(--brand)">{{ formatDateTime(request.delivered_at) }}</p>
        </div>
      </div>
    </div>

    <!-- Timeline -->
    <div class="card mt-16">
      <h4 class="mb-16">📊 Historique</h4>
      <StatusTimeline
        :history="request.timeline || []"
        :current="request.status"
      />
    </div>

    <!-- Preuves -->
    <div v-if="proofs.length" class="card mt-16">
      <h4 class="mb-16">📸 Preuves</h4>
      <div class="proofs-grid">
        <div v-for="(proof, i) in proofs" :key="i" class="proof-item">
          <img
            :src="proof.file_url"
            :alt="proof.proof_type"
            class="proof-img"
            loading="lazy"
          />
          <span class="small muted" style="text-align: center">
            {{ proof.proof_type === 'pickup_photo' ? 'Récupération' : proof.proof_type === 'photo' ? 'Livraison' : proof.proof_type }}
          </span>
        </div>
      </div>
    </div>

    <!-- Chat -->
    <div class="card mt-16">
      <h4 class="mb-16">💬 Chat</h4>
      <ChatPanel :delivery-request-id="request.id" />
    </div>
  </div>
</template>

<style scoped>
.detail-page {
  max-width: 740px;
  margin: 0 auto;
  padding-bottom: 48px;
}

.detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

/* Action cards */
.action-card {
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
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.price-value {
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--brand);
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
  color: var(--text-faint);
  transition: color 0.15s;
  padding: 2px;
}

.star-btn.active {
  color: var(--warning);
}

.star-btn:hover {
  color: var(--warning);
}

/* Review form */
.review-form {
  border-top: 1px solid var(--border);
  padding-top: 16px;
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

@media (max-width: 768px) {
  .detail-header { flex-direction: column; }
}
</style>
