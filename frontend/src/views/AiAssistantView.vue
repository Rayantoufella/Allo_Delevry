<script setup>
import AppIcon from "../components/AppIcon.vue"
import { ref, computed, nextTick, watch, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { apiError } from '../api/axios'
import { formatPrice } from '../lib/statuses'

const route = useRoute()
const router = useRouter()
const slug = computed(() => route.params.slug)

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

// Message d'accueil : le persona livreur se présente (brand_name dès que chargé).
const welcomeMessage = computed(
  () =>
    `Salam ! Je suis l'assistant de ${driver.value?.brand_name || 'ce livreur'}. Décris-moi ta livraison : destinataire, adresse, colis…`,
)

// Session de chat (draft créée au montage par POST /ai-request-drafts/start).
const draftId = ref(null)
const draftData = ref(null)
const draftStatus = ref(null) // pending | done | failed
const draftError = ref('')

// Bulles locales, hors historique serveur :
//  - optimistic    : message client affiché avant même le retour du POST
//  - systemBubbles : erreurs réseau / échec IA / timeout (bulles système)
//  - localAiBubbles: confirmations de l'IA (ex. demande créée)
const optimistic = ref(null)
const systemBubbles = ref([]) // { type: 'network'|'failed'|'timeout', content }
const localAiBubbles = ref([]) // { content }

// Vue du chat : accueil + historique serveur + bulles locales.
const chatMessages = computed(() => {
  const list = [{ role: 'assistant', content: welcomeMessage.value, local: true }]
  for (const m of draftData.value?.chat_history || []) list.push(m)
  if (optimistic.value) list.push({ role: 'user', content: optimistic.value.content, optimistic: true })
  for (const b of systemBubbles.value) list.push({ role: 'system', ...b })
  for (const b of localAiBubbles.value) list.push({ role: 'assistant', content: b.content, local: true })
  return list
})

const inputMessage = ref('')
const sending = ref(false)
const lastFailedText = ref('')

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

// Demande créée depuis le chat : on ne navigue pas, le chat reste ouvert.
const createdRequest = ref(null) // { id, tracking_number }

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

// Le formulaire apparaît dès que la première extraction IA est terminée et
// qu'elle a produit au moins une information exploitable.
const showForm = computed(
  () =>
    draftStatus.value === 'done' &&
    draftData.value?.generated_data &&
    Object.values(draftData.value.generated_data).some((v) => v !== null && v !== ''),
)

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

function resetForm() {
  form.value = {
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
  }
}

// Au montage : reprendre la dernière conversation en cours plutôt que d'en
// créer une nouvelle à chaque visite (la page se recharge et le chat repartait
// de zéro). On cherche le dernier draft NON VIDE (une conversation a au moins
// un message) : les drafts vides créés par de simples visites sont ignorés.
// « Nouvelle demande » appelle start() pour repartir de zéro.
async function resumeOrStart() {
  try {
    const { data } = await api.get('/ai-request-drafts?per_page=10')
    const last = (data.data || []).find((d) => d.chat_history?.length > 0)
    if (last) {
      draftId.value = last.id
      draftData.value = last
      draftStatus.value = last.status
      if (last.status === 'done' && last.generated_data) {
        applyDraft(last)
      }
      return
    }
  } catch {
    // Pas de liste disponible : on retombe sur une nouvelle conversation.
  }
  start()
}

// Crée la session de chat (appelée par « Nouvelle demande »).
async function start() {
  clearPolling()
  draftId.value = null
  draftData.value = null
  draftStatus.value = null
  draftError.value = ''
  optimistic.value = null
  systemBubbles.value = []
  localAiBubbles.value = []
  lastFailedText.value = ''
  sending.value = false
  createdRequest.value = null
  createError.value = ''
  resetForm()
  try {
    const { data } = await api.post('/ai-request-drafts/start')
    draftId.value = data.id
  } catch (err) {
    systemBubbles.value.push({
      type: 'network',
      content: apiError(err, "Impossible de démarrer la conversation. Recharge la page pour réessayer."),
    })
  }
}

// Envoie un message : bulle optimiste → POST /messages → polling GET 2,5s.
async function send(text) {
  const content = (text ?? '').trim()
  if (!content || sending.value) return

  // Défensif : sans session, on en crée une à la volée.
  if (!draftId.value) {
    try {
      const { data } = await api.post('/ai-request-drafts/start')
      draftId.value = data.id
    } catch (err) {
      systemBubbles.value.push({
        type: 'network',
        content: apiError(err, "Impossible de démarrer la conversation."),
      })
      return
    }
  }

  inputMessage.value = ''
  optimistic.value = { content }
  systemBubbles.value = []
  sending.value = true
  draftStatus.value = 'pending'
  lastFailedText.value = ''

  try {
    const { data } = await api.post(`/ai-request-drafts/${draftId.value}/messages`, {
      content,
      driver_slug: slug.value,
    })
    optimistic.value = null
    draftData.value = data
    if (data.status === 'done') {
      finishDone(data)
      return
    }
    if (data.status === 'failed') {
      finishFailed(data)
      return
    }
    pollDraft(data.id || draftId.value)
  } catch (err) {
    // Erreur réseau : on garde la bulle optimiste visible + bulle système rouge.
    sending.value = false
    lastFailedText.value = content
    systemBubbles.value.push({
      type: 'network',
      content: apiError(err, "Erreur de connexion : impossible d'envoyer le message."),
    })
  }
}

function submitMessage() {
  const text = inputMessage.value.trim()
  if (!text || sending.value) return
  send(text)
}

function retrySend() {
  const text = lastFailedText.value
  systemBubbles.value = []
  if (text) send(text)
}

function pollDraft(id) {
  clearPolling()
  pollInterval = setInterval(async () => {
    try {
      const { data } = await api.get(`/ai-request-drafts/${id}`)
      draftData.value = data
      if (data.status === 'done') {
        finishDone(data)
      } else if (data.status === 'failed') {
        finishFailed(data)
      }
    } catch {
      clearPolling()
      sending.value = false
      systemBubbles.value.push({
        type: 'network',
        content: "Erreur de connexion pendant le traitement. Clique sur « Réessayer » pour renvoyer ton message.",
      })
    }
  }, 2500)

  // Sécurité : l'IA fait 2 appels (réponse + extraction) qui peuvent prendre
  // plus d'une minute au total (fallbacks inclus). Au-delà, on rend la main.
  pollTimeout = setTimeout(() => {
    clearPolling()
    if (sending.value) {
      sending.value = false
      systemBubbles.value.push({
        type: 'timeout',
        content: "Le traitement IA prend plus de temps que prévu. Tu peux renvoyer un message ou remplir le formulaire manuellement.",
      })
    }
  }, 120000)
}

function finishDone(data) {
  clearPolling()
  sending.value = false
  draftStatus.value = 'done'
  draftData.value = data
  if (data.generated_data) applyDraft(data)
}

function finishFailed(data) {
  clearPolling()
  sending.value = false
  draftStatus.value = 'failed'
  draftError.value = data.error_message || "L'analyse a échoué."
  systemBubbles.value.push({ type: 'failed', content: draftError.value })
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
    service_id: data.service_id || gd.service_id || null,
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
    // Pas de navigation : le chat reste ouvert. On confirme en local.
    createdRequest.value = { id: data.id, tracking_number: data.tracking_number || `#${data.id}` }
    localAiBubbles.value.push({
      content: `Votre demande N°${createdRequest.value.tracking_number} est créée ! Le livreur va la prendre en charge.`,
    })
  } catch (err) {
    createError.value = apiError(err, 'Erreur lors de la création de la demande.')
  } finally {
    creating.value = false
  }
}

