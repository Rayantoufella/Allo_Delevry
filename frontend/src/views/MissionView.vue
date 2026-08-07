<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api/axios'
import { apiError } from '../api/axios'
import { usePolling } from '../composables/usePolling'
import { STATUS, TERMINAL_STATUSES, PROOF_LABELS, formatPrice, formatDateTime } from '../lib/statuses'
import StatusBadge from '../components/StatusBadge.vue'
import StatusTimeline from '../components/StatusTimeline.vue'
import ChatPanel from '../components/ChatPanel.vue'
import ToastMessage from '../components/driver/ToastMessage.vue'

/**
 * Gestion d'une mission livreur — GET /delivery-requests/{id} + polling 5 s.
 * Actions contextuelles selon la machine à états stricte du backend.
 */
const route = useRoute()
const id = computed(() => String(route.params.id))

const { data, loading, error, start, stop, refresh } = usePolling(async () => {
  const res = await api.get(`/delivery-requests/${id.value}`)
  return res.data
}, 5000)

const request = computed(() => data.value || {})
const isTerminal = computed(() => TERMINAL_STATUSES.includes(request.value.status))

// ---- Timeline (chargée une fois) ----
const history = ref([])

async function loadHistory() {
  try {
    const res = await api.get('/request-status-histories', {
      params: { delivery_request_id: id.value },
    })
    history.value = res.data.data ?? res.data ?? []
  } catch {
    history.value = []
  }
}

// ---- Preuves ----
const proofs = ref([])
const proofsLoading = ref(true)
const proofError = ref('')

async function loadProofs() {
  try {
    const res = await api.get('/delivery-proofs', {
      params: { delivery_request_id: id.value },
    })
    proofs.value = res.data.data ?? res.data ?? []
  } catch (err) {
    proofError.value = apiError(err)
  } finally {
    proofsLoading.value = false
  }
}

const hasPickupPhoto = computed(() =>
  proofs.value.some((p) => p.proof_type === 'pickup_photo'),
)

// ---- Actions de statut ----
const actionError = ref('')
const actionLoading = ref(false)

function setActionError(err) {
  actionError.value = apiError(err)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function clearActionError() {
  actionError.value = ''
}

async function changeStatus(status, extra = {}) {
  actionLoading.value = true
  clearActionError()
  try {
    await api.patch(`/delivery-requests/${id.value}/status`, { status, ...extra })
    await refresh()
  } catch (err) {
    setActionError(err)
  } finally {
    actionLoading.value = false
  }
}

// ---- Proposer un prix / refuser ----
const showPriceForm = ref(false)
const price = ref('')
const priceError = ref('')
const refuseComment = ref('')
const showRefuseForm = ref(false)

async function proposePrice() {
  priceError.value = ''
  const value = String(price.value || '').trim()
  if (!value || Number.isNaN(Number(value)) || Number(value) < 0) {
    priceError.value = 'Saisissez un montant valide en FCFA.'
    return
  }
  await changeStatus(STATUS.PRIX_PROPOSE, { proposed_price: Number(value) })
  if (!actionError.value) {
    showPriceForm.value = false
    price.value = ''
  }
}

async function refuseMission() {
  const comment = refuseComment.value.trim()
  await changeStatus(STATUS.REFUSEE, comment ? { comment } : {})
  if (!actionError.value) {
    showRefuseForm.value = false
    refuseComment.value = ''
  }
}

// ---- Upload photo de récupération ----
const pickupFile = ref(null)
const pickupPreview = ref('')
const pickupUploading = ref(false)
const pickupError = ref('')

function onPickupFile(e) {
  const file = e.target.files?.[0]
  pickupError.value = ''
  if (!file) return
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    pickupError.value = 'Format non pris en charge (jpg, png ou webp).'
    e.target.value = ''
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    pickupError.value = 'Photo trop lourde (max 2 Mo).'
    e.target.value = ''
    return
  }
  pickupFile.value = file
  pickupPreview.value = URL.createObjectURL(file)
}

