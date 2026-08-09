<script setup>
import AppIcon from "../components/AppIcon.vue"
import { ref, computed, nextTick, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { apiError } from '../api/axios'
import { formatPrice } from '../lib/statuses'

const route = useRoute()
const router = useRouter()
const slug = computed(() => route.params.slug)

const inputMessage = ref('')
const analyzing = ref(false)
const errorMsg = ref('')

// Profil du livreur (zones & tarifs) — chargé au montage pour le choix de zone.
const driver = ref(null)

async function loadDriver() {
  try {
    const { data } = await api.get(`/drivers/${slug.value}`)
    driver.value = data
  } catch {
    // Le sélecteur de zone restera simplement masqué si le profil est indisponible.
  }
}
loadDriver()

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
  delivery_zone_id: null,
})

const creating = ref(false)
const createError = ref('')

// Champs requis pour l'envoi (les seuls qu'exige le backend). L'IA peut en
// laisser certains vides quand le message du client est pauvre : on guide le
// remplissage au lieu de bloquer le bouton en silence.
const REQUIRED_FIELDS = [
  { key: 'recipient_name', label: 'Nom du destinataire' },
  { key: 'recipient_phone', label: 'Téléphone du destinataire' },
  { key: 'pickup_address', label: 'Adresse de retrait' },
  { key: 'delivery_address', label: 'Adresse de livraison' },
]

// Références des 4 champs requis, pour le focus automatique.
const recipientNameInput = ref(null)
const recipientPhoneInput = ref(null)
const pickupAddressInput = ref(null)
const deliveryAddressInput = ref(null)
const requiredInputRefs = {
  recipient_name: recipientNameInput,
  recipient_phone: recipientPhoneInput,
  pickup_address: pickupAddressInput,
  delivery_address: deliveryAddressInput,
}

const missingRequiredFields = computed(() => {
  const missing = REQUIRED_FIELDS.filter((f) => !(form.value[f.key] || '').trim())
  // La zone est requise dès que le livreur en propose (tarif fixe par zone).
  if (activeZones.value.length && !form.value.delivery_zone_id) {
    missing.push({ key: 'delivery_zone_id', label: 'Zone de livraison' })
  }
  return missing
})

const requiredFieldsComplete = computed(() => missingRequiredFields.value.length === 0)

const activeZones = computed(() =>
  (driver.value?.delivery_zones || []).filter((z) => z.is_active),
)

const selectedZone = computed(() => {
  if (!form.value.delivery_zone_id || !activeZones.value.length) return null
  return (
    activeZones.value.find((z) => z.id === Number(form.value.delivery_zone_id)) || null
  )
})

const zoneMissing = computed(
  () => activeZones.value.length > 0 && !form.value.delivery_zone_id,
)

function isMissing(key) {
  return REQUIRED_FIELDS.some((f) => f.key === key) && !(form.value[key] || '').trim()
}

// Section des zones, pour y amener le client quand c'est ce qui manque.
const zoneSection = ref(null)

