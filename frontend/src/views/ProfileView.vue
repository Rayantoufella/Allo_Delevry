<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api/axios'
import { apiError } from '../api/axios'
import { formatPrice } from '../lib/statuses'
import DriverSidebar from '../components/driver/DriverSidebar.vue'
import ToastMessage from '../components/driver/ToastMessage.vue'

/**
 * Profil & marque livreur — profil public (CRUD driver-profiles),
 * catalogue des services, page publique (lien + QR) et zones & tarifs.
 * Chaque sous-formulaire gère ses erreurs 422 champ par champ.
 */
const router = useRouter()
const route = useRoute()

const toast = ref('')
let toastTimer = null

function showToast(message) {
  toast.value = message
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 3500)
}

function slugify(text) {
  return String(text || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

// ============================================================
// Profil public
// ============================================================
const profiles = ref([])
const profileLoading = ref(true)
const profileSaving = ref(false)
const profileErrors = ref({})

const profileForm = ref({
  brand_name: '',
  slug: '',
  logo_path: '',
  city: '',
  is_available: true,
})

const currentProfile = computed(() => profiles.value[0] || null)

async function loadProfiles() {
  profileLoading.value = true
  try {
    const res = await api.get('/driver-profiles')
    profiles.value = res.data.data ?? res.data ?? []
    const p = currentProfile.value
    if (p) {
      profileForm.value = {
        brand_name: p.brand_name || '',
        slug: p.slug || '',
        logo_path: p.logo_path || '',
        city: p.city || '',
        is_available: p.is_available !== false,
      }
    }
  } catch {
    profiles.value = []
  } finally {
    profileLoading.value = false
  }
}

function onBrandName() {
  // Pré-remplissage du slug uniquement lors de la création.
  if (!currentProfile.value && !profileForm.value.slug.trim()) {
    profileForm.value.slug = slugify(profileForm.value.brand_name)
  }
}

const profileInitial = computed(() =>
  (profileForm.value.brand_name || 'M').trim().charAt(0).toUpperCase(),
)

const prettyLink = computed(() =>
  currentProfile.value ? `allo.delivery/r/${currentProfile.value.slug}` : '',
)

const localPublicUrl = computed(() => {
  if (!currentProfile.value) return ''
  const base = window.location.origin
  return `${base}/drivers/${currentProfile.value.slug}`
})

const qrSrc = computed(() =>
  currentProfile.value ? `/api/drivers/${currentProfile.value.slug}/qr` : '',
)

async function saveProfile() {
  profileErrors.value = {}
  profileSaving.value = true
  try {
    const payload = {
      brand_name: profileForm.value.brand_name.trim(),
      slug: profileForm.value.slug.trim().toLowerCase(),
      logo_path: profileForm.value.logo_path.trim() || null,
      city: profileForm.value.city.trim(),
      is_available: !!profileForm.value.is_available,
    }
    if (currentProfile.value) {
      await api.put(`/driver-profiles/${currentProfile.value.id}`, payload)
      showToast('Profil public mis à jour')
    } else {
      await api.post('/driver-profiles', payload)
      showToast('Profil public créé — votre page est en ligne !')
    }
    await loadProfiles()
  } catch (err) {
    profileErrors.value = err?.response?.data?.errors || {}
    if (!Object.keys(profileErrors.value).length) {
      showToast(apiError(err))
    }
  } finally {
    profileSaving.value = false
  }
}

async function copyLink() {
  if (!localPublicUrl.value) return
  try {
    await navigator.clipboard.writeText(localPublicUrl.value)
    showToast('Lien copié dans le presse-papiers')
  } catch {
    showToast(localPublicUrl.value)
  }
}

function goPublicPage() {
  if (currentProfile.value) {
    router.push({ name: 'driver-public', params: { slug: currentProfile.value.slug } })
  }
}

// ============================================================
// Services
// ============================================================
const services = ref([])
const servicesLoading = ref(true)
const serviceErrors = ref({})
const serviceSaving = ref(false)
const editingService = ref(null)

const serviceForm = ref({
  name: '',
  description: '',
  base_price: '',
  is_active: true,
})

async function loadServices() {
  servicesLoading.value = true
  try {
    const res = await api.get('/services')
    services.value = res.data.data ?? res.data ?? []
  } catch {
    services.value = []
  } finally {
    servicesLoading.value = false
  }
}

function startEditService(s) {
  editingService.value = s
  serviceForm.value = {
    name: s.name || '',
    description: s.description || '',
    base_price: s.base_price ?? '',
    is_active: s.is_active !== false,
  }
  serviceErrors.value = {}
}

function cancelEditService() {
  editingService.value = null
  serviceForm.value = { name: '', description: '', base_price: '', is_active: true }
  serviceErrors.value = {}
}

async function saveService() {
  serviceErrors.value = {}
  serviceSaving.value = true
  try {
    const payload = {
      name: serviceForm.value.name.trim(),
      description: serviceForm.value.description.trim(),
      base_price: serviceForm.value.base_price === '' ? null : Number(serviceForm.value.base_price),
      is_active: !!serviceForm.value.is_active,
    }
    if (editingService.value) {
      await api.put(`/services/${editingService.value.id}`, payload)
      showToast('Service mis à jour')
    } else {
      await api.post('/services', payload)
      showToast('Service ajouté')
    }
    cancelEditService()
    await loadServices()
  } catch (err) {
    serviceErrors.value = err?.response?.data?.errors || {}
    if (!Object.keys(serviceErrors.value).length) {
      showToast(apiError(err))
    }
  } finally {
    serviceSaving.value = false
  }
}

async function toggleService(s) {
  try {
    await api.put(`/services/${s.id}`, { is_active: !s.is_active })
    await loadServices()
  } catch (err) {
    showToast(apiError(err))
  }
}

async function deleteService(s) {
  if (!window.confirm(`Supprimer le service « ${s.name} » ?`)) return
  try {
    await api.delete(`/services/${s.id}`)
    showToast('Service supprimé')
    await loadServices()
  } catch (err) {
    showToast(apiError(err))
  }
}

// ============================================================
// Zones & tarifs (section #zones, accessible via sidebar)
// ============================================================
const zones = ref([])
const zonesLoading = ref(true)
const zoneErrors = ref({})
const zoneSaving = ref(false)
const editingZone = ref(null)
const zonePrices = ref({})

const zoneForm = ref({
  origin_zone: '',
  destination_zone: '',
  fixed_price: '',
  is_active: true,
})

async function loadZones() {
  zonesLoading.value = true
  try {
    const res = await api.get('/delivery-zones')
    zones.value = res.data.data ?? res.data ?? []
    const prices = {}
    for (const z of zones.value) {
      prices[z.id] = z.fixed_price ?? ''
    }
    zonePrices.value = prices
  } catch {
    zones.value = []
  } finally {
    zonesLoading.value = false
  }
}

function startEditZone(z) {
  editingZone.value = z
  zoneForm.value = {
    origin_zone: z.origin_zone || '',
    destination_zone: z.destination_zone || '',
    fixed_price: z.fixed_price ?? '',
    is_active: z.is_active !== false,
  }
  zoneErrors.value = {}
}

function cancelEditZone() {
  editingZone.value = null
  zoneForm.value = { origin_zone: '', destination_zone: '', fixed_price: '', is_active: true }
  zoneErrors.value = {}
}

async function saveZone() {
  zoneErrors.value = {}
  zoneSaving.value = true
  try {
    const payload = {
      origin_zone: zoneForm.value.origin_zone.trim(),
      destination_zone: zoneForm.value.destination_zone.trim(),
      fixed_price: zoneForm.value.fixed_price === '' ? null : Number(zoneForm.value.fixed_price),
      is_active: !!zoneForm.value.is_active,
    }
    if (editingZone.value) {
      await api.put(`/delivery-zones/${editingZone.value.id}`, payload)
      showToast('Zone mise à jour')
    } else {
      await api.post('/delivery-zones', payload)
      showToast('Zone ajoutée')
    }
    cancelEditZone()
    await loadZones()
  } catch (err) {
    zoneErrors.value = err?.response?.data?.errors || {}
    if (!Object.keys(zoneErrors.value).length) {
      showToast(apiError(err))
    }
  } finally {
    zoneSaving.value = false
  }
}

async function updateZonePrice(z) {
  const value = zonePrices.value[z.id]
  const num = value === '' || value === null ? null : Number(value)
  if (num === Number(z.fixed_price ?? null) && num !== null) return
  try {
    await api.put(`/delivery-zones/${z.id}`, { fixed_price: num })
    await loadZones()
    showToast('Tarif de la zone mis à jour')
  } catch (err) {
    zonePrices.value[z.id] = z.fixed_price ?? ''
    showToast(apiError(err))
  }
}

async function toggleZone(z) {
  try {
    await api.patch(`/delivery-zones/${z.id}/toggle-active`)
    await loadZones()
  } catch (err) {
    showToast(apiError(err))
  }
}

async function deleteZone(z) {
  if (!window.confirm(`Supprimer la zone « ${z.origin_zone} → ${z.destination_zone} » ?`)) return
  try {
    await api.delete(`/delivery-zones/${z.id}`)
    showToast('Zone supprimée')
    await loadZones()
  } catch (err) {
    showToast(apiError(err))
  }
}

// Scroll vers la section #zones demandée par la sidebar
async function scrollToZones() {
  await nextTick()
  const el = document.getElementById('zones')
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

watch(
  () => route.query.section,
  async (section) => {
    if (section === 'zones') {
      await scrollToZones()
    }
  },
)

onMounted(() => {
  loadProfiles()
  loadServices()
  loadZones()
  if (route.query.section === 'zones') {
    scrollToZones()
  }
})
</script>

<template>
  <div class="driver-layout">
    <DriverSidebar />

    <main class="driver-main">
      <div class="mb-16">
        <h2>Profil &amp; marque</h2>
        <p class="muted small">Ton identité publique, ton lien unique et ton catalogue.</p>
      </div>

      <!-- ===================== Profil public ===================== -->
      <section class="card mb-16">
        <div class="flex-between wrap mb-16">
          <h3>Mon profil public</h3>
          <span v-if="profileLoading" class="spinner"></span>
        </div>

        <div v-if="currentProfile" class="flex-between wrap mb-16 profile-ok">
          <div class="flex wrap">
            <span class="badge badge-green">Profil en ligne</span>
            <span v-if="currentProfile.is_available" class="badge badge-green">Disponible</span>
            <span v-else class="badge badge-yellow">Indisponible</span>
            <span class="badge">{{ currentProfile.slug }}</span>
          </div>
          <div class="flex wrap">
            <button class="btn btn-outline" @click="goPublicPage()">Voir ma page publique ↗</button>
          </div>
        </div>

        <form v-if="!profileLoading" class="form-grid" @submit.prevent="saveProfile()">
          <label class="field avatar-field">
            <span class="avatar-lg">{{ profileInitial }}</span>
          </label>
          <div class="avatar-actions">
            <span class="small faint">Logo (URL)</span>
            <input v-model="profileForm.logo_path" class="input" :class="{ 'input-error': profileErrors.logo_path }" placeholder="https://…/logo.png" />
            <span v-if="profileErrors.logo_path" class="error-msg">{{ profileErrors.logo_path[0] }}</span>
          </div>

          <label class="field">
            <span>Nom de la marque *</span>
            <input v-model="profileForm.brand_name" class="input" :class="{ 'input-error': profileErrors.brand_name }" placeholder="Ex : Rayan Express" @input="onBrandName" />
            <span v-if="profileErrors.brand_name" class="error-msg">{{ profileErrors.brand_name[0] }}</span>
          </label>

          <label class="field">
            <span>Identifiant public (slug) *</span>
            <input v-model="profileForm.slug" class="input" :class="{ 'input-error': profileErrors.slug }" placeholder="ex: rayan-express" />
            <span v-if="profileErrors.slug" class="error-msg">{{ profileErrors.slug[0] }}</span>
            <span class="faint small">Unique — utilisé dans votre page publique : /drivers/votre-marque</span>
          </label>

          <label class="field">
            <span>Ville</span>
            <input v-model="profileForm.city" class="input" :class="{ 'input-error': profileErrors.city }" placeholder="Ex : Agadir" />
            <span v-if="profileErrors.city" class="error-msg">{{ profileErrors.city[0] }}</span>
          </label>

          <label class="field check">
            <input v-model="profileForm.is_available" type="checkbox" />
            <span>Disponible pour accepter de nouvelles missions</span>
          </label>

          <div class="field span-2">
            <button class="btn btn-primary" :disabled="profileSaving" type="submit">
              {{ profileSaving ? '…' : currentProfile ? 'Enregistrer les modifications' : 'Créer mon profil public' }}
            </button>
          </div>
        </form>
      </section>

      <!-- ===================== Ta page publique ===================== -->
      <section v-if="currentProfile" class="card mb-16">
        <h3 class="mb-16">Ta page publique</h3>
        <div class="public-row">
          <div class="public-info">
            <span class="small faint">Lien unique</span>
            <div class="link-line">
              <b>{{ prettyLink }}</b>
            </div>
            <p class="faint small mt-8">
              Lien local actif : <code>{{ localPublicUrl }}</code>
            </p>
            <div class="flex wrap mt-16">
              <button class="btn btn-primary" @click="copyLink()">📋 Copier le lien</button>
              <button class="btn btn-outline" @click="goPublicPage()">👁 Aperçu client</button>
            </div>
          </div>
          <div class="qr-img">
            <img v-if="qrSrc" :src="qrSrc" alt="QR code de la page publique" />
          </div>
        </div>
      </section>

      <!-- ===================== Catalogue des services ===================== -->
      <section class="card mb-16">
        <div class="flex-between wrap mb-16">
          <h3>Catalogue des services</h3>
          <button v-if="!editingService" class="btn btn-outline" @click="startEditService({})">+ Ajouter</button>
        </div>

        <div v-if="servicesLoading" class="skeleton skel-row"></div>

        <div v-else-if="services.length" class="flex-col">
          <div v-for="s in services" :key="s.id" class="row-item">
            <div class="row-main">
              <div class="flex wrap">
                <b>{{ s.name }}</b>
                <span class="badge" :class="s.is_active ? 'badge-green' : 'badge-red'">
                  {{ s.is_active ? 'Actif' : 'Inactif' }}
                </span>
              </div>
              <p v-if="s.description" class="muted small mt-8">{{ s.description }}</p>
              <p v-if="s.base_price" class="small mt-8">💰 Dès {{ formatPrice(s.base_price) }}</p>
            </div>
            <div class="row-actions">
              <button class="btn btn-ghost" @click="toggleService(s)">{{ s.is_active ? 'Désactiver' : 'Activer' }}</button>
              <button class="btn btn-ghost" @click="startEditService(s)">Éditer</button>
              <button class="btn btn-ghost danger" @click="deleteService(s)">Supprimer</button>
            </div>
          </div>
        </div>
        <p v-else class="muted small">Aucun service. Ajoutez vos services (ex : envoi express, livraison de repas…).</p>

        <form v-if="editingService" class="form-grid mt-16 sub-form" @submit.prevent="saveService()">
          <label class="field">
            <span>Nom du service *</span>
            <input v-model="serviceForm.name" class="input" :class="{ 'input-error': serviceErrors.name }" placeholder="Ex : Envoi express" />
            <span v-if="serviceErrors.name" class="error-msg">{{ serviceErrors.name[0] }}</span>
          </label>
          <label class="field">
            <span>Prix de base (DH)</span>
            <input v-model="serviceForm.base_price" class="input" :class="{ 'input-error': serviceErrors.base_price }" type="number" min="0" placeholder="Ex : 40" />
            <span v-if="serviceErrors.base_price" class="error-msg">{{ serviceErrors.base_price[0] }}</span>
          </label>
          <label class="field span-2">
            <span>Description</span>
            <textarea v-model="serviceForm.description" class="input" :class="{ 'input-error': serviceErrors.description }" placeholder="Décrivez ce service…"></textarea>
            <span v-if="serviceErrors.description" class="error-msg">{{ serviceErrors.description[0] }}</span>
          </label>
          <label class="field check">
            <input v-model="serviceForm.is_active" type="checkbox" />
            <span>Service actif</span>
          </label>
          <div class="field span-2 flex">
            <button class="btn btn-primary" :disabled="serviceSaving" type="submit">
              {{ serviceSaving ? '…' : editingService.id ? 'Enregistrer' : 'Ajouter le service' }}
            </button>
            <button type="button" class="btn btn-ghost" :disabled="serviceSaving" @click="cancelEditService()">Annuler</button>
          </div>
        </form>
      </section>

      <!-- ===================== Zones & tarifs ===================== -->
      <section id="zones" class="card">
        <div class="mb-16">
          <h2 class="zones-title">Zones &amp; tarifs</h2>
          <p class="muted small mt-8">
            Un prix fixe par zone. Le tarif enregistré dans une mission ne change pas si tu modifies la zone plus tard (RG07).
          </p>
        </div>

        <div class="flex-between wrap mb-16">
          <span v-if="zonesLoading" class="spinner"></span>
          <button v-if="!editingZone" class="btn btn-outline" @click="startEditZone({})">+ Ajouter une zone</button>
        </div>

        <div v-if="zonesLoading" class="skeleton skel-row"></div>

        <div v-else-if="zones.length" class="flex-col">
          <div v-for="z in zones" :key="z.id" class="zone-row">
            <div class="zone-main">
              <div class="flex wrap">
                <b>{{ z.origin_zone }} → {{ z.destination_zone }}</b>
                <span class="badge" :class="z.is_active ? 'badge-green' : 'badge-red'">
                  {{ z.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <label class="price-field">
                <span class="small faint">Tarif fixe (DH)</span>
                <input
                  v-model="zonePrices[z.id]"
                  class="input-boxed price-input"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="—"
                  @blur="updateZonePrice(z)"
                  @keyup.enter="$event.target.blur()"
                />
              </label>
            </div>
            <div class="zone-actions">
              <button class="btn btn-ghost" @click="toggleZone(z)">{{ z.is_active ? 'Désactiver' : 'Activer' }}</button>
              <button class="btn btn-ghost" @click="startEditZone(z)">Éditer</button>
              <button class="btn btn-danger del-btn" title="Supprimer la zone" @click="deleteZone(z)">✕</button>
            </div>
          </div>
        </div>
        <p v-else class="muted small">Aucune zone. Ajoutez vos trajets (ex : Agadir Centre → Tikiouine).</p>

        <form v-if="editingZone" class="form-grid mt-16 sub-form" @submit.prevent="saveZone()">
          <label class="field">
            <span>Zone de départ *</span>
            <input v-model="zoneForm.origin_zone" class="input" :class="{ 'input-error': zoneErrors.origin_zone }" placeholder="Ex : Agadir Centre" />
            <span v-if="zoneErrors.origin_zone" class="error-msg">{{ zoneErrors.origin_zone[0] }}</span>
          </label>
          <label class="field">
            <span>Zone de destination *</span>
            <input v-model="zoneForm.destination_zone" class="input" :class="{ 'input-error': zoneErrors.destination_zone }" placeholder="Ex : Tikiouine" />
            <span v-if="zoneErrors.destination_zone" class="error-msg">{{ zoneErrors.destination_zone[0] }}</span>
          </label>
          <label class="field">
            <span>Tarif fixe (DH)</span>
            <input v-model="zoneForm.fixed_price" class="input" :class="{ 'input-error': zoneErrors.fixed_price }" type="number" min="0" placeholder="Ex : 35" />
            <span v-if="zoneErrors.fixed_price" class="error-msg">{{ zoneErrors.fixed_price[0] }}</span>
          </label>
          <label class="field check">
            <input v-model="zoneForm.is_active" type="checkbox" />
            <span>Zone active</span>
          </label>
          <div class="field span-2 flex">
            <button class="btn btn-primary" :disabled="zoneSaving" type="submit">
              {{ zoneSaving ? '…' : editingZone.id ? 'Enregistrer' : 'Ajouter la zone' }}
            </button>
            <button type="button" class="btn btn-ghost" :disabled="zoneSaving" @click="cancelEditZone()">Annuler</button>
          </div>
        </form>
      </section>

      <ToastMessage :message="toast" @close="toast = ''" />
    </main>
  </div>
</template>

<style scoped>
.profile-ok {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px 14px;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 16px;
}
.span-2 { grid-column: span 2; }
.field.check {
  flex-direction: row;
  align-items: center;
  gap: 10px;
  padding-top: 26px;
}
.field.check input {
  width: 18px;
  height: 18px;
  accent-color: var(--green);
}

.avatar-field { justify-content: center; }
.avatar-lg {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  background: var(--green);
  color: var(--green-ink);
  display: grid;
  place-items: center;
  font-weight: 800;
  font-size: 1.6rem;
}
.avatar-actions {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 4px;
}

/* Page publique */
.public-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}
.public-info { flex: 1; min-width: 240px; }
.link-line {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px 14px;
  margin-top: 6px;
  font-size: 1.05rem;
  color: var(--fg);
}
.link-line b { color: var(--green); }
.public-info code {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 2px 6px;
  font-size: 0.78rem;
  overflow-wrap: anywhere;
}
.qr-img {
  background: #fff;
  padding: 10px;
  border-radius: 12px;
  border: 1px solid var(--border);
}
.qr-img img {
  width: 140px;
  height: 140px;
  display: block;
}

/* Zones */
.zones-title { font-size: 1.5rem; }
.zone-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  flex-wrap: wrap;
  padding: 14px 0;
  border-bottom: 1px solid var(--border);
}
.zone-row:last-child { border-bottom: none; }
.zone-main { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.price-field { display: flex; flex-direction: column; gap: 4px; max-width: 220px; }
.price-input { padding: 8px 12px; font-size: 1rem; }
.zone-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}
.del-btn { padding: 8px 12px; }

.skel-row { height: 64px; }
.row-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  flex-wrap: wrap;
  padding: 12px 0;
  border-bottom: 1px solid var(--border);
}
.row-item:last-child { border-bottom: none; }
.row-main { min-width: 0; }
.row-actions {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}
.btn-ghost.danger { color: var(--red); }
.sub-form {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px;
}

@media (max-width: 640px) {
  .form-grid { grid-template-columns: 1fr; }
  .span-2 { grid-column: span 1; }
}
</style>