async function uploadPickupPhoto() {
  if (!pickupFile.value) {
    pickupError.value = 'Sélectionnez d’abord une photo.'
    return
  }
  pickupUploading.value = true
  pickupError.value = ''
  try {
    const fd = new FormData()
    fd.append('delivery_request_id', id.value)
    fd.append('proof_type', 'pickup_photo')
    fd.append('file', pickupFile.value)
    await api.post('/delivery-proofs', fd)
    await loadProofs()
    pickupFile.value = null
    pickupPreview.value = ''
    if (document.getElementById('pickup-file-input')) {
      document.getElementById('pickup-file-input').value = ''
    }
  } catch (err) {
    pickupError.value = apiError(err, 'Échec de l’envoi de la photo.')
  } finally {
    pickupUploading.value = false
  }
}

// ---- Upload preuve de livraison ----
const deliveryFile = ref(null)
const deliveryPreview = ref('')
const deliveryType = ref('photo')
const receiverName = ref('')
const deliveryUploading = ref(false)
const deliveryError = ref('')

function onDeliveryFile(e) {
  const file = e.target.files?.[0]
  deliveryError.value = ''
  if (!file) return
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    deliveryError.value = 'Format non pris en charge (jpg, png ou webp).'
    e.target.value = ''
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    deliveryError.value = 'Fichier trop lourd (max 2 Mo).'
    e.target.value = ''
    return
  }
  deliveryFile.value = file
  deliveryPreview.value = URL.createObjectURL(file)
}

async function uploadDeliveryProof() {
  if (!deliveryFile.value) {
    deliveryError.value = 'Sélectionnez d’abord un fichier.'
    return
  }
  deliveryUploading.value = true
  deliveryError.value = ''
  try {
    const fd = new FormData()
    fd.append('delivery_request_id', id.value)
    fd.append('proof_type', deliveryType.value)
    fd.append('file', deliveryFile.value)
    if (receiverName.value.trim()) {
      fd.append('receiver_name', receiverName.value.trim())
    }
    await api.post('/delivery-proofs', fd)
    await loadProofs()
    deliveryFile.value = null
    deliveryPreview.value = ''
    receiverName.value = ''
    if (document.getElementById('delivery-file-input')) {
      document.getElementById('delivery-file-input').value = ''
    }
  } catch (err) {
    deliveryError.value = apiError(err, 'Échec de l’envoi de la preuve.')
  } finally {
    deliveryUploading.value = false
  }
}

async function deleteProof(p) {
  try {
    await api.delete(`/delivery-proofs/${p.id}`)
    await loadProofs()
  } catch (err) {
    proofError.value = apiError(err)
  }
}

// ---- Code de confirmation ----
const code = ref('')
const codeExpiresAt = ref(null)
const codeLoading = ref(false)
const codeError = ref('')
const now = ref(Date.now())
let countdownTimer = null

const codeRemaining = computed(() => {
  if (!codeExpiresAt.value) return 0
  return Math.max(0, Math.floor((codeExpiresAt.value - now.value) / 1000))
})

const codeExpired = computed(() => codeRemaining.value === 0)

const codeRemainingLabel = computed(() => {
  const total = codeRemaining.value
  const mm = String(Math.floor(total / 60)).padStart(2, '0')
  const ss = String(total % 60).padStart(2, '0')
  return `${mm}:${ss}`
})

async function generateCode() {
  codeLoading.value = true
  codeError.value = ''
  try {
    const res = await api.post(`/delivery-requests/${id.value}/generate-code`)
    code.value = String(res.data.code ?? '')
    codeExpiresAt.value = Date.now() + 30 * 60 * 1000
  } catch (err) {
    codeError.value = apiError(err, 'Impossible de générer le code.')
  } finally {
    codeLoading.value = false
  }
}

watch(codeRemaining, (remaining) => {
  if (remaining === 0 && codeExpiresAt.value) {
    code.value = ''
    codeExpiresAt.value = null
  }
})

// ---- Incident ----
const showIncidentForm = ref(false)
const incidentType = ref('retard')
const incidentDescription = ref('')
const incidentSubmitting = ref(false)
const incidentError = ref('')
const incidentSent = ref(false)