function goToTracking() {
  if (createdRequest.value?.id) {
    router.push({ name: 'request-detail', params: { id: createdRequest.value.id } })
  }
}

// Auto-scroll vers le bas à chaque nouveau message.
const chatBody = ref(null)
function scrollToBottom() {
  nextTick(() => {
    chatBody.value?.scrollTo({ top: chatBody.value.scrollHeight, behavior: 'smooth' })
  })
}
watch(chatMessages, scrollToBottom, { deep: true })
watch(sending, (v) => {
  if (v) scrollToBottom()
})

loadDriver()
resumeOrStart()
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

    <h2 style="font-size: 1.75rem">Discute avec l'assistant</h2>
    <p class="ai-subtitle">
      L'IA joue le livreur : réponds à ses questions, elle remplit le formulaire de demande au fil de la conversation.
    </p>

    <router-link
      :to="{ name: 'request-form', params: { slug } }"
      class="btn btn-outline ai-skip"
    >
      Plutôt choisir un service
    </router-link>

    <!-- Chat -->
    <div ref="chatBody" class="chat-window" aria-live="polite">
      <div
        v-for="(msg, i) in chatMessages"
        :key="i"
        class="chat-row"
        :class="{
          'chat-row-user': msg.role === 'user',
          'chat-row-system': msg.role === 'system',
        }"
      >
        <div v-if="msg.role !== 'user'" class="chat-avatar">
          <AppIcon name="sparkle" :size="14" />
        </div>
        <div
          class="chat-bubble"
          :class="{
            'chat-bubble-user': msg.role === 'user',
            'chat-bubble-ai': msg.role === 'assistant',
            'chat-bubble-system': msg.role === 'system',
            'chat-bubble-error': msg.role === 'system' && msg.type === 'network',
          }"
        >
          <p class="chat-text">{{ msg.content }}</p>
          <button
            v-if="msg.role === 'system' && msg.type === 'network'"
            class="btn btn-outline chat-retry"
            @click="retrySend"
          >
            Réessayer
          </button>
          <router-link
            v-if="msg.role === 'system' && (msg.type === 'failed' || msg.type === 'timeout')"
            :to="{ name: 'request-form', params: { slug } }"
            class="chat-manual"
          >
            Remplir le formulaire manuellement →
          </router-link>
        </div>
      </div>

      <!-- Indicateur de frappe : l'IA écrit… -->
      <div v-if="sending" class="chat-row">
        <div class="chat-avatar"><AppIcon name="sparkle" :size="14" /></div>
        <div class="chat-typing"><span></span><span></span><span></span> L'IA écrit…</div>
      </div>
    </div>

    <!-- Zone de saisie -->
    <form class="chat-input-row" @submit.prevent="submitMessage">
      <input
        v-model="inputMessage"
        type="text"
        placeholder="Décris ta livraison : destinataire, adresse, colis…"
        autocomplete="off"
        :disabled="sending"
        aria-label="Message pour l'assistant"
      />
      <button
        class="btn btn-primary chat-send"
        type="submit"
        :disabled="sending || !inputMessage.trim()"
        aria-label="Envoyer le message"
      >
        <AppIcon name="send" :size="18" />
      </button>
    </form>

    <p v-if="createError" class="error-msg" style="margin-top: 0.75rem">{{ createError }}</p>

    <!-- Résultat : done — formulaire éditable (masqué après création de la demande) -->
    <div v-if="showForm && !createdRequest" class="ai-result-card">
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

      <button
        class="btn btn-primary btn-submit"
        :disabled="creating || !requiredFieldsComplete"
        @click="createRequest"
      >
        <span v-if="creating" class="spinner"></span>
        <span v-else><AppIcon name="package" :size="18" /> Envoyer la demande →</span>
      </button>
    </div>

    <!-- Après création : confirmation + actions, le chat reste ouvert -->
    <div v-if="createdRequest" class="ai-result-card ai-success-card">
      <div class="flex" style="gap: 0.875rem; align-items: flex-start">
        <span class="chat-success-icon"><AppIcon name="check" :size="18" /></span>
        <div>
          <p class="bold">Demande créée !</p>
          <p class="small muted" style="margin-top: 0.25rem">
            Votre demande N°{{ createdRequest.tracking_number }} est créée. Le livreur va la prendre en charge.
          </p>
        </div>
      </div>
      <div class="btn-group" style="margin-top: 1rem">
        <button class="btn btn-primary" @click="goToTracking">Voir le suivi</button>
        <button class="btn btn-outline" @click="start">Nouvelle demande</button>
      </div>
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

