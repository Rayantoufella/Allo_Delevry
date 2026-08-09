<script setup>
import { onMounted, ref } from 'vue'
import api, { apiError } from '../api/axios'
import AppIcon from '../components/AppIcon.vue'
import DriverSidebar from '../components/driver/DriverSidebar.vue'
import ToastMessage from '../components/driver/ToastMessage.vue'

/**
 * Zones & tarifs — page dédiée de l'espace livreur, fidèle à l'écran 11 du
 * prototype : une carte par zone avec nom (input inline), compteur de
 * livraisons et tarif fixe, plus un bouton « + Ajouter une zone ».
 * Le modèle backend enregistre un couple origin/destination ; le prototype ne
 * connaît qu'un nom par zone, on écrit donc la même valeur dans les deux
 * champs (concession documentée au store).
 */

const zones = ref([])
const zonesLoading = ref(true)

// Buffers d'édition locaux : la sauvegarde se fait au blur (nom) ou blur/Enter (prix).
const names = ref({})
const prices = ref({})

const addingNew = ref(false)
const newSaving = ref(false)
const newErrors = ref({})
const newForm = ref({ name: '', fixed_price: '', is_active: true })

const toast = ref('')
let toastTimer = null

function showToast(message) {
  toast.value = message
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 3500)
}

const DOT_COLORS = ['var(--green)', 'var(--blue)', 'var(--violet)', 'var(--amber)', 'var(--red)']

function dotColor(index) {
  return DOT_COLORS[index % DOT_COLORS.length]
}

function zoneCountLabel(zone) {
  const n = Number(zone.deliveries_count ?? 0)
  return n === 1 ? '1 livraison' : `${n} livraisons`
}

function zoneLabel(zone) {
  return zone.destination_zone || zone.origin_zone || ''
}

async function loadZones() {
  zonesLoading.value = true
  try {
    const res = await api.get('/delivery-zones')
    zones.value = res.data.data ?? res.data ?? []
    const nextNames = {}
    const nextPrices = {}
    for (const z of zones.value) {
      nextNames[z.id] = zoneLabel(z)
      nextPrices[z.id] = z.fixed_price ?? ''
    }
    names.value = nextNames
    prices.value = nextPrices
  } catch (err) {
    showToast(apiError(err, 'Impossible de charger les zones.'))
  } finally {
    zonesLoading.value = false
  }
}

/** Renomme la zone : le prototype n'a qu'un nom, on le répercute sur origin et
 *  destination pour rester cohérent avec le modèle backend. */
async function saveName(zone) {
  const value = String(names.value[zone.id] ?? '').trim()
  if (!value) {
    names.value[zone.id] = zoneLabel(zone)
    showToast('Le nom de la zone ne peut pas être vide.')
    return
  }
  if (value === zoneLabel(zone)) return
  try {
    await api.put(`/delivery-zones/${zone.id}`, {
      origin_zone: value,
      destination_zone: value,
    })
    zone.destination_zone = value
    zone.origin_zone = value
    showToast('Zone renommée')
  } catch (err) {
    names.value[zone.id] = zoneLabel(zone)
    showToast(apiError(err, 'Impossible de renommer la zone.'))
  }
}

async function savePrice(zone) {
  const raw = String(prices.value[zone.id] ?? '').trim()
  const num = raw === '' ? null : Number(raw)
  const current = zone.fixed_price === null || zone.fixed_price === ''
    ? null
    : Number(zone.fixed_price)
  if (num === current) return
  try {
    await api.put(`/delivery-zones/${zone.id}`, { fixed_price: num })
    zone.fixed_price = num
    showToast('Tarif mis à jour')
  } catch (err) {
    prices.value[zone.id] = zone.fixed_price ?? ''
    showToast(apiError(err, 'Impossible d’enregistrer le tarif.'))
  }
}

async function removeZone(zone) {
  const label = zoneLabel(zone)
  if (!window.confirm(`Supprimer la zone « ${label} » ?`)) return
  try {
    await api.delete(`/delivery-zones/${zone.id}`)
    showToast('Zone supprimée')
    await loadZones()
  } catch (err) {
    showToast(apiError(err, 'Impossible de supprimer la zone.'))
  }
}

function startAdd() {
  newForm.value = { name: '', fixed_price: '', is_active: true }
  newErrors.value = {}
  addingNew.value = true
}