// Dès que les données IA arrivent, amène le curseur sur le premier champ
// encore vide (ou ne fait rien si tout est déjà rempli).
function focusFirstMissingField() {
  nextTick(() => {
    const firstMissing = missingRequiredFields.value[0]
    if (!firstMissing) return
    if (firstMissing.key === 'delivery_zone_id') {
      zoneSection.value?.scrollIntoView({ behavior: 'smooth', block: 'center' })
      return
    }
    requiredInputRefs[firstMissing.key]?.value?.focus()
  })
}

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
    delivery_zone_id: null,
  }
  focusFirstMissingField()
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
    if (form.value.delivery_zone_id) payload.delivery_zone_id = Number(form.value.delivery_zone_id)

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

    <div class="ai-badge"><AppIcon name="sparkle" :size="16" /> Assistant IA</div>

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
          style="padding: 0.9375rem 1.5rem"
          :disabled="analyzing || !inputMessage.trim()"
          @click="analyze"
        >
          <span v-if="analyzing" class="spinner"></span>
          <span v-else><AppIcon name="sparkle" :size="18" /> Analyser avec l’IA</span>
        </button>
        <router-link
          :to="{ name: 'request-form', params: { slug } }"
          class="btn btn-outline"
          style="padding: 0.9375rem 1.5rem"
        >
          Plutôt choisir un service
        </router-link>
      </div>
    </div>

    <!-- Garde-fou -->
    <p class="ai-garde-fou">
      Garde-fou : l'IA ne crée jamais la livraison. Elle propose un remplissage, tu confirmes chaque champ, et le tarif reste fixé par les zones du livreur.
    </p>

    <!-- Erreur analyse -->
    <p v-if="errorMsg" class="error-msg" style="margin-top: 0.75rem">{{ errorMsg }}</p>

    <!-- Analyse en cours -->
    <div v-if="analyzing" class="ai-loading-card">
      <div class="flex" style="gap: 0.875rem">
        <div class="spinner"></div>
        <div>
          <p class="bold">Analyse en cours…</p>
          <p class="small muted" style="margin-top: 0.25rem">L'IA analyse votre demande et prépare les informations.</p>
        </div>
      </div>
      <div class="skeletons-grid">
        <div class="skeleton" style="height: 2.25rem"></div>
        <div class="skeleton" style="height: 2.25rem"></div>
        <div class="skeleton" style="height: 2.25rem"></div>
        <div class="skeleton" style="height: 2.25rem"></div>
      </div>
    </div>

    <!-- Résultat : failed -->
    <div v-if="draftStatus === 'failed'" class="ai-failed-card">
      <p class="bold" style="color: var(--red)">Analyse échouée</p>
      <p class="small muted" style="margin-top: 0.25rem">{{ draftError }}</p>
      <router-link
        :to="{ name: 'request-form', params: { slug } }"
        class="btn btn-outline mt-16"
      >
        Remplir le formulaire manuellement
      </router-link>
    </div>

    <!-- Résultat : done — formulaire éditable -->
    <div v-if="showForm" class="ai-result-card">
      <h3 style="margin-bottom: 1rem">Récapitulatif de votre demande</h3>
      <p class="small muted" style="margin-bottom: 1rem">Vous pouvez modifier les informations avant de confirmer.</p>

      <!-- Bandeau de guidage : tout est prêt, ou il reste des champs à compléter -->
      <div
        v-if="requiredFieldsComplete"
        class="ai-status-banner ai-status-ok"
      >
        <AppIcon name="check" :size="16" />
        <span>Formulaire pré-rempli, vérifie et envoie</span>
      </div>
      <div v-else class="ai-status-banner ai-status-warn" role="status">
        <AppIcon name="warning" :size="16" />
        <span>
          Complète ces champs pour envoyer la demande :
          <strong>{{ missingRequiredFields.map((f) => f.label).join(', ') }}</strong>
        </span>
      </div>

      <div class="grid-2">
        <div class="ai-field">
          <label>Nom du destinataire</label>
          <input
            ref="recipientNameInput"
            v-model="form.recipient_name"
            type="text"
            class="ai-input"
            :class="{ 'ai-input-error': isMissing('recipient_name') }"
            required
          />
        </div>
        <div class="ai-field">
          <label>Téléphone</label>
          <input
            ref="recipientPhoneInput"
            v-model="form.recipient_phone"
            type="tel"
            class="ai-input"
            :class="{ 'ai-input-error': isMissing('recipient_phone') }"
            required
          />
        </div>
      </div>

      <div class="ai-field">
        <label>Adresse de retrait</label>
        <input
          ref="pickupAddressInput"
          v-model="form.pickup_address"
          type="text"
          class="ai-input"
          :class="{ 'ai-input-error': isMissing('pickup_address') }"
          required
        />
      </div>

      <div class="ai-field">
        <label>Adresse de livraison</label>
        <input
          ref="deliveryAddressInput"
          v-model="form.delivery_address"
          type="text"
          class="ai-input"
          :class="{ 'ai-input-error': isMissing('delivery_address') }"
          required
        />
      </div>

      <!-- Zone de livraison & tarif fixe par zone (le client la choisit ; l'IA
           ne calcule jamais de prix). Masquée si le livreur n'a aucune zone. -->
      <div v-if="activeZones.length" ref="zoneSection" class="ai-field">
        <label>Zone de livraison</label>
        <div class="zones-grid" :class="{ 'ai-input-error': zoneMissing }">
          <div
            v-for="zone in activeZones"
            :key="zone.id"
            class="zone-card"
            :class="{ active: form.delivery_zone_id === zone.id }"
            @click="form.delivery_zone_id = zone.id"
          >
            <div class="zone-name">{{ zone.destination_zone || zone.origin_zone }}</div>
            <div v-if="zone.fixed_price" class="zone-price">{{ formatPrice(zone.fixed_price) }}</div>
          </div>
        </div>
        <p v-if="selectedZone" class="zone-recap">
          Frais de livraison : <strong>{{ formatPrice(selectedZone.fixed_price) }}</strong> — tarif fixé par le livreur.
        </p>
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

      <p v-if="createError" class="error-msg" style="margin-top: 0.5rem">{{ createError }}</p>

      <button
        class="btn btn-primary btn-submit"
        :disabled="creating || !requiredFieldsComplete"
        @click="createRequest"
      >
        <span v-if="creating" class="spinner"></span>
        <span v-else><AppIcon name="package" :size="18" /> Envoyer la demande →</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
/* 760px : largeur de l'écran « Décris ta demande » dans le prototype. */
.ai-page {
  max-width: 47.5rem;
  margin: 0 auto;
  padding-bottom: 3rem;
}

.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--fg-2);
  font-size: 0.8125rem;
  font-weight: 600;
  text-decoration: none;
  margin-bottom: 1rem;
}
.btn-back:hover { color: var(--fg); text-decoration: none; }

