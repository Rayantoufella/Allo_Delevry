<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api/axios'
import { apiError } from '../api/axios'
import { usePolling } from '../composables/usePolling'
import { STATUS, TERMINAL_STATUSES, PROOF_LABELS, formatPrice, formatDateTime } from '../lib/statuses'
import AppIcon from '../components/AppIcon.vue'
import DriverSidebar from '../components/driver/DriverSidebar.vue'
import StatusBadge from '../components/StatusBadge.vue'
import StatusTimeline from '../components/StatusTimeline.vue'
import ChatPanel from '../components/ChatPanel.vue'
import ToastMessage from '../components/driver/ToastMessage.vue'
import ImageLightbox from '../components/ImageLightbox.vue'

/**
 * Gestion d'une mission livreur — GET /delivery-requests/{id} (resource NUE) + polling 5 s.
 * Actions contextuelles selon la machine à états stricte du backend :
 *   en_attente → prix_propose | refusee
 *   prix_propose → refusee (le client confirme ou annule de son côté)
 *   confirmee → colis_recupere (photo de récupération obligatoire)
 *   colis_recupere → en_livraison
 *   en_livraison → livreur_arrive (le livreur confirme son arrivée) | echec
 *   livreur_arrive → livree (remise confirmée par le livreur, RG06)
 */
const route = useRoute()
const router = useRouter()
const id = computed(() => String(route.params.id))

const { data, loading, error, start, stop, refresh } = usePolling(async () => {
  const res = await api.get(`/delivery-requests/${id.value}`)
  // Le backend renvoie la resource dans un wrapper { data: {...} } :
  // on l'extrait de façon résiliente (resource simple OU wrapper).
  return res.data.data ?? res.data
}, 5000)

const request = computed(() => data.value || {})
const isTerminal = computed(() => TERMINAL_STATUSES.includes(request.value.status))

// ---- Historique des statuts (rechargé à chaque changement de statut) ----
const history = ref([])
const historyLoading = ref(false)

async function loadHistory() {
  historyLoading.value = true
  try {
    const res = await api.get('/request-status-histories', {
      params: { delivery_request_id: id.value },
    })
    history.value = res.data.data ?? res.data ?? []
  } catch {
    history.value = []
  } finally {
    historyLoading.value = false
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
}

async function changeStatus(status, extra = {}) {
  actionLoading.value = true
  actionError.value = ''
  try {
    await api.patch(`/delivery-requests/${id.value}/status`, { status, ...extra })
    await refresh()
  } catch (err) {
    setActionError(err)
  } finally {
    actionLoading.value = false
  }
}

// ---- Confirmer la remise (livreur_arrive -> livree) ----
// Le livreur, présent sur place, valide que le client a récupéré la commande.
// RG06 : une preuve de livraison doit avoir été enregistrée avant.
async function confirmHandover() {
  actionLoading.value = true
  actionError.value = ''
  try {
    await api.post(`/delivery-requests/${id.value}/confirm-handover`)
    await refresh()
  } catch (err) {
    setActionError(err)
  } finally {
    actionLoading.value = false
  }
}

// ---- Confirmer l'arrivée (en_livraison -> livreur_arrive) ----
// Tous les boutons de statut sont côté livreur : c'est lui qui confirme
// qu'il est arrivé à l'adresse, puis qu'il a remis la commande.
async function confirmArrival() {
  actionLoading.value = true
  actionError.value = ''
  try {
    await api.post(`/delivery-requests/${id.value}/confirm-arrival`)
    await refresh()
  } catch (err) {
    setActionError(err)
  } finally {
    actionLoading.value = false
  }
}

// ---- Accepter / refuser ----
// Tarifs de livraison fixes par zone (non négociables) : le driver n'a plus de
// prix à proposer. « Accepter la demande » passe directement en "confirmee"
// (PATCH /delivery-requests/{id}/status { status: "confirmee" }).
const showRefuseForm = ref(false)
const refuseComment = ref('')

async function refuseMission() {
  const comment = refuseComment.value.trim()
  await changeStatus(STATUS.REFUSEE, comment ? { comment } : {})
  if (!actionError.value) {
    showRefuseForm.value = false
  }
}

// ---- Total à encaisser ----
// Le montant est optionnel côté client : s'il est absent on l'indique plutôt
// que d'afficher un prix nul. Bonus : part du livreur par zone, affichée quand
// l'API fournit delivery_zone.fixed_price (réservé à la ressource pour l'instant).
const collectAmount = computed(() =>
  request.value.amount_to_collect ?? request.value.proposed_price ?? null,
)
const zonePrice = computed(() => request.value.delivery_zone?.fixed_price ?? null)
const zoneName = computed(() =>
  request.value.delivery_zone?.name
    || request.value.delivery_zone?.origin_zone
    || request.value.delivery_zone?.origin
    || 'zone',
)

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

// ---- Signaler un échec (avec commentaire) ----
const showFailForm = ref(false)
const failComment = ref('')

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

// ---- Lightbox (agrandir une preuve) ----
const lightbox = ref('')

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
  actionError.value = ''
  history.value = []
  proofs.value = []
  proofsLoading.value = true
  showRefuseForm.value = false
  showFailForm.value = false
  showIncidentForm.value = false
  incidentSent.value = false
  loadHistory()
  loadProofs()
  start()
}

