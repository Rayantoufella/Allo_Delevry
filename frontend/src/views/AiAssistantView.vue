<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { apiError } from '../api/axios'
import { usePolling } from '../composables/usePolling'
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
  const { data: pollData, stop } = usePolling(async () => {
    const { data } = await api.get(`/ai-request-drafts/${id}`)
    return data
  }, 2500)

  const unwatch = window.__aiPollWatchers = window.__aiPollWatchers || []

  // Manual watch via interval
  let interval = setInterval(async () => {
    try {
      const { data } = await api.get(`/ai-request-drafts/${id}`)
      if (data.status === 'done') {
        clearInterval(interval)
        draftStatus.value = 'done'
        draftData.value = data
        applyDraft(data)
      } else if (data.status === 'failed') {
        clearInterval(interval)
        draftStatus.value = 'failed'
        draftError.value = data.error_message || 'L\'analyse a échoué.'
      }
      // still pending → keep polling
    } catch {
      clearInterval(interval)
      draftStatus.value = 'failed'
      draftError.value = 'Erreur lors de la récupération du résultat.'
    }
  }, 2500)

  // Safety timeout: 30s
  setTimeout(() => clearInterval(interval), 30000)
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
    <div class="flex-between mb-16">
      <h2>🤖 Assistant IA</h2>
      <router-link
        :to="{ name: 'request-form', params: { slug } }"
        class="btn btn-ghost small"
      >
        Remplir manuellement →
      </router-link>
    </div>
    <p class="muted small mb-16">
      L'assistant IA comprend votre demande en langage naturel. Décrivez simplement ce dont vous avez besoin.
    </p>

    <!-- Zone de saisie -->
    <div class="card">
      <div class="field">
        <label for="ai-input">Décrivez votre demande</label>
        <textarea
          id="ai-input"
          v-model="inputMessage"
          placeholder="Ex: J'envoie un colis de Cocody à Yopougon pour Mme Kouassi, téléphone 0709080706. C'est un document important. Le prix à encaisser est de 5000 FCFA."
          rows="5"
          :disabled="analyzing"
        ></textarea>
      </div>
      <button
        class="btn btn-primary"
        :disabled="analyzing || !inputMessage.trim()"
        @click="analyze"
      >
        <span v-if="analyzing" class="spinner"></span>
        <span v-else>🔍 Analyser ma demande</span>
      </button>
    </div>

    <!-- Erreur analyse -->
    <p v-if="errorMsg" class="error-msg mt-8">{{ errorMsg }}</p>

    <!-- Analyse en cours -->
    <div v-if="analyzing" class="card mt-16">
      <div class="flex" style="gap: 14px">
        <div class="spinner"></div>
        <div>
          <p class="bold">Analyse en cours…</p>
          <p class="small muted mt-8">L'IA analyse votre demande et prépare les informations.</p>
        </div>
      </div>
      <div class="skeleton-grid mt-16">
        <div class="skeleton" style="height: 36px"></div>
        <div class="skeleton" style="height: 36px"></div>
        <div class="skeleton" style="height: 36px"></div>
        <div class="skeleton" style="height: 36px"></div>
      </div>
    </div>

    <!-- Résultat : failed -->
    <div v-if="draftStatus === 'failed'" class="card mt-16" style="border-color: var(--danger)">
      <p class="bold" style="color: var(--danger)">⚠️ Analyse échouée</p>
      <p class="small muted mt-8">{{ draftError }}</p>
      <router-link
        :to="{ name: 'request-form', params: { slug } }"
        class="btn btn-outline mt-16"
      >
        Remplir le formulaire manuellement
      </router-link>
    </div>

    <!-- Résultat : done — formulaire éditable -->
    <div v-if="showForm" class="card mt-16">
      <h3 class="mb-16">Récapitulatif de votre demande</h3>
      <p class="small muted mb-16">Vous pouvez modifier les informations avant de confirmer.</p>

      <div class="grid-2">
        <div class="field">
          <label for="ai-recipient">Nom du destinataire</label>
          <input id="ai-recipient" v-model="form.recipient_name" class="input" required />
        </div>
        <div class="field">
          <label for="ai-phone">Téléphone</label>
          <input id="ai-phone" v-model="form.recipient_phone" class="input" required />
        </div>
      </div>

      <div class="field">
        <label for="ai-pickup">Adresse de ramassage</label>
        <input id="ai-pickup" v-model="form.pickup_address" class="input" required />
      </div>

      <div class="field">
        <label for="ai-delivery">Adresse de livraison</label>
        <input id="ai-delivery" v-model="form.delivery_address" class="input" required />
      </div>

      <div class="field">
        <label for="ai-desc">Description du colis</label>
        <input id="ai-desc" v-model="form.package_description" class="input" />
      </div>

      <div class="grid-2">
        <div class="field">
          <label for="ai-amount">Montant produit (FCFA)</label>
          <input id="ai-amount" v-model="form.product_amount" type="number" class="input" />
        </div>
        <div class="field">
          <label for="ai-collect">Montant à encaisser (FCFA)</label>
          <input id="ai-collect" v-model="form.amount_to_collect" type="number" class="input" />
        </div>
      </div>

      <div class="field">
        <label for="ai-date">Date souhaitée</label>
        <input id="ai-date" v-model="form.scheduled_at" type="datetime-local" class="input" />
      </div>

      <p v-if="createError" class="error-msg mt-8">{{ createError }}</p>

      <button
        class="btn btn-primary btn-lg mt-16"
        :disabled="creating || !form.recipient_name || !form.recipient_phone || !form.pickup_address || !form.delivery_address"
        @click="createRequest"
        style="width: 100%"
      >
        <span v-if="creating" class="spinner"></span>
        <span v-else>📦 Créer la demande de livraison</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.ai-page {
  max-width: 700px;
  margin: 0 auto;
}
.skeleton-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
@media (max-width: 768px) {
  .skeleton-grid { grid-template-columns: 1fr; }
}
</style>