function cancelAdd() {
  addingNew.value = false
  newErrors.value = {}
}

async function addZone() {
  const name = newForm.value.name.trim()
  newErrors.value = {}
  if (!name) {
    newErrors.value.name = 'Le nom de la zone est requis.'
    return
  }
  newSaving.value = true
  try {
    const payload = {
      origin_zone: name,
      destination_zone: name,
      fixed_price: newForm.value.fixed_price === '' ? null : Number(newForm.value.fixed_price),
      is_active: !!newForm.value.is_active,
    }
    await api.post('/delivery-zones', payload)
    addingNew.value = false
    showToast('Zone ajoutée')
    await loadZones()
  } catch (err) {
    newErrors.value = err?.response?.data?.errors || {}
    if (!Object.keys(newErrors.value).length) {
      showToast(apiError(err, 'Impossible d’ajouter la zone.'))
    }
  } finally {
    newSaving.value = false
  }
}

onMounted(() => {
  loadZones()
})
</script>

<template>
  <div class="driver-layout">
    <DriverSidebar />

    <main class="driver-main">
      <!-- En-tête du prototype : titre 28px 800 + sous-titre RG07 -->
      <div class="zones-head">
        <h2>Zones &amp; tarifs</h2>
        <p class="zones-sub">
          Un prix fixe par zone. Le tarif enregistré dans une mission ne change pas si tu modifies la zone plus tard (RG07).
        </p>
      </div>

      <div v-if="zonesLoading" class="flex" style="gap: 0.75rem; margin-top: 1rem">
        <div class="spinner"></div>
        <span class="small muted">Chargement des zones…</span>
      </div>

      <div v-else class="zones-grid">
        <!-- Une carte par zone : nom inline + compteur + prix inline + suppression -->
        <div v-for="(zone, index) in zones" :key="zone.id" class="zone-card">
          <div class="zone-top">
            <span class="zone-dot" :style="{ background: dotColor(index) }"></span>
            <input
              class="zone-name-input"
              :value="names[zone.id]"
              placeholder="Nom de la zone"
              @input="names[zone.id] = $event.target.value"
              @blur="saveName(zone)"
              @keyup.enter="$event.target.blur()"
            />
            <span class="zone-count">{{ zoneCountLabel(zone) }}</span>
          </div>
          <div class="zone-bottom">
            <input
              class="zone-price-input"
              :value="prices[zone.id]"
              type="number"
              min="0"
              step="0.01"
              @input="prices[zone.id] = $event.target.value"
              @blur="savePrice(zone)"
              @keyup.enter="$event.target.blur()"
            />
            <span class="zone-currency">DH</span>
            <button class="zone-delete" title="Supprimer la zone" @click="removeZone(zone)">
              <AppIcon name="close" :size="16" label="Supprimer la zone" />
            </button>
          </div>
        </div>

        <!-- Nouvelle zone : ligne vide à compléter avant POST -->
        <div v-if="addingNew" class="zone-card">
          <div class="zone-top">
            <span class="zone-dot" :style="{ background: dotColor(zones.length) }"></span>
            <input
              v-model="newForm.name"
              class="zone-name-input"
              placeholder="Nom de la zone"
              @keyup.enter="addZone"
            />
          </div>
          <div class="zone-bottom">
            <input
              v-model="newForm.fixed_price"
              class="zone-price-input"
              type="number"
              min="0"
              step="0.01"
              placeholder="0"
              @keyup.enter="addZone"
            />
            <span class="zone-currency">DH</span>
            <label class="zone-active">
              <input v-model="newForm.is_active" type="checkbox" />
              Active
            </label>
          </div>
          <p v-if="newErrors.name || newErrors.destination_zone" class="zone-error">
            {{ newErrors.name?.[0] || newErrors.destination_zone?.[0] }}
          </p>
          <div class="zone-new-actions">
            <button class="zone-save" :disabled="newSaving" @click="addZone">
              {{ newSaving ? '…' : 'Ajouter' }}
            </button>
            <button class="zone-cancel" :disabled="newSaving" @click="cancelAdd">Annuler</button>
          </div>
        </div>

        <!-- Bouton + Ajouter une zone (carte pointillée du prototype) -->
        <button v-else class="zone-add" @click="startAdd">
          <span class="zone-add-plus">+</span>
          <span>Ajouter une zone</span>
        </button>
      </div>

      <p v-if="!zonesLoading && !zones.length && !addingNew" class="muted small mt-16">
        Aucune zone pour l’instant. Ajoute ta première zone pour fixer tes tarifs.
      </p>

      <ToastMessage :message="toast" @close="toast = ''" />
    </main>
  </div>
