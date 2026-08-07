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

function requestDelivery() {
  if (!auth.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: `/drivers/${slug.value}/request` } })
  } else {
    router.push({ name: 'request-form', params: { slug: slug.value } })
  }
}

function openAi() {
  if (!auth.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: `/drivers/${slug.value}/ai` } })
  } else {
    router.push({ name: 'ai-assistant', params: { slug: slug.value } })
  }
}

const initials = computed(() => {
  if (!driver.value?.brand_name) return '?'
  return driver.value.brand_name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2)
})
</script>

<template>
  <!-- LOADING -->
  <div v-if="loading" class="flex-col" style="gap: 16px; padding-top: 48px">
    <div class="skeleton" style="width: 80px; height: 80px; border-radius: 50%"></div>
    <div class="skeleton" style="width: 240px; height: 28px"></div>
    <div class="skeleton" style="width: 160px; height: 18px"></div>
    <div class="skeleton" style="width: 100%; height: 80px; margin-top: 24px"></div>
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
  <div v-else-if="errorMsg" class="card" style="text-align: center; padding: 32px">
    <p class="error-msg">{{ errorMsg }}</p>
  </div>

  <!-- DRIVER PROFILE -->
  <div v-else-if="driver" class="driver-page">
    <!-- Bandeau indisponible -->
    <div v-if="!driver.is_available" class="unavailable-banner">
      <span>⚠️</span>
      <span>Ce livreur est indisponible actuellement</span>
    </div>

    <!-- Header profil -->
    <div class="profile-header">
      <div v-if="driver.logo_path" class="logo-wrap">
        <img
          :src="driver.logo_path.startsWith('http') ? driver.logo_path : '/storage/' + driver.logo_path"
          :alt="driver.brand_name"
          class="logo-img"
        />
      </div>
      <div v-else class="logo-fallback">{{ initials }}</div>

      <div class="profile-info">
        <h1>{{ driver.brand_name }}</h1>
        <div class="flex wrap" style="gap: 10px; margin-top: 6px">
          <span v-if="driver.city" class="badge">📍 {{ driver.city }}</span>
          <span
            class="badge"
            :class="driver.is_available ? 'badge-green' : 'badge-red'"
          >
            {{ driver.is_available ? 'Disponible' : 'Indisponible' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Services -->
    <section v-if="driver.services && driver.services.length" class="mt-24">
      <h2>Services proposés</h2>
      <div class="grid-2 mt-16">
        <ServiceCard
          v-for="svc in driver.services"
          :key="svc.id"
          :service="svc"
        />
      </div>
    </section>

    <!-- Zones -->
    <section v-if="driver.delivery_zones && driver.delivery_zones.length" class="mt-24">
      <h2>Zones de livraison</h2>
      <div class="flex-col mt-16" style="gap: 10px">
        <div
          v-for="zone in driver.delivery_zones"
          :key="zone.id"
          class="card card-soft zone-row"
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

    <!-- QR Code -->
    <section class="mt-24">
      <div class="card qr-section">
        <div class="qr-info">
          <h3>Partagez ce profil</h3>
          <p class="small muted mt-8">
            Scannez le code QR pour accéder rapidement à la page de {{ driver.brand_name }}.
          </p>
        </div>
        <div class="qr-img-wrap">
          <img
            :src="'/api/drivers/' + slug + '/qr'"
            alt="QR Code"
            class="qr-img"
          />
          <p class="faint small" style="text-align: center; margin-top: 6px">Scannez pour partager</p>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="cta-section mt-24">
      <button
        class="btn btn-primary btn-lg"
        :disabled="!driver.is_available"
        @click="requestDelivery"
        style="width: 100%"
      >
        📦 Faire une demande de livraison
      </button>
      <button
        class="btn btn-outline btn-lg mt-8"
        :disabled="!driver.is_available"
        @click="openAi"
        style="width: 100%"
      >
        🤖 Demander avec l'IA
      </button>
    </section>
  </div>
</template>

<style scoped>
.not-found {
  text-align: center;
  padding: 64px 0;
}

.driver-page {
  max-width: 740px;
  margin: 0 auto;
}

/* Bandeau indispo */
.unavailable-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  background: rgba(248, 113, 113, 0.1);
  border: 1px solid rgba(248, 113, 113, 0.3);
  border-radius: var(--radius-sm);
  margin-bottom: 24px;
  font-weight: 700;
  color: var(--danger);
  font-size: 0.9rem;
}

/* Header profil */
.profile-header {
  display: flex;
  align-items: center;
  gap: 20px;
}

.logo-img {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--border-strong);
}

.logo-fallback {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: var(--brand);
  color: #04140b;
  display: grid;
  place-items: center;
  font-size: 1.6rem;
  font-weight: 800;
  flex-shrink: 0;
}

.profile-info {
  flex: 1;
}

.profile-info h1 {
  font-size: 1.8rem;
}

/* Zones */
.zone-row {
  padding: 14px 18px;
}

/* QR */
.qr-section {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 24px;
}

.qr-img-wrap {
  flex-shrink: 0;
}

.qr-img {
  width: 130px;
  height: 130px;
  border-radius: var(--radius-sm);
  background: #fff;
  padding: 6px;
}

/* CTA */
.cta-section {
  padding-bottom: 48px;
}

/* Responsive */
@media (max-width: 768px) {
  .profile-header { flex-direction: column; text-align: center; }
  .profile-info .flex { justify-content: center; }
  .qr-section { flex-direction: column; align-items: center; text-align: center; }
}
</style>