.ai-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.1875rem 0.5625rem;
  border-radius: 1.25rem;
  background: color-mix(in srgb, var(--violet) 16%, transparent);
  color: var(--violet);
  font-size: 0.6875rem;
  font-weight: 800;
  margin-bottom: 0.625rem;
}

.ai-subtitle {
  color: var(--fg-2);
  font-size: 0.9063rem;
  margin-top: 0.5rem;
  max-width: 37.5rem;
  line-height: 1.55;
}

/* Input card */
.ai-input-card {
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  border-radius: 1rem;
  padding: 1.375rem;
  margin-top: 1.5rem;
}

.ai-field {
  display: flex;
  flex-direction: column;
}

.ai-field label {
  display: block;
  font-size: 0.7813rem;
  font-weight: 700;
  color: var(--fg-2);
  margin-bottom: 0.375rem;
}

.ai-textarea {
  width: 100%;
  padding: 0.8125rem 0.9375rem;
  border-radius: 0.75rem;
  border: 0.0625rem solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 0.9375rem;
  font-family: inherit;
  resize: vertical;
  min-height: 7.5rem;
  line-height: 1.5;
  margin-bottom: 0.875rem;
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
  gap: 0.625rem;
}

.ai-garde-fou {
  color: var(--fg-3);
  font-size: 0.8125rem;
  margin-top: 0.75rem;
  line-height: 1.5;
}

/* Loading card */
.ai-loading-card {
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  border-radius: 1rem;
  padding: 1.375rem;
  margin-top: 1rem;
}

.skeletons-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin-top: 1rem;
}

/* Failed card */
.ai-failed-card {
  background: var(--surface);
  border: 0.0625rem solid var(--red);
  border-radius: 1rem;
  padding: 1.375rem;
  margin-top: 1rem;
}

/* Result card */
.ai-result-card {
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  border-radius: 1rem;
  padding: 1.375rem;
  margin-top: 1rem;
}

/* Bandeau de guidage : état du pré-remplissage (prêt / champs manquants). */
.ai-status-banner {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.75rem 0.875rem;
  border-radius: 0.75rem;
  font-size: 0.8125rem;
  line-height: 1.5;
  margin-bottom: 1rem;
}
.ai-status-banner .app-icon { margin-top: 0.125rem; }
.ai-status-ok {
  background: color-mix(in srgb, var(--green) 12%, transparent);
  border: 0.0625rem solid color-mix(in srgb, var(--green) 35%, transparent);
  color: var(--green-2);
}
.ai-status-warn {
  background: color-mix(in srgb, var(--amber) 14%, transparent);
  border: 0.0625rem solid color-mix(in srgb, var(--amber) 40%, transparent);
  color: var(--amber);
}
.ai-status-warn strong { color: var(--fg); }

.ai-input {
  width: 100%;
  padding: 0.8125rem 0.9375rem;
  border-radius: 0.75rem;
  border: 0.0625rem solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 0.9375rem;
  font-family: inherit;
  margin-bottom: 1rem;
  transition: border-color 0.2s;
}

.ai-input:focus {
  outline: none;
  border-color: var(--green) !important;
}

/* Champ requis encore vide : filet rouge + halo doux, pour guider le client
   vers ce qui manque. Placé après la règle :focus pour reprendre la main. */
.ai-input-error {
  border-color: var(--red) !important;
  box-shadow: 0 0 0 0.1875rem color-mix(in srgb, var(--red) 22%, transparent);
}
.ai-input-error:focus {
  border-color: var(--red) !important;
  box-shadow: 0 0 0 0.1875rem color-mix(in srgb, var(--red) 22%, transparent);
}

.ai-input::placeholder {
  color: var(--fg-3);
}

/* Zone cards (mêmes visuels que le formulaire manuel) */
.zones-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.625rem;
  margin-bottom: 0.5rem;
  border-radius: 0.875rem;
}

.zone-card {
  padding: 0.875rem 1rem;
  border-radius: 0.875rem;
  background: var(--surface-2);
  border: 0.0938rem solid var(--border);
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}

.zone-card:hover {
  border-color: var(--fg-2);
}

.zone-card.active {
  border-color: var(--green);
  background: color-mix(in srgb, var(--green) 8%, var(--surface));
}

.zone-name {
  font-size: 0.8125rem;
  font-weight: 700;
}

.zone-price {
  font-size: 0.8125rem;
  font-weight: 800;
  color: var(--green);
  margin-top: 0.25rem;
}

.zone-recap {
  font-size: 0.8125rem;
  color: var(--fg-2);
  margin: 0.375rem 0 0.75rem;
}
.zone-recap strong {
  color: var(--green);
}

.btn-submit {
  width: 100%;
  margin-top: 1rem;
  padding: 0.9375rem;
  font-size: 0.9688rem;
}
/* Grid 2 */
.grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.875rem;
}

@media (max-width: 768px) {
  .grid-2 {
    grid-template-columns: 1fr;
  }
  .zones-grid {
    grid-template-columns: 1fr;
  }
  .ai-actions {
    flex-direction: column;
  }
}
</style>