</template>

<style scoped>
/* En-tête (valeurs de l'écran 11 du prototype) */
.zones-head h2 {
  margin: 0 0 4px;
  font-size: 1.75rem;
  font-weight: 800;
  letter-spacing: -0.025em;
}

.zones-sub {
  color: var(--fg-2);
  font-weight: 600;
  margin-bottom: 22px;
}

/* Grille 2 colonnes du prototype */
.zones-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

/* Carte zone : padding 20, radius 16, surface */
.zone-card {
  padding: 1.25rem;
  border-radius: 1rem;
  background: var(--surface);
  border: 0.0625rem solid var(--border);
}

/* Rangée du haut : pastille + nom inline + compteur */
.zone-top {
  display: flex;
  align-items: center;
  gap: 0.625rem;
}

.zone-dot {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 0.25rem;
  flex: 0 0 auto;
}

/* Input nom : transparent, souligné en pointillés */
.zone-name-input {
  flex: 1 1 0%;
  min-width: 0;
  font-weight: 800;
  font-size: 1rem;
  background: transparent;
  border: none;
  border-bottom: 0.0625rem dashed var(--border-2);
  color: var(--fg);
  padding: 2px 0;
  font-family: inherit;
}

.zone-name-input:focus {
  outline: none;
  border-bottom-color: var(--green);
}

.zone-count {
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--fg-2);
  white-space: nowrap;
  margin-left: 0.5rem;
}

/* Rangée du bas : prix inline + DH + suppression */
.zone-bottom {
  display: flex;
  align-items: baseline;
  gap: 0.375rem;
  margin-top: 0.875rem;
}

.zone-price-input {
  width: 5rem;
  padding: 0.625rem;
  border-radius: 0.625rem;
  border: 0.0625rem solid var(--border);
  background: var(--surface-2);
  color: var(--fg);
  font-size: 1.25rem;
  font-weight: 800;
  text-align: center;
  font-family: inherit;
}

.zone-price-input:focus {
  outline: none;
  border-color: var(--green);
}

.zone-currency {
  font-weight: 800;
  color: var(--fg-2);
}

.zone-delete {
  margin-left: auto;
  width: 2rem;
  height: 2rem;
  border-radius: 0.5625rem;
  border: 0.0625rem solid var(--border);
  background: var(--surface-2);
  color: var(--red);
  font-weight: 800;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Carte d'ajout : bordure pointillée du prototype */
.zone-add {
  padding: 1.25rem;
  border-radius: 1rem;
  border: 0.0938rem dashed var(--border-2);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 0.5rem;
  color: var(--fg-2);
  cursor: pointer;
  min-height: 7.5rem;
  background: transparent;
  font-family: inherit;
  transition: color 0.15s, border-color 0.15s;
}

.zone-add:hover {
  color: var(--fg);
  border-color: var(--green);
}

.zone-add-plus {
  font-size: 1.625rem;
  line-height: 1;
}

.zone-add span:last-child {
  font-weight: 700;
  font-size: 0.875rem;
}

/* Formulaire d'ajout */
.zone-active {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--fg-2);
  margin-left: 0.5rem;
  cursor: pointer;
}

.zone-active input {
  accent-color: var(--green);
}

.zone-error {
  color: var(--red);
  font-size: 0.78rem;
  margin-top: 0.5rem;
}

.zone-new-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.875rem;
}

.zone-save {
  padding: 0.5rem 1rem;
  border-radius: 0.625rem;
  border: none;
  background: var(--green);
  color: var(--green-ink);
  font-weight: 800;
  font-size: 0.8125rem;
  cursor: pointer;
  font-family: inherit;
}

.zone-save:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.zone-cancel {
  padding: 0.5rem 1rem;
  border-radius: 0.625rem;
  border: 0.0625rem solid var(--border);
  background: var(--surface-2);
  color: var(--fg);
  font-weight: 700;
  font-size: 0.8125rem;
  cursor: pointer;
  font-family: inherit;
}

@media (max-width: 640px) {
  .zones-grid {
    grid-template-columns: 1fr;
  }
}
</style>