.ai-skip {
  margin-top: 1rem;
  display: inline-flex;
}

/* ---------- Chat ---------- */
.chat-window {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-height: 26rem;
  min-height: 13rem;
  overflow-y: auto;
  padding: 1.25rem;
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  border-radius: 1rem;
  margin-top: 1.25rem;
  scroll-behavior: smooth;
}

.chat-row {
  display: flex;
  gap: 0.625rem;
  align-items: flex-end;
}
.chat-row-user { justify-content: flex-end; }
.chat-row-system { justify-content: center; }

.chat-avatar {
  width: 1.875rem;
  height: 1.875rem;
  flex: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: color-mix(in srgb, var(--violet) 16%, transparent);
  color: var(--violet);
}

.chat-bubble {
  max-width: 78%;
  padding: 0.6875rem 0.9375rem;
  border-radius: 1rem;
  font-size: 0.9063rem;
  line-height: 1.5;
  word-break: break-word;
  white-space: pre-wrap;
}

/* Client : à droite, fond vert. */
.chat-bubble-user {
  background: var(--green);
  color: var(--green-ink);
  border-bottom-right-radius: 0.25rem;
}

/* IA : à gauche, surface avec avatar sparkle. */
.chat-bubble-ai {
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  color: var(--fg);
  border-bottom-left-radius: 0.25rem;
}