const INCIDENT_TYPES = [
  { value: 'colis_abime', label: 'Colis abîmé' },
  { value: 'retard', label: 'Retard' },
  { value: 'adresse_incomplete', label: 'Adresse incomplète' },
  { value: 'autre', label: 'Autre' },
]

async function submitIncident() {
  incidentError.value = ''
  incidentSubmitting.value = true
  try {
    await api.post('/incidents', {
      delivery_request_id: id.value,
      type: incidentType.value,
      description: incidentDescription.value.trim() || undefined,
    })
    incidentSent.value = true
    showIncidentForm.value = false
    incidentDescription.value = ''
  } catch (err) {
    incidentError.value = apiError(err, 'Impossible de signaler l’incident.')
  } finally {
    incidentSubmitting.value = false
  }
}

// ---- Toast ----
const toast = ref('')
let toastTimer = null

function showToast(message) {
  toast.value = message
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 3500)
}

// ---- Cycle de vie ----
function setupForRequest() {
  clearActionError()
  history.value = []
  proofs.value = []
  proofsLoading.value = true
  code.value = ''
  codeExpiresAt.value = null
  showPriceForm.value = false
  showRefuseForm.value = false
  showIncidentForm.value = false
  incidentSent.value = false
  loadHistory()
  loadProofs()
  start()
}

watch(id, () => {
  stop()
  setupForRequest()
})

onMounted(() => {
  setupForRequest()
  countdownTimer = setInterval(() => {
    now.value = Date.now()
  }, 1000)
})

onBeforeUnmount(() => {
  stop()
  if (countdownTimer) clearInterval(countdownTimer)
  if (toastTimer) clearTimeout(toastTimer)
})
</script>

