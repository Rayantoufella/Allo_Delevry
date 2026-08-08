<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { apiError } from '../api/axios'
import { formatPrice } from '../lib/statuses'

const route = useRoute()
const router = useRouter()
const slug = computed(() => route.params.slug)

const inputMessage = ref('')
const analyzing = ref(false)
const errorMsg = ref('')

// Draft state
const draftId = ref(null)
const draftData = ref(null)
const draftStatus = ref(null) // pending | done | failed
const draftError = ref('')

// Editable fields from generated_data
const form = ref({
  recipient_name: '',
  recipient_phone: '',
  pickup_address: '',
  delivery_address: '',
  package_description: '',
  product_amount: '',
  amount_to_collect: '',
  scheduled_at: '',
  service_id: null,
})

const creating = ref(false)
const createError = ref('')

const showForm = computed(() => draftStatus.value === 'done' && draftData.value)

let pollInterval = null
let pollTimeout = null

onBeforeUnmount(() => {
  clearPolling()
})

function clearPolling() {
  if (pollInterval) clearInterval(pollInterval)
  if (pollTimeout) clearTimeout(pollTimeout)
  pollInterval = null
  pollTimeout = null
}

async function analyze() {
  const text = inputMessage.value.trim()
  if (!text) return
  analyzing.value = true
  errorMsg.value = ''
  draftId.value = null
  draftData.value = null
  draftStatus.value = null
  draftError.value = ''
  createError.value = ''
  clearPolling()

  try {
    const { data } = await api.post('/ai-request-drafts/analyze', {
      input_message: text,
      driver_slug: slug.value,
    })
    draftId.value = data.id
    draftStatus.value = data.status || 'pending'
    if (data.status === 'done' && data.generated_data) {
      applyDraft(data)
    } else {
      pollDraft(data.id)
    }
  } catch (err) {
    errorMsg.value = apiError(err, "Erreur lors de l'analyse.")
  } finally {
    analyzing.value = false
  }
}

function pollDraft(id) {
  clearPolling()
  pollInterval = setInterval(async () => {
    try {
      const { data } = await api.get(`/ai-request-drafts/${id}`)
      if (data.status === 'done') {
        clearPolling()
        draftStatus.value = 'done'
        draftData.value = data
        applyDraft(data)
      } else if (data.status === 'failed') {
        clearPolling()
        draftStatus.value = 'failed'
        draftError.value = data.error_message || "L'analyse a échoué."
      }
    } catch {
      clearPolling()
      draftStatus.value = 'failed'
      draftError.value = 'Erreur lors de la récupération du résultat.'
    }
  }, 2500)

  // Safety timeout: 30s
  pollTimeout = setTimeout(() => clearPolling(), 30000)
}

function applyDraft(data) {
  draftData.value = data
  const gd = data.generated_data || {}
  form.value = {
    recipient_name: gd.recipient_name || '',
    recipient_phone: gd.recipient_phone || '',
    pickup_address: gd.pickup_address || '',
    delivery_address: gd.delivery_address || '',
    package_description: gd.package_description || '',
    product_amount: gd.product_amount ?? '',
    amount_to_collect: gd.amount_to_collect ?? '',
    scheduled_at: gd.scheduled_at || '',
    service_id: gd.service_id || null,
  }
}

async function createRequest() {
  createError.value = ''
  creating.value = true
  try {
    const payload = {
      ai_request_draft_id: draftId.value,
      recipient_name: form.value.recipient_name,
      recipient_phone: form.value.recipient_phone,
      pickup_address: form.value.pickup_address,
      delivery_address: form.value.delivery_address,
    }
    if (form.value.package_description) payload.package_description = form.value.package_description
    if (form.value.product_amount) payload.product_amount = Number(form.value.product_amount)
    if (form.value.amount_to_collect) payload.amount_to_collect = Number(form.value.amount_to_collect)
    if (form.value.scheduled_at) payload.scheduled_at = form.value.scheduled_at
    if (form.value.service_id) payload.service_id = form.value.service_id

    const { data } = await api.post(`/drivers/${slug.value}/delivery-requests`, payload)
    router.push({ name: 'request-detail', params: { id: data.id } })
  } catch (err) {
    createError.value = apiError(err, 'Erreur lors de la création de la demande.')
  } finally {
    creating.value = false
  }
}
</script>

