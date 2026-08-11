<script setup>
import AppIcon from "../components/AppIcon.vue"
import { computed, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api, { apiError } from '../api/axios'
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
    // C'est ici que le client « entre » chez un livreur (lien ou QR code) :
    // on mémorise le contexte auquel son compte sera rattaché.
    auth.setDriverContext({
      slug: slug.value,
      brand_name: data?.brand_name,
      logo_path: data?.logo_path,
    })
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
    router.push({
      name: 'login',
      params: { slug: slug.value },
      query: { redirect: `/drivers/${slug.value}/ai` },
    })
  } else {
    router.push({ name: 'ai-assistant', params: { slug: slug.value } })
  }
}

function openRequestForm() {
  if (!auth.isAuthenticated) {
    router.push({
      name: 'login',
      params: { slug: slug.value },
      query: { redirect: `/drivers/${slug.value}/request` },
    })
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
  <div v-if="loading" class="flex-col" style="gap: 1rem; padding-top: 3rem; align-items: center">
    <div class="skeleton" style="width: 5rem; height: 5rem; border-radius: 0.75rem"></div>
    <div class="skeleton" style="width: 15rem; height: 1.75rem"></div>
    <div class="skeleton" style="width: 10rem; height: 1.125rem"></div>
    <div class="skeleton" style="width: 100%; max-width: 43.75rem; height: 7.5rem; margin-top: 1.5rem"></div>
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
  <div v-else-if="errorMsg" class="card" style="text-align: center; padding: 2rem; max-width: 31.25rem; margin: 3rem auto">
    <p class="error-msg">{{ errorMsg }}</p>
  </div>

  <!-- DRIVER PROFILE -->
  <div v-else-if="driver" class="driver-page">
    <!-- Bandeau indisponible -->
    <div v-if="!driver.is_available" class="unavailable-banner">
      <AppIcon name="warning" />
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
              <span class="profile-badge"><AppIcon name="check" :size="14" /> Vérifié</span>
            </div>
            <div v-if="driver.description || driver.city" class="profile-desc">
              <template v-if="driver.description">{{ driver.description }}</template>
              <template v-if="driver.description && driver.city"> · </template>
              <template v-if="driver.city">{{ driver.city }}</template>
            </div>
            <div class="profile-stats">
              <span v-if="driver.total_deliveries">{{ driver.total_deliveries }} livraisons</span>
              <template v-if="driver.total_deliveries && driver.city"> · </template>
              <span v-if="driver.city">{{ driver.city }}</span>
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
          <div class="cta-action-badge"><AppIcon name="sparkle" :size="14" /> IA</div>
        </div>
        <div class="cta-action-icon cta-action-icon--accent">
          <AppIcon name="chat" :size="22" />
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
          <AppIcon name="sparkle" :size="18" /> Demander via l’IA
        </button>
      </div>

      <!-- Service Card -->
      <div class="cta-action-card" :class="{ disabled: !driver.is_available }">
        <div class="cta-action-icon cta-action-icon--surface">
          <AppIcon name="grid" :size="22" />
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
  </div>
</template>

<style scoped>
.not-found {
  text-align: center;
  padding: 4rem 0;
}

/* La colonne centrée et sa marge latérale sont celles de `.container`, qui est
   désormais la coquille de l'espace client. Les redéclarer ici doublait la
   marge horizontale et rétrécissait la page d'autant. */
.driver-page {
  display: flex;
  flex-direction: column;
}

/* Bandeau indispo */
.unavailable-banner {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.75rem 1.125rem;
  background: rgba(248, 113, 113, 0.1);
  border: 0.0625rem solid rgba(248, 113, 113, 0.3);
  border-radius: 0.625rem;
  margin-bottom: 1.5rem;
  font-weight: 700;
  color: var(--red);
  font-size: 0.9rem;
}

/* Profile hero card */
.profile-hero {
  width: 100%;
  position: relative;
  overflow: hidden;
  border-radius: 1.5rem;
  background: linear-gradient(120deg, var(--fg), color-mix(in srgb, var(--fg) 70%, var(--green)));
  color: var(--bg);
}

.profile-hero-inner {
  padding: 2.125rem 2.125rem 1.875rem;
}

.profile-hero-top {
  display: flex;
  align-items: center;
  gap: 1.125rem;
  flex-wrap: wrap;
}

.profile-avatar {
  width: 4.625rem;
  height: 4.625rem;
  border-radius: 1.25rem;
  background: var(--green);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.125rem;
  font-weight: 800;
  color: var(--green-ink);
  flex-shrink: 0;
}

.logo-img-wrap {
  width: 4.625rem;
  height: 4.625rem;
  border-radius: 1.25rem;
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
  min-width: 12.5rem;
}

.profile-name-row {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  flex-wrap: wrap;
}

.profile-name {
  font-size: 1.625rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.profile-badge {
  padding: 0.1875rem 0.5625rem;
  border-radius: 1.25rem;
  background: var(--green);
  color: var(--green-ink);
  font-size: 0.6875rem;
  font-weight: 800;
}

.profile-desc {
  opacity: 0.7;
  font-weight: 600;
  margin-top: 0.1875rem;
}

.profile-stats {
  display: flex;
  gap: 1rem;
  margin-top: 0.625rem;
  font-size: 0.8125rem;
  font-weight: 700;
  opacity: 0.85;
}

/* QR Code */
.profile-qr {
  text-align: center;
  flex-shrink: 0;
}

.qr-box {
  width: 5.5rem;
  height: 5.5rem;
  border-radius: 1rem;
  background: #fff;
  padding: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.qr-img {
  width: 100%;
  height: 100%;
  border-radius: 0.25rem;
}

.qr-label {
  font-size: 0.6875rem;
  opacity: 0.7;
  margin-top: 0.375rem;
  font-weight: 700;
}

/* CTA action cards */
.cta-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 1.125rem;
}

.cta-action-card {
  cursor: pointer;
  padding: 1.625rem;
  border-radius: 1.25rem;
  background: var(--surface);
  border: 0.0938rem solid var(--border);
  transition: transform 0.2s, border-color 0.2s;
  position: relative;
  overflow: hidden;
}

.cta-action-card:first-child {
  border-color: var(--green);
}

.cta-action-card:hover {
  transform: translateY(-0.125rem);
}

.cta-action-card.disabled {
  opacity: 0.5;
  pointer-events: none;
}

.cta-action-top {
  position: absolute;
  top: 1rem;
  right: 1rem;
}

/* L'assistant IA est en vert, pas en violet : dans le prototype, le violet ne
   sert qu'au statut « colis récupéré ». Le lui emprunter ici créait une
   troisième couleur d'accent sur un écran qui n'en a qu'une. */
.cta-action-badge {
  padding: 0.25rem 0.625rem;
  border-radius: 1.25rem;
  background: color-mix(in srgb, var(--green) 16%, transparent);
  color: var(--green);
  font-size: 0.6875rem;
  font-weight: 800;
}

.cta-action-icon {
  width: 2.875rem;
  height: 2.875rem;
  border-radius: 0.8125rem;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
}

.cta-action-icon--accent {
  background: color-mix(in srgb, var(--green) 16%, transparent);
  color: var(--green);
}

.cta-action-icon--surface {
  background: var(--surface-2);
  color: var(--fg);
}

.cta-action-title {
  font-size: 1.1875rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.cta-action-desc {
  color: var(--fg-2);
  font-size: 0.875rem;
  margin-top: 0.375rem;
  margin-bottom: 1.25rem;
  line-height: 1.45;
}

/* Section labels */
.section-label {
  font-size: 0.8125rem;
  font-weight: 800;
  color: var(--fg-2);
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

/* Services list */
/* Grille, pas liste : le prototype range le catalogue en `auto-fill` à partir
   de 240px par carte. Empilées sur une seule colonne, cinq services occupaient
   tout l'écran alors qu'ils tiennent sur deux ou trois rangs. */
.services-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
  gap: 0.875rem;
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
