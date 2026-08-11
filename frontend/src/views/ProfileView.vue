<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api/axios'
import { apiError } from '../api/axios'
import { useAuthStore } from '../stores/auth'
import { formatPrice } from '../lib/statuses'
import {
  copyPublicLink,
  prettyLink as prettyDriverLink,
  publicUrl,
  qrUrl,
} from '../lib/driverLink'
import AppIcon from '../components/AppIcon.vue'
import DriverSidebar from '../components/driver/DriverSidebar.vue'
import ToastMessage from '../components/driver/ToastMessage.vue'

/**
 * Profil & marque livreur — conforme screen10-profile-driver.html.
 * Trois blocs : profil public, catalogue services, page publique.
 * La section zones & tarifs a été extraite dans ZonesTarifsView.vue.
 */
const router = useRouter()
const auth = useAuthStore()

// ============================================================
// Toast
// ============================================================
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
  phone: '',
  description: '',
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
        phone: p.phone ?? auth.user?.phone ?? '',
        description: p.description || '',
        is_available: p.is_available !== false,
      }
    } else {
      profileForm.value.phone = auth.user?.phone ?? ''
    }
  } catch {
    profiles.value = []
  } finally {
    profileLoading.value = false
  }
}

function onBrandName() {
  if (!currentProfile.value && !profileForm.value.slug.trim()) {
    profileForm.value.slug = slugify(profileForm.value.brand_name)
  }
}

const profileInitial = computed(() =>
  (profileForm.value.brand_name || 'M').trim().charAt(0).toUpperCase(),
)

const slug = computed(() => currentProfile.value?.slug || '')
const prettyLink = computed(() => prettyDriverLink(slug.value))
const localPublicUrl = computed(() => publicUrl(slug.value))
const qrSrc = computed(() => qrUrl(slug.value))