<template>
  <div class="ai-page">
    <!-- Back link -->
    <router-link
      :to="{ name: 'driver-public', params: { slug } }"
      class="btn-back"
    >
      ← Retour
    </router-link>

    <div class="ai-badge">✦ Assistant IA</div>

    <h2 style="font-size: 1.75rem">Décris ta demande</h2>
    <p class="ai-subtitle">
      Écris comme tu parles. L'IA extrait le destinataire, l'adresse, le colis et le montant — tu vérifies tout avant l'envoi.
    </p>

    <!-- Zone de saisie -->
    <div class="ai-input-card">
      <div class="ai-field">
        <label>Describez votre demande</label>
        <textarea
          v-model="inputMessage"
          class="ai-textarea"
          rows="5"
          placeholder="Ex : Livrer demain avant 15h à Sara, 06 12 34 56 78, quartier Al Houda Agadir. Colis : chaussures taille 39. Montant à encaisser : 420 DH. Retrait chez Rayan, Avenue Hassan II."
          :disabled="analyzing"
        ></textarea>
      </div>
      <div class="ai-actions">
        <button
          class="btn btn-primary"
          style="padding: 15px 24px"
          :disabled="analyzing || !inputMessage.trim()"
          @click="analyze"
        >
          <span v-if="analyzing" class="spinner"></span>
          <span v-else>✦ Analyser avec l'IA</span>
        </button>
        <router-link
          :to="{ name: 'request-form', params: { slug } }"
          class="btn btn-outline"
          style="padding: 15px 24px"
        >
          Plutôt choisir un service
        </router-link>
      </div>
    </div>

    <!-- Garde-fou -->
    <p class="ai-garde-fou">
      🔒 Garde-fou : l'IA ne crée jamais la livraison. Elle propose un remplissage, tu confirmes chaque champ, et le tarif reste fixé par les zones du livreur.
    </p>

    <!-- Erreur analyse -->
    <p v-if="errorMsg" class="error-msg" style="margin-top: 12px">{{ errorMsg }}</p>

    <!-- Analyse en cours -->
    <div v-if="analyzing" class="ai-loading-card">
      <div class="flex" style="gap: 14px">
        <div class="spinner"></div>
        <div>
          <p class="bold">Analyse en cours…</p>
          <p class="small muted" style="margin-top: 4px">L'IA analyse votre demande et prépare les informations.</p>
        </div>
      </div>
      <div class="skeletons-grid">
        <div class="skeleton" style="height: 36px"></div>
        <div class="skeleton" style="height: 36px"></div>
        <div class="skeleton" style="height: 36px"></div>
        <div class="skeleton" style="height: 36px"></div>
      </div>
    </div>

    <!-- Résultat : failed -->
    <div v-if="draftStatus === 'failed'" class="ai-failed-card">
      <p class="bold" style="color: var(--red)">⚠️ Analyse échouée</p>
      <p class="small muted" style="margin-top: 4px">{{ draftError }}</p>
      <router-link
        :to="{ name: 'request-form', params: { slug } }"
        class="btn btn-outline mt-16"
      >
        Remplir le formulaire manuellement
      </router-link>
    </div>

    <!-- Résultat : done — formulaire éditable -->
    <div v-if="showForm" class="ai-result-card">
      <h3 style="margin-bottom: 16px">Récapitulatif de votre demande</h3>
      <p class="small muted" style="margin-bottom: 16px">Vous pouvez modifier les informations avant de confirmer.</p>

      <div class="grid-2">
        <div class="ai-field">
          <label>Nom du destinataire</label>
          <input v-model="form.recipient_name" type="text" class="ai-input" required />
        </div>
        <div class="ai-field">
          <label>Téléphone</label>
          <input v-model="form.recipient_phone" type="tel" class="ai-input" required />
        </div>
      </div>

      <div class="ai-field">
        <label>Adresse de retrait</label>
        <input v-model="form.pickup_address" type="text" class="ai-input" required />
      </div>

      <div class="ai-field">
        <label>Adresse de livraison</label>
        <input v-model="form.delivery_address" type="text" class="ai-input" required />
      </div>

      <div class="ai-field">
        <label>Description du colis</label>
        <input v-model="form.package_description" type="text" class="ai-input" />
      </div>

      <div class="grid-2">
        <div class="ai-field">
          <label>Montant produit (DH)</label>
          <input v-model="form.product_amount" type="number" class="ai-input" />
        </div>
        <div class="ai-field">
          <label>Montant à encaisser (DH)</label>
          <input v-model="form.amount_to_collect" type="number" class="ai-input" />
        </div>
      </div>

      <div class="ai-field">
        <label>Date souhaitée</label>
        <input v-model="form.scheduled_at" type="text" class="ai-input" placeholder="Ex : demain avant 15:00" />
      </div>

      <p v-if="createError" class="error-msg" style="margin-top: 8px">{{ createError }}</p>

      <button
        class="btn btn-primary btn-submit"
        :disabled="creating || !form.recipient_name || !form.recipient_phone || !form.pickup_address || !form.delivery_address"
        @click="createRequest"
      >
        <span v-if="creating" class="spinner"></span>
        <span v-else>📦 Envoyer la demande →</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.ai-page {
  max-width: 700px;
  margin: 0 auto;
  padding-bottom: 48px;
}