// Le polling (5 s) reflète les changements côté client (confirm-price, cancel) ;
// l'historique est rechargé à chaque changement de statut détecté.
watch(() => request.value.status, () => {
  loadHistory()
})

watch(id, () => {
  stop()
  setupForRequest()
})

onMounted(() => {
  setupForRequest()
})

onBeforeUnmount(() => {
  stop()
  if (toastTimer) clearTimeout(toastTimer)
})
</script>

<template>
  <div class="driver-layout">
    <DriverSidebar />

    <main class="driver-main">
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
        <!-- Fil d'ariane -->
        <button class="btn btn-ghost back" @click="router.push({ name: 'driver-requests' })">← Demandes</button>

        <!-- En-tête mission -->
        <div class="flex-between wrap mb-8">
          <div class="flex wrap gap-10">
            <h2 class="tracking">{{ request.tracking_number || `Mission #${request.id}` }}</h2>
            <StatusBadge :status="request.status" />
          </div>
          <span class="faint small">créé le {{ formatDateTime(request.created_at) }}</span>
        </div>
        <p v-if="request.pickup_address || request.delivery_address" class="route small muted mb-16">
          <span class="route-dot"></span>
          <b>{{ request.pickup_address || '—' }}</b>
          <span class="arrow">→</span>
          <b>{{ request.delivery_address || '—' }}</b>
        </p>

        <!-- Erreur d'action -->
        <div v-if="actionError" class="card action-error mb-16">
          <div class="flex-between wrap">
            <div>
              <b class="small">Action impossible</b>
              <p class="small mt-8">{{ actionError }}</p>
              <p v-if="request.status === STATUS.CONFIRMEE && !hasPickupPhoto" class="small faint mt-8">
                La photo de récupération est obligatoire pour valider la récupération du colis. Déposez-la dans la carte ci-dessous.
              </p>
            </div>
            <button class="btn btn-ghost" @click="actionError = ''">Fermer</button>
          </div>
        </div>

        <div class="grid-mission">
          <!-- Colonne principale -->
          <div class="flex-col col">
            <!-- Total à encaisser -->
            <section class="total">
              <span class="total-label">Total à encaisser</span>
              <div class="amount">
                {{ collectAmount != null ? formatPrice(collectAmount) : 'À définir à la remise' }}
              </div>
              <span v-if="zonePrice != null" class="total-sub flex">
                <AppIcon name="map" :size="16" /> Ta part ({{ zoneName }}) : {{ formatPrice(zonePrice) }}
              </span>
              <span v-else class="total-sub flex"><AppIcon name="cash" :size="16" /> Espèces à la livraison</span>
            </section>

            <!-- Détails de la demande -->
            <section class="card">
              <h3 class="mb-16">Détails de la demande</h3>
              <dl class="info">
                <div class="row"><dt>Destinataire</dt><dd>{{ request.recipient_name || '—' }}</dd></div>
                <div class="row"><dt>Téléphone</dt><dd>{{ request.recipient_phone || '—' }}</dd></div>
                <div class="row"><dt>Retrait</dt><dd>{{ request.pickup_address || '—' }}</dd></div>
                <div class="row"><dt>Livraison</dt><dd>{{ request.delivery_address || '—' }}</dd></div>
                <div class="row"><dt>Colis</dt><dd>{{ request.package_description || '—' }}</dd></div>
                <div class="row"><dt>Créneau</dt><dd>{{ formatDateTime(request.scheduled_at) }}</dd></div>
              </dl>
            </section>

            <!-- Carte action selon la machine à états -->
            <section class="card">
              <!-- en_attente : accepter (tarif fixe par zone) ou refuser -->
              <div v-if="request.status === STATUS.EN_ATTENTE">
                <h3 class="mb-8">Accepter la demande</h3>
                <p class="small muted mb-16">
                  Les frais de livraison sont fixés par zone — ils ne sont pas négociables.
                  En acceptant, la demande passe directement en « Confirmée ».
                </p>

                <div v-if="!showRefuseForm" class="flex wrap">
                  <button class="btn btn-primary" :disabled="actionLoading" @click="changeStatus(STATUS.CONFIRMEE)">
                    {{ actionLoading ? '…' : 'Accepter la demande' }}
                  </button>
                  <button class="btn btn-danger" :disabled="actionLoading" @click="showRefuseForm = !showRefuseForm">
                    Refuser
                  </button>
                </div>

                <div v-if="showRefuseForm" class="mt-16 refuse-box">
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

              <!-- prix_propose (demande antérieure — compat legacy : le client peut
                   encore confirmer de son côté) : attente de la décision du client -->
              <div v-else-if="request.status === STATUS.PRIX_PROPOSE">
                <h3 class="mb-8">Prix proposé</h3>
                <div class="wait-box">
                  <p class="small muted">Votre proposition :</p>
                  <p class="price-big">{{ formatPrice(request.proposed_price) }}</p>
                  <p class="small muted">En attente de la décision du client…</p>
                  <p class="faint small mt-8">La page se met à jour automatiquement dès que le client confirme ou annule.</p>
                </div>
                <button class="btn btn-danger mt-16" :disabled="actionLoading" @click="showRefuseForm = !showRefuseForm">
                  Refuser
                </button>
                <div v-if="showRefuseForm" class="mt-16 refuse-box">
                  <label class="field">
                    <span>Motif du refus (optionnel)</span>
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

              <!-- confirmee : photo de récupération obligatoire -->
              <div v-else-if="request.status === STATUS.CONFIRMEE">
                <h3 class="mb-8 flex"><AppIcon name="camera" :size="18" /> Photo de récupération (obligatoire)</h3>
                <div class="pickup-box" :class="{ ok: hasPickupPhoto }">
                  <div class="flex-between wrap">
                    <p class="small muted">
                      {{ hasPickupPhoto
                        ? 'Photo enregistrée — vous pouvez récupérer le colis.'
                        : 'Prenez une photo du colis avant de le récupérer. Sans photo, la transition est bloquée.' }}
                    </p>
                    <span v-if="hasPickupPhoto" class="badge badge-green"><AppIcon name="check" :size="13" /> Photo OK</span>
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
                  {{ actionLoading ? '…' : 'Colis récupéré' }}
                </button>
                <p v-if="!hasPickupPhoto" class="faint small mt-8">Le bouton s'active dès que la photo de récupération est enregistrée.</p>
              </div>

              <!-- colis_recupere : départ en livraison -->
              <div v-else-if="request.status === STATUS.COLIS_RECUPERE">
                <h3 class="mb-8">Colis récupéré</h3>
                <p class="small muted">Confirmez votre départ pour lancer la livraison.</p>
                <button class="btn btn-primary mt-16" :disabled="actionLoading" @click="changeStatus(STATUS.EN_LIVRAISON)">
                  {{ actionLoading ? '…' : 'Départ en livraison' }}
                </button>
              </div>

              <!-- en_livraison : le livreur confirme son arrivée puis remet -->
              <div v-else-if="request.status === STATUS.EN_LIVRAISON">
                <h3 class="mb-8">En livraison</h3>
                <p class="small muted">
                  Une fois sur place, confirmez votre arrivée : le statut passera à « Livreur arrivé ».
                  La remise se clôturera ensuite avec le bouton « Le colis est livré ».
                </p>

                <button class="btn btn-primary mt-16" :disabled="actionLoading" @click="confirmArrival()">
                  {{ actionLoading ? '…' : 'Le livreur est arrivé' }}
                </button>

                <div class="divider"></div>

                <div v-if="!showFailForm">
                  <button class="btn btn-danger" @click="showFailForm = true"><AppIcon name="warning" :size="16" /> Signaler un échec</button>
                  <p class="faint small mt-8">L'échec met fin à la mission (une preuve reste facultative).</p>
                </div>
                <div v-else class="refuse-box">
                  <label class="field">
                    <span>Motif de l'échec (optionnel)</span>
                    <textarea v-model="failComment" class="input" placeholder="Ex : destinataire injoignable"></textarea>
                  </label>
                  <div class="flex">
                    <button
                      class="btn btn-danger"
                      :disabled="actionLoading"
                      @click="changeStatus(STATUS.ECHEC, failComment.trim() ? { comment: failComment.trim() } : {})"
                    >
                      {{ actionLoading ? '…' : "Confirmer l'échec" }}
                    </button>
                    <button class="btn btn-ghost" :disabled="actionLoading" @click="showFailForm = false">Annuler</button>
                  </div>
                </div>
              </div>

              <!-- livreur_arrive : remise de la commande -->
              <div v-else-if="request.status === STATUS.LIVREUR_ARRIVE">
                <h3 class="mb-8 flex"><AppIcon name="home" :size="18" /> Livreur arrivé</h3>
                <p class="small muted">Vous avez confirmé votre arrivée. Remettez la commande au client, puis cliquez sur « Le colis est livré » : le statut passera à « Livrée ».</p>

                <button
                  class="btn btn-primary mt-16"
                  :disabled="actionLoading"
                  @click="confirmHandover()"
                >
                  {{ actionLoading ? '…' : 'Le colis est livré' }}
                </button>

                <div class="divider"></div>

                <div v-if="!showFailForm">
                  <button class="btn btn-danger" @click="showFailForm = true"><AppIcon name="warning" :size="16" /> Signaler un échec</button>
                  <p class="faint small mt-8">L'échec met fin à la mission (une preuve reste facultative).</p>
                </div>
                <div v-else class="refuse-box">
                  <label class="field">
                    <span>Motif de l'échec (optionnel)</span>
                    <textarea v-model="failComment" class="input" placeholder="Ex : destinataire injoignable"></textarea>
                  </label>
                  <div class="flex">
                    <button
                      class="btn btn-danger"
                      :disabled="actionLoading"
                      @click="changeStatus(STATUS.ECHEC, failComment.trim() ? { comment: failComment.trim() } : {})"
                    >
                      {{ actionLoading ? '…' : "Confirmer l'échec" }}
                    </button>
                    <button class="btn btn-ghost" :disabled="actionLoading" @click="showFailForm = false">Annuler</button>
                  </div>
                </div>
              </div>

              <!-- Statuts terminaux -->
              <div v-else-if="isTerminal">
                <div class="wait-box">
                  <p class="bold">
                    {{ request.status === STATUS.LIVREE ? 'Mission livrée avec succès' : '' }}
                    {{ request.status === STATUS.REFUSEE ? 'Vous avez refusé cette demande' : '' }}
                    {{ request.status === STATUS.ECHEC ? 'Mission en échec' : '' }}
                    {{ request.status === STATUS.ANNULEE ? 'Demande annulée par le client' : '' }}
                  </p>
                  <p v-if="request.status === STATUS.LIVREE && request.delivered_at" class="small muted mt-8">
                    Livrée le {{ formatDateTime(request.delivered_at) }}.
                  </p>
                </div>
              </div>
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
                  <img
                    v-if="p.file_url"
                    :src="p.file_url"
                    :alt="PROOF_LABELS[p.proof_type] || p.proof_type"
                    loading="lazy"
                    class="zoomable"
                    title="Agrandir"
                    @click="lightbox = p.file_url"
                  />
                  <div class="proof-meta flex-between">
                    <span class="badge" :class="p.proof_type === 'pickup_photo' ? 'badge-green' : 'badge-blue'">
                      {{ PROOF_LABELS[p.proof_type] || p.proof_type }}
                    </span>
                    <button class="btn btn-ghost del" title="Supprimer la preuve" @click="deleteProof(p)" aria-label="Supprimer la preuve"><AppIcon name="close" :size="16" /></button>
                  </div>
                  <p v-if="p.receiver_name" class="faint small">Reçu par : {{ p.receiver_name }}</p>
                  <p class="faint small">{{ formatDateTime(p.created_at) }}</p>
                </div>
              </div>
              <p v-else class="muted small">Aucune preuve pour le moment.</p>

              <!-- Upload preuve de livraison (remise) — requis RG06 avant "livree" -->
              <template v-if="request.status === STATUS.EN_LIVRAISON || request.status === STATUS.LIVREUR_ARRIVE">
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
              </template>
            </section>

            <!-- Incident -->
            <section class="card">
              <div class="flex-between wrap">
                <h3>Incident</h3>
                <button v-if="!showIncidentForm" class="btn btn-outline" @click="showIncidentForm = true">Signaler un incident</button>
              </div>
              <p v-if="incidentSent" class="small badge badge-yellow mt-8"><AppIcon name="check" :size="13" /> Incident signalé — l'équipe a été notifiée.</p>

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

          <!-- Colonne secondaire -->
          <div class="flex-col col">
            <!-- Historique des statuts -->
            <section class="card">
              <h3 class="mb-16">Historique des statuts</h3>
              <StatusTimeline variant="history" :history="history" :current="request.status" />
            </section>

            <!-- Chat client -->
            <section class="card">
              <h3 class="mb-16">Chat client</h3>
              <ChatPanel :delivery-request-id="request.id" compact />
            </section>
          </div>
        </div>
      </template>

      <ToastMessage :message="toast" @close="toast = ''" />
      <ImageLightbox v-if="lightbox" :src="lightbox" alt="Preuve" @close="lightbox = ''" />
    </main>
  </div>
