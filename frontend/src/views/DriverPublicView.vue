<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api, { apiError } from '../api/axios'
import { formatPrice } from '../lib/statuses'
import ServiceCard from '../components/client/ServiceCard.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const slug = computed(() => route.params.slug)
const driver = ref(null)
const loading = ref(true)
const notFound = ref(false)
const errorMsg = ref('')

onMounted(() => {
  loadDriver()
})

async function loadDriver() {
  loading.value = true
  notFound.value = false
  errorMsg.value = ''
  try {
    const { data } = await api.get(`/drivers/${slug.value}`)
    driver.value = data
  } catch (err) {
    if (err.response?.status === 404) {
      notFound.value = true
    } else {
      errorMsg.value = apiError(err, 'Erreur lors du chargement du profil.')
    }
  } finally {
    loading.value = false
  }
}

function openAi() {
  if (!auth.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: `/drivers/${slug.value}/ai` } })
  } else {
    router.push({ name: 'ai-assistant', params: { slug: slug.value } })
  }
}

function openRequestForm() {
  if (!auth.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: `/drivers/${slug.value}/request` } })
  } else {
    router.push({ name: 'request-form', params: { slug: slug.value } })
  }
}

const initials = computed(() => {
  if (!driver.value?.brand_name) return '?'
  return driver.value.brand_name
    .split(' ')
    .map((w) => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})
</script>

<template>
  <!-- LOADING -->
  <div v-if="loading" class="flex-col" style="gap: 16px; padding-top: 48px; align-items: center">
    <div class="skeleton" style="width: 80px; height: 80px; border-radius: 12px"></div>
    <div class="skeleton" style="width: 240px; height: 28px"></div>
    <div class="skeleton" style="width: 160px; height: 18px"></div>
    <div class="skeleton" style="width: 100%; max-width: 700px; height: 120px; margin-top: 24px"></div>
  </div>

  <!-- 404 -->
  <div v-else-if="notFound" class="not-found">
    <h2>Profil introuvable</h2>
    <p class="muted mt-8">Ce livreur n'existe pas ou a été désactivé.</p>
    <router-link class="btn btn-primary mt-16" :to="{ name: 'landing' }">
      Retour à l'accueil
    </router-link>
  </div>

  <!-- ERROR -->
  <div v-else-if="errorMsg" class="card" style="text-align: center; padding: 32px; max-width: 500px; margin: 48px auto">
    <p class="error-msg">{{ errorMsg }}</p>
  </div>

  <!-- DRIVER PROFILE -->
  <div v-else-if="driver" class="driver-page">
    <!-- Bandeau indisponible -->
    <div v-if="!driver.is_available" class="unavailable-banner">
      <span>⚠️</span>
      <span>Indisponible actuellement</span>
    </div>

    <!-- Profile hero card (gradient) -->
    <div class="profile-hero au">
      <div class="profile-hero-inner">
        <!-- Avatar + Info -->
        <div class="profile-hero-top">
          <div v-if="driver.logo_path" class="logo-img-wrap">
            <img
              :src="driver.logo_path.startsWith('http') ? driver.logo_path : '/storage/' + driver.logo_path"
              :alt="driver.brand_name"
              class="logo-img"
            />
          </div>
          <div v-else class="profile-avatar">{{ initials }}</div>

          <div class="profile-info">
            <div class="profile-name-row">
              <span class="profile-name">{{ driver.brand_name }}</span>
              <span class="profile-badge">✓ Vérifié</span>
            </div>
            <div v-if="driver.description || driver.city" class="profile-desc">
              <template v-if="driver.description">{{ driver.description }}</template>
              <template v-if="driver.description && driver.city"> · </template>
              <template v-if="driver.city">{{ driver.city }}</template>
            </div>
            <div class="profile-stats">
              <span v-if="driver.rating">★ {{ driver.rating }}</span>
              <span v-if="driver.total_deliveries">· {{ driver.total_deliveries }} livraisons</span>
              <span v-if="driver.city">· {{ driver.city }}</span>
            </div>
          </div>

          <!-- QR Code -->
          <div class="profile-qr">
            <div class="qr-box">
              <img
                :src="'/api/drivers/' + slug + '/qr'"
                alt="QR Code"
                class="qr-img"
              />
            </div>
            <div class="qr-label">Scanne pour commander</div>
          </div>
        </div>
      </div>
    </div>

    <!-- DEUX CTA majeurs -->
    <div class="cta-grid mt-24">
      <!-- IA Card -->
      <div class="cta-action-card" :class="{ disabled: !driver.is_available }">
        <div class="cta-action-top">
          <div class="cta-action-badge">✦ IA</div>
        </div>
        <div class="cta-action-icon cta-action-icon--violet">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 5h16v11H9l-4 4v-4H4z"></path>
          </svg>
        </div>
        <div class="cta-action-title">Décrire ma demande</div>
        <p class="cta-action-desc">
          Écris librement ton besoin. L'IA reconnaît le service et pré-remplit le formulaire pour toi.
        </p>
        <button
          class="btn btn-primary"
          style="width: 100%"
          :disabled="!driver.is_available"
          @click="openAi"
        >
          ✦ Demander via l'IA
        </button>
      </div>

      <!-- Service Card -->
      <div class="cta-action-card" :class="{ disabled: !driver.is_available }">
        <div class="cta-action-icon cta-action-icon--surface">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"></path>
          </svg>
        </div>
        <div class="cta-action-title">Choisir un service</div>
        <p class="cta-action-desc">
          Parcours le catalogue de {{ driver.brand_name }} et remplis le formulaire manuellement.
        </p>
        <button
          class="btn btn-outline"
          style="width: 100%"
          :disabled="!driver.is_available"
          @click="openRequestForm"
        >
          Choisir un service
        </button>
      </div>
    </div>

    <!-- CATALOGUE DES SERVICES -->
    <section v-if="driver.services && driver.services.length" class="mt-32">
      <div class="section-label">Catalogue des services</div>
      <div class="services-list mt-14">
        <ServiceCard
          v-for="svc in driver.services"
          :key="svc.id"
          :service="svc"
        />
      </div>
    </section>

    <!-- ZONES DE LIVRAISON -->
    <section v-if="driver.delivery_zones && driver.delivery_zones.length" class="mt-32">
      <div class="section-label">Zones de livraison</div>
      <div class="flex-col mt-14" style="gap: 10px">
        <div
          v-for="zone in driver.delivery_zones"
          :key="zone.id"
          class="card zone-row"
        >
          <div class="flex-between">
            <span class="bold small">
              {{ zone.origin_zone }} <span class="faint">→</span> {{ zone.destination_zone }}
            </span>
            <span class="badge badge-green" v-if="zone.fixed_price">{{ formatPrice(zone.fixed_price) }}</span>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.not-found {
  text-align: center;
  padding: 64px 0;
}

.driver-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0 20px;
}