.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--fg-2);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  margin-bottom: 16px;
}
.btn-back:hover { color: var(--fg); text-decoration: none; }

.ai-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 3px 9px;
  border-radius: 20px;
  background: color-mix(in srgb, var(--violet) 16%, transparent);
  color: var(--violet);
  font-size: 11px;
  font-weight: 800;
  margin-bottom: 10px;
}

.ai-subtitle {
  color: var(--fg-2);
  font-size: 14.5px;
  margin-top: 8px;
  max-width: 600px;
  line-height: 1.55;
}

/* Input card */
.ai-input-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 22px;
  margin-top: 24px;
}

.ai-field {
  display: flex;
  flex-direction: column;
}

.ai-field label {
  display: block;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--fg-2);
  margin-bottom: 6px;
}

.ai-textarea {
  width: 100%;
  padding: 13px 15px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 15px;
  font-family: inherit;
  resize: vertical;
  min-height: 120px;
  line-height: 1.5;
  margin-bottom: 14px;
  transition: border-color 0.2s;
}

.ai-textarea:focus {
  outline: none;
  border-color: var(--green) !important;
}

.ai-textarea::placeholder {
  color: var(--fg-3);
}

.ai-actions {
  display: flex;
  gap: 10px;
}

.ai-garde-fou {
  color: var(--fg-3);
  font-size: 13px;
  margin-top: 12px;
  line-height: 1.5;
}

/* Loading card */
.ai-loading-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 22px;
  margin-top: 16px;
}

.skeletons-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 16px;
}

/* Failed card */
.ai-failed-card {
  background: var(--surface);
  border: 1px solid var(--red);
  border-radius: 16px;
  padding: 22px;
  margin-top: 16px;
}

/* Result card */
.ai-result-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 22px;
  margin-top: 16px;
}

.ai-input {
  width: 100%;
  padding: 13px 15px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 15px;
  font-family: inherit;
  margin-bottom: 16px;
  transition: border-color 0.2s;
}

.ai-input:focus {
  outline: none;
  border-color: var(--green) !important;
}

.ai-input::placeholder {
  color: var(--fg-3);
}

.btn-submit {
  width: 100%;
  margin-top: 16px;
  padding: 15px;
  font-size: 15.5px;
}

/* Spinner */
.spinner {
  display: inline-block;
  width: 20px;
  height: 20px;
  border: 2.5px solid var(--border);
  border-top-color: var(--green);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Grid 2 */
.grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

@media (max-width: 768px) {
  .grid-2 {
    grid-template-columns: 1fr;
  }
  .ai-actions {
    flex-direction: column;
  }
}
</style>