</template>

<style scoped>
.back {
  margin: -0.375rem 0 0.375rem -0.5rem;
  color: var(--fg-2);
}
.tracking {
  font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
  font-size: 1.45rem;
}
.gap-10 { gap: 0.625rem; }
.route {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.route-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  background: var(--green);
  flex-shrink: 0;
}
.route .arrow {
  color: var(--fg-3);
  font-weight: 800;
}

/* 1.3fr / 1fr et gouttière de 18px : proportions de l'écran « mission » du
   prototype. `minmax(0, …)` empêche une longue adresse d'imposer sa largeur
   intrinsèque à une piste et de faire défiler la page. */
.grid-mission {
  display: grid;
  grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr);
  gap: 1.125rem;
  align-items: start;
}
.col { gap: 1rem; min-width: 0; }

.skel-hdr { height: 5.625rem; }
.skel-card { height: 16.25rem; }

/* Total à encaisser : bannière verte (prototype) */
.total {
  background: var(--green);
  border-radius: 1rem;
  padding: 1.125rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  color: var(--green-ink);
}
.total-label {
  font-size: 0.7813rem;
  font-weight: 700;
  color: var(--green-ink);
  opacity: 0.75;
}
.total .amount {
  color: var(--green-ink);
  font-size: 2rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.total-sub {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--green-ink);
  opacity: 0.85;
}