.driver-page > * {
  width: 100%;
  max-width: 1080px;
  padding-left: 0;
  padding-right: 0;
}

/* Bandeau indispo */
.unavailable-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  background: rgba(248, 113, 113, 0.1);
  border: 1px solid rgba(248, 113, 113, 0.3);
  border-radius: 10px;
  margin-bottom: 24px;
  font-weight: 700;
  color: var(--red);
  font-size: 0.9rem;
}

/* Profile hero card */
.profile-hero {
  width: 100%;
  position: relative;
  overflow: hidden;
  border-radius: 24px;
  background: linear-gradient(120deg, var(--fg), color-mix(in srgb, var(--fg) 70%, var(--green)));
  color: var(--bg);
}

.profile-hero-inner {
  padding: 34px 34px 30px;
}

.profile-hero-top {
  display: flex;
  align-items: center;
  gap: 18px;
  flex-wrap: wrap;
}

.profile-avatar {
  width: 74px;
  height: 74px;
  border-radius: 20px;
  background: var(--green);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 34px;
  font-weight: 800;
  color: var(--green-ink);
  flex-shrink: 0;
}

.logo-img-wrap {
  width: 74px;
  height: 74px;
  border-radius: 20px;
  overflow: hidden;
  flex-shrink: 0;
}

.logo-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.profile-info {
  flex: 1 1 0%;
  min-width: 200px;
}

.profile-name-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.profile-name {
  font-size: 26px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.profile-badge {
  padding: 3px 9px;
  border-radius: 20px;
  background: var(--green);
  color: var(--green-ink);
  font-size: 11px;
  font-weight: 800;
}

.profile-desc {
  opacity: 0.7;
  font-weight: 600;
  margin-top: 3px;
}

.profile-stats {
  display: flex;
  gap: 16px;
  margin-top: 10px;
  font-size: 13px;
  font-weight: 700;
  opacity: 0.85;
}

/* QR Code */
.profile-qr {
  text-align: center;
  flex-shrink: 0;
}

.qr-box {
  width: 88px;
  height: 88px;
  border-radius: 16px;
  background: #fff;
  padding: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.qr-img {
  width: 100%;
  height: 100%;
  border-radius: 4px;
}

.qr-label {
  font-size: 11px;
  opacity: 0.7;
  margin-top: 6px;
  font-weight: 700;
}

/* CTA action cards */
.cta-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.cta-action-card {
  cursor: pointer;
  padding: 26px;
  border-radius: 20px;
  background: var(--surface);
  border: 1.5px solid var(--border);
  transition: transform 0.2s, border-color 0.2s;
  position: relative;
  overflow: hidden;
}

.cta-action-card:first-child {
  border-color: var(--green);
}

.cta-action-card:hover {
  transform: translateY(-2px);
}

.cta-action-card.disabled {
  opacity: 0.5;
  pointer-events: none;
}

.cta-action-top {
  position: absolute;
  top: 16px;
  right: 16px;
}

.cta-action-badge {
  padding: 4px 10px;
  border-radius: 20px;
  background: color-mix(in srgb, var(--violet) 16%, transparent);
  color: var(--violet);
  font-size: 11px;
  font-weight: 800;
}

.cta-action-icon {
  width: 46px;
  height: 46px;
  border-radius: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
}

.cta-action-icon--violet {
  background: color-mix(in srgb, var(--violet) 16%, transparent);
  color: var(--violet);
}

.cta-action-icon--surface {
  background: var(--surface-2);
  color: var(--fg);
}

.cta-action-title {
  font-size: 19px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.cta-action-desc {
  color: var(--fg-2);
  font-size: 14px;
  margin-top: 6px;
  margin-bottom: 20px;
  line-height: 1.45;
}

/* Section labels */
.section-label {
  font-size: 13px;
  font-weight: 800;
  color: var(--fg-2);
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

/* Services list */
.services-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* Zones */
.zone-row {
  padding: 14px 18px;
}

/* Responsive */
@media (max-width: 768px) {
  .profile-hero-top {
    flex-direction: column;
    text-align: center;
    align-items: center;
  }
  .profile-name-row {
    justify-content: center;
  }
  .profile-stats {
    justify-content: center;
  }
  .cta-grid {
    grid-template-columns: 1fr;
  }
}
</style>