async function saveProfile() {
  profileErrors.value = {}
  profileSaving.value = true
  try {
    const payload = {
      brand_name: profileForm.value.brand_name.trim(),
      slug: profileForm.value.slug.trim().toLowerCase(),
      logo_path: profileForm.value.logo_path.trim() || null,
      city: profileForm.value.city.trim(),
      description: profileForm.value.description.trim() || null,
      phone: profileForm.value.phone.trim() || null,
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

async function deleteProfile() {
  if (!currentProfile.value) return
  if (!window.confirm('Supprimer votre profil public ? Vos services et zones seront masqués de votre page publique. Vous pourrez en recréer un plus tard.')) return
  profileSaving.value = true
  try {
    await api.delete(`/driver-profiles/${currentProfile.value.id}`)
    showToast('Profil public supprimé')
    profiles.value = []
    profileForm.value = {
      brand_name: '',
      slug: '',
      logo_path: '',
      city: '',
      phone: auth.user?.phone ?? '',
      description: '',
      is_available: true,
    }
  } catch (err) {
    showToast(apiError(err, 'Impossible de supprimer le profil.'))
  } finally {
    profileSaving.value = false
  }
}

async function copyLink() {
  const message = await copyPublicLink(slug.value)
  if (message) showToast(message)
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
    if (editingService.value?.id) {
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
// Init
// ============================================================
onMounted(() => {
  loadProfiles()
  loadServices()
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

      <!-- Deux colonnes : identité + catalogue à gauche, page publique à droite -->
      <div class="profile-grid">
        <div class="profile-col">

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
                <button class="btn btn-ghost danger" :disabled="profileSaving" @click="deleteProfile()">
                  Supprimer
                </button>
              </div>
            </div>

            <form v-if="!profileLoading" class="form-grid" @submit.prevent="saveProfile()">
              <!-- Avatar + Changer le logo -->
              <label class="field avatar-field">
                <span class="avatar-lg">{{ profileInitial }}</span>
              </label>
              <div class="avatar-actions">
                <span class="small faint">Changer le logo</span>
                <input v-model="profileForm.logo_path" class="input" :class="{ 'input-error': profileErrors.logo_path }" placeholder="https://…/logo.png" />
                <span v-if="profileErrors.logo_path" class="error-msg">{{ profileErrors.logo_path[0] }}</span>
              </div>

              <!-- Nom de la marque -->
              <label class="field">
                <span>Nom de la marque *</span>
                <input v-model="profileForm.brand_name" class="input" :class="{ 'input-error': profileErrors.brand_name }" placeholder="Ex : Rayan Express" @input="onBrandName" />
                <span v-if="profileErrors.brand_name" class="error-msg">{{ profileErrors.brand_name[0] }}</span>
              </label>

              <!-- Ville -->
              <label class="field">
                <span>Ville</span>
                <input v-model="profileForm.city" class="input" :class="{ 'input-error': profileErrors.city }" placeholder="Ex : Agadir" />
                <span v-if="profileErrors.city" class="error-msg">{{ profileErrors.city[0] }}</span>
              </label>

              <!-- Téléphone -->
              <label class="field">
                <span>Téléphone</span>
                <input v-model="profileForm.phone" class="input" :class="{ 'input-error': profileErrors.phone }" placeholder="Ex : 06 12 34 56 78" />
                <span v-if="profileErrors.phone" class="error-msg">{{ profileErrors.phone[0] }}</span>
              </label>

              <!-- Description -->
              <label class="field span-2">
                <span>Description</span>
                <textarea v-model="profileForm.description" class="input" :class="{ 'input-error': profileErrors.description }" placeholder="Décrivez votre activité…" rows="3"></textarea>
                <span v-if="profileErrors.description" class="error-msg">{{ profileErrors.description[0] }}</span>
              </label>

              <!-- Slug (discret) -->
              <label class="field span-2 slug-field">
                <input v-model="profileForm.slug" class="input slug-input" :class="{ 'input-error': profileErrors.slug }" placeholder="rayan-express" />
                <span class="faint small">Identifiant public — utilisé dans votre page : /drivers/{{ profileForm.slug || '…' }}</span>
                <span v-if="profileErrors.slug" class="error-msg">{{ profileErrors.slug[0] }}</span>
              </label>

              <!-- Disponibilité -->
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

          <!-- ===================== Catalogue des services ===================== -->
          <section class="card mb-16">
            <div class="flex-between wrap mb-16">
              <h3>Catalogue des services</h3>
              <button v-if="!editingService" class="btn btn-outline" @click="startEditService({})">+ Ajouter</button>
            </div>

            <div v-if="servicesLoading" class="skeleton skel-row"></div>

            <div v-else-if="services.length" class="flex-col">
              <div v-for="s in services" :key="s.id" class="svc-row">
                <div class="svc-main">
                  <div class="svc-header">
                    <b>{{ s.name }}</b>
                    <span v-if="s.base_price" class="badge badge-green">dès {{ formatPrice(s.base_price) }}</span>
                  </div>
                  <p v-if="s.description" class="muted small mt-8">{{ s.description }}</p>
                  <div class="svc-meta">
                    <span class="badge" :class="s.is_active ? 'badge-green' : 'badge-red'">
                      {{ s.is_active ? 'Actif' : 'Inactif' }}
                    </span>
                  </div>
                </div>
                <div class="svc-actions">
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

        </div>

        <!-- ===================== Ta page publique ===================== -->
        <aside class="profile-col">
          <section v-if="currentProfile" class="card">
            <h3 class="mb-16">Ta page publique</h3>
            <div class="public-row">
              <div class="qr-img">
                <img v-if="qrSrc" :src="qrSrc" alt="QR code de la page publique" />
              </div>
              <div class="public-info">
                <span class="small faint">Lien unique</span>
                <div class="link-line">
                  <b>{{ prettyLink }}</b>
                </div>
                <p class="faint small mt-8">
                  Lien local actif : <code>{{ localPublicUrl }}</code>
                </p>
                <div class="flex wrap mt-16">
                  <button class="btn btn-primary" @click="copyLink()"><AppIcon name="clipboard" /> Copier le lien</button>
                  <button class="btn btn-outline" @click="goPublicPage()">
                    <AppIcon name="eye" :size="18" /> Aperçu client
                  </button>
                </div>
              </div>
            </div>
          </section>
        </aside>
      </div>

      <ToastMessage :message="toast" @close="toast = ''" />
    </main>
  </div>
</template>

<style scoped>
/* ---------- Profil public ---------- */
.profile-ok {
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 0.75rem;
  padding: 0.75rem 0.875rem;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 1rem;
}
.span-2 { grid-column: span 2; }
.field.check {
  flex-direction: row;
  align-items: center;
  gap: 0.625rem;
  padding-top: 1.625rem;
}
.field.check input {
  width: 1.125rem;
  height: 1.125rem;
  accent-color: var(--green);
}

.avatar-field { justify-content: center; }
.avatar-lg {
  width: 4rem;
  height: 4rem;
  border-radius: 1rem;
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
  gap: 0.25rem;
}

/* Note affichée — input désactivé */
.input-readonly {
  opacity: 0.7;
  cursor: default;
}

/* Slug — champ discret sous le formulaire */
.slug-field {
  margin-top: 0.25rem;
}
.slug-input {
  font-size: 0.875rem;
  color: var(--fg-2);
}

/* ---------- Services ---------- */
.svc-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.875rem;
  flex-wrap: wrap;
  padding: 0.75rem 0;
  border-bottom: 0.0625rem solid var(--border);
}
.svc-row:last-child { border-bottom: none; }
.svc-main { min-width: 0; flex: 1; }
.svc-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.svc-meta { margin-top: 0.375rem; }
.svc-actions {
  display: flex;
  gap: 0.25rem;
  flex-wrap: wrap;
}
.btn-ghost.danger { color: var(--red); }

.sub-form {
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 0.75rem;
  padding: 1rem;
}

/* ---------- Page publique ---------- */
.public-row {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 1.125rem;
}
.public-info { min-width: 0; }
.link-line {
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 0.75rem;
  padding: 0.75rem 0.875rem;
  margin-top: 0.375rem;
  font-size: 1.05rem;
  color: var(--fg);
}
.link-line b { color: var(--green); }
.public-info code {
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 0.375rem;
  padding: 0.125rem 0.375rem;
  font-size: 0.78rem;
  overflow-wrap: anywhere;
}
.qr-img {
  background: #fff;
  padding: 0.625rem;
  border-radius: 0.75rem;
  border: 0.0625rem solid var(--border);
  align-self: center;
}
.qr-img img {
  width: 8.75rem;
  height: 8.75rem;
  display: block;
}

/* ---------- Layout colonnes ---------- */
.profile-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  gap: 1.125rem;
  align-items: start;
}
.profile-col { min-width: 0; }

.skel-row { height: 4rem; }

@media (max-width: 1100px) {
  .profile-grid { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 640px) {
  .form-grid { grid-template-columns: 1fr; }
  .span-2 { grid-column: span 1; }
}
</style>