.action-error {
  border: 0.0625rem solid rgba(255, 106, 106, 0.35);
  background: rgba(255, 106, 106, 0.08);
}

.wait-box {
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 0.75rem;
  padding: 0.875rem 1rem;
}
.price-big {
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--green);
  margin: 0.25rem 0;
}

.refuse-box {
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 0.75rem;
  padding: 0.875rem 1rem;
}

.pickup-box {
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 0.75rem;
  padding: 0.875rem 1rem;
}
.pickup-box.ok {
  border-color: rgba(34, 197, 111, 0.4);
}

.preview img {
  max-width: 100%;
  max-height: 13.75rem;
  border-radius: 0.625rem;
  border: 0.0625rem solid var(--border);
  margin-top: 0.375rem;
}

.amber { color: var(--amber); }
.block { display: block; }

.info {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.info .row {
  display: grid;
  grid-template-columns: 8.125rem 1fr;
  gap: 0.75rem;
  font-size: 0.85rem;
  padding: 0.3125rem 0;
  border-bottom: 0.0625rem dashed var(--border);
}
.info .row:last-child { border-bottom: none; }
.info dt {
  color: var(--fg);
  font-weight: 700;
}
.info dd { color: var(--fg-2); overflow-wrap: anywhere; }

.proof-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(8.75rem, 1fr));
  gap: 0.75rem;
}
.proof {
  border: 0.0625rem solid var(--border);
  border-radius: 0.625rem;
  padding: 0.5rem;
  background: var(--surface-2);
}
.proof img {
  width: 100%;
  height: 6.875rem;
  object-fit: cover;
  border-radius: 0.375rem;
  border: 0.0625rem solid var(--border);
}
.zoomable { cursor: zoom-in; }
.proof-meta { margin: 0.5rem 0 0.25rem; }
.del {
  padding: 0.125rem 0.5rem;
  color: var(--red);
}

@media (max-width: 1000px) {
  /* minmax(0, …) sinon la piste ne descend pas sous la largeur intrinsèque
     de son contenu et fait défiler la page horizontalement. */
  .grid-mission { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 420px) {
  /* Une colonne d'étiquettes de 8.125rem ne laisse plus assez de place à la
     valeur : on les empile. */
  .info .row {
    grid-template-columns: 1fr;
    gap: 0.125rem;
  }
}
</style>