/* Système : neutre (timeout / échec) ou rouge (erreur réseau). */
.chat-bubble-system {
  background: color-mix(in srgb, var(--amber) 12%, transparent);
  border: 0.0625rem solid color-mix(in srgb, var(--amber) 40%, transparent);
  color: var(--fg);
  text-align: center;
}
.chat-bubble-error {
  background: color-mix(in srgb, var(--red) 10%, transparent);
  border-color: color-mix(in srgb, var(--red) 40%, transparent);
  color: var(--red);
}

.chat-text { margin: 0; }

.chat-retry {
  margin-top: 0.5rem;
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
}

.chat-manual {
  display: inline-block;
  margin-top: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--green);
  text-decoration: none;
}

/* « L'IA écrit… » : 3 points animés. */
.chat-typing {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 1rem;
  border-bottom-left-radius: 0.25rem;
  padding: 0.6875rem 0.9375rem;
  color: var(--fg-2);
  font-size: 0.8125rem;
}
.chat-typing span {
  width: 0.375rem;
  height: 0.375rem;
  border-radius: 50%;
  background: var(--fg-2);
  animation: chat-blink 1.2s infinite ease-in-out;
}
.chat-typing span:nth-child(2) { animation-delay: 0.2s; }
.chat-typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes chat-blink {
  0%, 80%, 100% { opacity: 0.25; }
  40% { opacity: 1; }
}

/* Zone de saisie : input + bouton envoyer. */
.chat-input-row {
  display: flex;
  gap: 0.625rem;
  margin-top: 0.875rem;
}
.chat-input-row input {
  flex: 1;
  min-width: 0;
  padding: 0.8125rem 0.9375rem;
  border-radius: 0.75rem;
  border: 0.0625rem solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 0.9375rem;
  font-family: inherit;
  transition: border-color 0.2s;
}
.chat-input-row input:focus {
  outline: none;
  border-color: var(--green) !important;
}
.chat-input-row input::placeholder {
  color: var(--fg-3);
}
.chat-send {
  padding: 0.8125rem 1.125rem;
  flex: none;
}

/* Carte de succès après création de la demande. */
.chat-success-icon {
  width: 2.25rem;
  height: 2.25rem;
  flex: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: color-mix(in srgb, var(--green) 16%, transparent);
  color: var(--green);
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