<template>
  <div class="page container">
    <div v-if="loading && !data" class="flex-col">
      <div class="skeleton skel-hdr"></div>
      <div class="grid-2">
        <div class="skeleton skel-card"></div>
        <div class="skeleton skel-card"></div>
      </div>
    </div>

    <div v-else-if="error && !data" class="card">
      <h3>Impossible de charger la mission</h3>
      <p class="muted small mt-8">{{ apiError(error, 'Erreur de chargement.') }}</p>
      <button class="btn btn-outline mt-16" @click="start()">Réessayer</button>
    </div>

    <template v-else>
      <!-- En-tête -->
      <div class="card hdr">
        <div class="flex-between wrap">
          <div class="flex wrap">
            <h1 class="tracking">{{ request.tracking_number || `Mission #${request.id}` }}</h1>
            <StatusBadge :status="request.status" />
          </div>
          <div class="flex wrap amounts">
            <span v-if="request.proposed_price" class="amt">
              Prix proposé<br /><b>{{ formatPrice(request.proposed_price) }}</b>
            </span>
            <span v-if="request.amount_to_collect" class="amt">
              À encaisser<br /><b>{{ formatPrice(request.amount_to_collect) }}</b>
            </span>
            <span v-if="request.product_amount" class="amt">
              Montant produit<br /><b>{{ formatPrice(request.product_amount) }}</b>
            </span>
          </div>
        </div>
      </div>

      <!-- Erreur d'action -->
      <div v-if="actionError" class="card action-error mt-16">
        <div class="flex-between wrap">
          <div>
            <b class="small">Action impossible</b>
            <p class="small mt-8">{{ actionError }}</p>
            <p v-if="request.status === STATUS.CONFIRMEE" class="small faint mt-8">
              💡 La photo de récupération est obligatoire pour valider la récupération du colis (RG06). Déposez-la dans la section « Preuves » ci-dessous.
            </p>
          </div>
          <button class="btn btn-ghost" @click="clearActionError()">Fermer</button>
        </div>
      </div>

      <div class="grid-2 mt-16">
        <div class="flex-col col">
          <!-- Actions contextuelles -->
          <section class="card">
            <h3 class="mb-16">Actions</h3>

            <!-- en_attente : proposer un prix ou refuser -->
            <div v-if="request.status === STATUS.EN_ATTENTE">
              <div v-if="!showPriceForm && !showRefuseForm">
                <button class="btn btn-primary" @click="showPriceForm = true">Proposer un prix</button>
                <button class="btn btn-outline mt-8" @click="showRefuseForm = true">Refuser</button>
                <p class="faint small mt-8">Le client attend votre proposition de prix pour confirmer la mission.</p>
              </div>

              <div v-else-if="showPriceForm">
                <label class="field">
                  <span>Montant proposé (FCFA)</span>
                  <input v-model="price" class="input" :class="{ 'input-error': priceError }" type="number" min="0" placeholder="Ex : 2500" @keyup.enter="proposePrice()" />
                  <span v-if="priceError" class="error-msg">{{ priceError }}</span>
                </label>
                <div class="flex">
                  <button class="btn btn-primary" :disabled="actionLoading" @click="proposePrice()">
                    {{ actionLoading ? '…' : 'Envoyer la proposition' }}
                  </button>
                  <button class="btn btn-ghost" :disabled="actionLoading" @click="showPriceForm = false">Annuler</button>
                </div>
              </div>

              <div v-else>
                <label class="field">
                  <span>Motif du refus (optionnel)</span>
                  <textarea v-model="refuseComment" class="input" placeholder="Ex : hors de ma zone de livraison"></textarea>
                </label>
                <div class="flex">
                  <button class="btn btn-danger" :disabled="actionLoading" @click="refuseMission()">
                    {{ actionLoading ? '…' : 'Confirmer le refus' }}
                  </button>
                  <button class="btn btn-ghost" :disabled="actionLoading" @click="showRefuseForm = false">Annuler</button>
                </div>
              </div>
            </div>

            <!-- prix_propose : attendre la décision du client -->
            <div v-else-if="request.status === STATUS.PRIX_PROPOSE">
              <div class="wait-box">
                <p class="small muted">Votre proposition :</p>
                <p class="price-big">{{ formatPrice(request.proposed_price) }}</p>
                <p class="small muted">En attente de la décision du client…</p>
                <p class="faint small mt-8">La page se met à jour automatiquement dès que le client confirme ou annule.</p>
              </div>
              <button class="btn btn-outline mt-16" @click="showRefuseForm = true">Retirer ma proposition</button>
              <div v-if="showRefuseForm" class="mt-16">
                <label class="field">
                  <span>Motif (optionnel)</span>
                  <textarea v-model="refuseComment" class="input" placeholder="Ex : délai trop long"></textarea>
                </label>
                <div class="flex">
                  <button class="btn btn-danger" :disabled="actionLoading" @click="refuseMission()">
                    {{ actionLoading ? '…' : 'Confirmer le refus' }}
                  </button>
                  <button class="btn btn-ghost" :disabled="actionLoading" @click="showRefuseForm = false">Annuler</button>
                </div>
              </div>
            </div>

            <!-- confirmee : photo de récupération obligatoire puis colis récupéré -->
            <div v-else-if="request.status === STATUS.CONFIRMEE">
              <div class="pickup-box" :class="{ ok: hasPickupPhoto }">
                <div class="flex-between wrap">
                  <div>
                    <b class="small">📦 Photo de récupération (obligatoire)</b>
                    <p class="small muted mt-8">
                      {{ hasPickupPhoto
                        ? 'Photo enregistrée — vous pouvez récupérer le colis.'
                        : 'Prenez une photo du colis avant de le récupérer (RG06). Sans photo, la transition est bloquée.' }}
                    </p>
                  </div>
                  <span v-if="hasPickupPhoto" class="badge badge-green">✓ Photo OK</span>
                  <span v-else class="badge badge-red">Manquante</span>
                </div>

                <div v-if="!hasPickupPhoto" class="mt-16">
                  <label class="field">
                    <span>Photo du colis (jpg, png, webp — max 2 Mo)</span>
                    <input id="pickup-file-input" type="file" accept="image/jpeg,image/png,image/webp" @change="onPickupFile" />
                  </label>
                  <div v-if="pickupPreview" class="preview">
                    <img :src="pickupPreview" alt="Aperçu photo de récupération" />
                  </div>
                  <span v-if="pickupError" class="error-msg">{{ pickupError }}</span>
                  <button class="btn btn-outline mt-8" :disabled="pickupUploading || !pickupFile" @click="uploadPickupPhoto()">
                    {{ pickupUploading ? 'Envoi…' : 'Enregistrer la photo' }}
                  </button>
                </div>
              </div>

              <button
                class="btn btn-primary mt-16"
                :disabled="actionLoading || !hasPickupPhoto"
                @click="changeStatus(STATUS.COLIS_RECUPERE)"
              >
                {{ actionLoading ? '…' : '📦 Colis récupéré' }}
              </button>
              <p v-if="!hasPickupPhoto" class="faint small mt-8">Le bouton s’active dès que la photo de récupération est enregistrée.</p>
            </div>

            <!-- colis_recupere : départ en livraison -->
            <div v-else-if="request.status === STATUS.COLIS_RECUPERE">
              <div class="wait-box">
                <p class="bold">Colis récupéré ✓</p>
                <p class="small muted mt-8">Confirmez votre départ pour lancer la livraison et pouvoir générer le code de confirmation.</p>
              </div>
              <button class="btn btn-primary mt-16" :disabled="actionLoading" @click="changeStatus(STATUS.EN_LIVRAISON)">
                {{ actionLoading ? '…' : '🛵 Départ en livraison' }}
              </button>
            </div>

            <!-- en_livraison : code + échec -->
            <div v-else-if="request.status === STATUS.EN_LIVRAISON">
              <div v-if="!code" class="mb-16">
                <p class="small muted">Générez un code à 6 chiffres à communiquer au client par téléphone : il le saisira pour confirmer la réception du colis.</p>
                <button class="btn btn-primary mt-16" :disabled="codeLoading" @click="generateCode()">
                  {{ codeLoading ? '…' : '🔐 Générer le code de confirmation' }}
                </button>
                <span v-if="codeError" class="error-msg block mt-8">{{ codeError }}</span>
              </div>

              <div v-else class="code-box">
                <p class="small muted">Code de confirmation — à communiquer au client :</p>
                <div class="code big">{{ code }}</div>
                <p class="small yellow">⏱ Valide 30 minutes — expire dans {{ codeRemainingLabel }}</p>
                <p class="faint small mt-8">Le client saisira ce code pour confirmer la livraison. Ne le partagez qu'avec lui.</p>
                <button class="btn btn-ghost mt-8" :disabled="codeLoading" @click="generateCode()">Régénérer un code</button>
              </div>

              <div class="divider"></div>

              <button class="btn btn-danger" :disabled="actionLoading" @click="changeStatus(STATUS.ECHEC)">
                {{ actionLoading ? '…' : '⚠️ Signaler un échec' }}
              </button>
              <p class="faint small mt-8">L'échec met fin à la mission (une preuve reste facultative).</p>
            </div>

            <!-- Statuts terminaux -->
            <div v-else>
              <div class="wait-box">
                <p class="bold">
                  {{ request.status === STATUS.LIVREE ? '🏁 Mission livrée avec succès' : '' }}
                  {{ request.status === STATUS.REFUSEE ? '🚫 Vous avez refusé cette demande' : '' }}
                  {{ request.status === STATUS.ECHEC ? '⚠️ Mission en échec' : '' }}
                  {{ request.status === STATUS.ANNULEE ? '❌ Demande annulée par le client' : '' }}
                </p>
                <p v-if="request.status === STATUS.LIVREE && request.delivered_at" class="small muted mt-8">
                  Livrée le {{ formatDateTime(request.delivered_at) }}.
                </p>
                <p v-if="request.status === STATUS.REFUSEE" class="small muted mt-8">
                  Cette demande n'est plus active. Vous pouvez être contacté par le client via le chat.
                </p>
              </div>
            </div>
          </section>

          <!-- Informations de la demande -->
          <section class="card">
            <h3 class="mb-16">Informations de la demande</h3>
            <dl class="info">
              <div class="row"><dt>Destinataire</dt><dd>{{ request.recipient_name || '—' }}<template v-if="request.recipient_phone"> · {{ request.recipient_phone }}</template></dd></div>
              <div class="row"><dt>Point de retrait</dt><dd>{{ request.pickup_address || '—' }}</dd></div>
              <div class="row"><dt>Adresse de livraison</dt><dd>{{ request.delivery_address || '—' }}</dd></div>
              <div class="row"><dt>Description du colis</dt><dd>{{ request.package_description || '—' }}</dd></div>
              <div class="row"><dt>Montant du produit</dt><dd>{{ formatPrice(request.product_amount) }}</dd></div>
              <div class="row"><dt>Montant à encaisser</dt><dd>{{ formatPrice(request.amount_to_collect) }}</dd></div>
              <div class="row"><dt>Prix proposé</dt><dd>{{ formatPrice(request.proposed_price) }}</dd></div>
              <div class="row"><dt>Demande créée le</dt><dd>{{ formatDateTime(request.created_at) }}</dd></div>
              <div v-if="request.scheduled_at" class="row"><dt>Planifiée le</dt><dd>{{ formatDateTime(request.scheduled_at) }}</dd></div>
            </dl>
          </section>

          <!-- Preuves -->
          <section class="card">
            <div class="flex-between wrap mb-16">
              <h3>Preuves</h3>
              <span v-if="proofsLoading" class="spinner"></span>
            </div>
            <span v-if="proofError" class="error-msg block mb-16">{{ proofError }}</span>

            <div v-if="proofs.length" class="proof-grid">
              <div v-for="p in proofs" :key="p.id" class="proof">
                <img v-if="p.file_url" :src="p.file_url" :alt="PROOF_LABELS[p.proof_type] || p.proof_type" loading="lazy" />
                <div class="proof-meta flex-between">
                  <span class="badge" :class="p.proof_type === 'pickup_photo' ? 'badge-green' : 'badge-blue'">
                    {{ PROOF_LABELS[p.proof_type] || p.proof_type }}
                  </span>
                  <button class="btn btn-ghost del" title="Supprimer la preuve" @click="deleteProof(p)">✕</button>
                </div>
                <p v-if="p.receiver_name" class="faint small">Reçu par : {{ p.receiver_name }}</p>
                <p class="faint small">{{ formatDateTime(p.created_at) }}</p>
              </div>
            </div>
            <p v-else class="muted small">Aucune preuve pour le moment.</p>

            <!-- Upload preuve de livraison -->
            <template v-if="request.status === STATUS.EN_LIVRAISON">
              <div class="divider"></div>
              <h4 class="mb-8">Ajouter une preuve de livraison</h4>
              <label class="field">
                <span>Type de preuve</span>
                <select v-model="deliveryType" class="input">
                  <option value="photo">Photo</option>
                  <option value="signature">Signature</option>
                  <option value="ticket">Ticket</option>
                </select>
              </label>
              <label class="field">
                <span>Fichier (jpg, png, webp — max 2 Mo)</span>
                <input id="delivery-file-input" type="file" accept="image/jpeg,image/png,image/webp" @change="onDeliveryFile" />
              </label>
              <label v-if="deliveryType === 'signature'" class="field">
                <span>Nom du destinataire</span>
                <input v-model="receiverName" class="input" placeholder="Nom de la personne qui a signé" />
              </label>
              <div v-if="deliveryPreview" class="preview">
                <img :src="deliveryPreview" alt="Aperçu de la preuve" />
              </div>
              <span v-if="deliveryError" class="error-msg block mt-8">{{ deliveryError }}</span>
              <button class="btn btn-outline mt-8" :disabled="deliveryUploading || !deliveryFile" @click="uploadDeliveryProof()">
                {{ deliveryUploading ? 'Envoi…' : 'Enregistrer la preuve' }}
              </button>
              <p v-if="request.status === STATUS.EN_LIVRAISON" class="faint small mt-8">
                💡 Le client ne peut confirmer la livraison que si au moins une preuve existe (RG06).
              </p>
            </template>

            <template v-else-if="request.status === STATUS.CONFIRMEE">
              <p class="faint small mt-16">
                📷 Une photo de récupération {{ hasPickupPhoto ? 'a été enregistrée' : 'est requise avant la récupération du colis' }} (RG06).
              </p>
            </template>
          </section>

          <!-- Incident -->
          <section class="card">
            <div class="flex-between wrap">
              <h3>Incident</h3>
              <button v-if="!showIncidentForm" class="btn btn-outline" @click="showIncidentForm = true">Signaler un incident</button>
            </div>
            <p v-if="incidentSent" class="small badge badge-yellow mt-8">✓ Incident signalé — l'équipe a été notifiée.</p>

            <div v-if="showIncidentForm" class="mt-16">
              <label class="field">
                <span>Type d'incident</span>
                <select v-model="incidentType" class="input">
                  <option v-for="t in INCIDENT_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
              </label>
              <label class="field">
                <span>Description (optionnel)</span>
                <textarea v-model="incidentDescription" class="input" placeholder="Décrivez la situation…"></textarea>
              </label>
              <span v-if="incidentError" class="error-msg block mb-8">{{ incidentError }}</span>
              <div class="flex">
                <button class="btn btn-danger" :disabled="incidentSubmitting" @click="submitIncident()">
                  {{ incidentSubmitting ? '…' : 'Envoyer le signalement' }}
                </button>
                <button class="btn btn-ghost" :disabled="incidentSubmitting" @click="showIncidentForm = false">Annuler</button>
              </div>
            </div>
          </section>
        </div>

        <div class="flex-col col">
          <!-- Timeline -->
          <section class="card">
            <h3 class="mb-16">Suivi de la mission</h3>
            <StatusTimeline :history="history" :current="request.status" />
          </section>

          <!-- Chat -->
          <section class="card">
            <h3 class="mb-16">Discussion avec le client</h3>
            <ChatPanel :delivery-request-id="request.id" compact />
          </section>
        </div>
      </div>
    </template>

    <ToastMessage :message="toast" @close="toast = ''" />
  </div>
</template>

<style scoped>
.tracking {
  font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
  font-size: 1.35rem;
}
.skel-hdr { height: 90px; }
.skel-card { height: 260px; }
.amounts {
  gap: 18px;
}
.amt {
  text-align: right;
  font-size: 0.8rem;
  color: var(--text-dim);
  line-height: 1.35;
}
.amt b { color: var(--text); font-size: 1rem; }

.action-error {
  border: 1px solid rgba(248, 113, 113, 0.35);
  background: rgba(248, 113, 113, 0.08);
}

.wait-box {
  background: var(--card-soft);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 14px 16px;
}
.price-big {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--brand);
  margin: 4px 0;
}

.pickup-box {
  background: var(--card-soft);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 14px 16px;
}
.pickup-box.ok {
  border-color: rgba(34, 197, 111, 0.4);
}

.preview img {
  max-width: 100%;
  max-height: 220px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-strong);
  margin-top: 6px;
}

.code-box {
  background: var(--card-soft);
  border: 1px dashed var(--border-strong);
  border-radius: var(--radius-sm);
  padding: 16px;
  text-align: center;
}
.code {
  font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
  font-size: 3rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  color: var(--brand);
  margin: 8px 0;
}
.yellow { color: var(--warning); }
.block { display: block; }

.info {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.info .row {
  display: grid;
  grid-template-columns: 150px 1fr;
  gap: 12px;
  font-size: 0.85rem;
}
.info dt {
  color: var(--text-dim);
  font-weight: 700;
}
.info dd { color: var(--text); overflow-wrap: anywhere; }

.proof-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 12px;
}
.proof {
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 8px;
  background: var(--card-soft);
}
.proof img {
  width: 100%;
  height: 110px;
  object-fit: cover;
  border-radius: 6px;
  border: 1px solid var(--border);
}
.proof-meta { margin: 8px 0 4px; }
.del {
  padding: 2px 8px;
  color: var(--danger);
}
</style>
