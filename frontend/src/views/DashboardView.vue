<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api, { apiError } from '../api/axios'
import { useAuthStore } from '../stores/auth'
import { usePolling } from '../composables/usePolling'
import { formatPrice, timeAgo, statusLabel } from '../lib/statuses'
import { copyPublicLink, prettyLink, publicUrl, qrUrl } from '../lib/driverLink'
import DriverSidebar from '../components/driver/DriverSidebar.vue'
import StatCard from '../components/driver/StatCard.vue'
import RequestCard from '../components/driver/RequestCard.vue'
import ToastMessage from '../components/driver/ToastMessage.vue'
import AppIcon from '../components/AppIcon.vue'

/**
 * Tableau de bord livreur — GET /dashboard (wrapper { success, data }) + polling 10 s.
 * Conforme au prototype : sidebar, 4 stats, graphique sobre, notifications, missions.
 */
const router = useRouter()
const auth = useAuthStore()

const { data, loading, error, start } = usePolling(async () => {
  const res = await api.get('/dashboard')
  return res.data
}, 10000)

const dash = computed(() => data.value || {})

// ---- Profil public : bandeau d'incitation si aucun profil créé ----
const profiles = ref([])
const profileLoading = ref(true)

async function loadProfiles() {
  try {
    const res = await api.get('/driver-profiles')
    profiles.value = res.data.data ?? res.data ?? []
  } catch {
    profiles.value = []
  } finally {
    profileLoading.value = false
  }
}

const hasProfile = computed(() => profiles.value.length > 0)
const hasError = computed(() => !!error.value && !data.value)

// ---- Lien client : la porte d'entrée du livreur ----
const slug = computed(() => profiles.value[0]?.slug || '')

const toast = ref('')
let toastTimer = null

function showToast(message) {
  toast.value = message
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value = '' }, 3000)
}

async function copyClientLink() {
  const message = await copyPublicLink(slug.value)
  if (message) showToast(message)
}

function goPublicPage() {
  if (slug.value) router.push({ name: 'driver-public', params: { slug: slug.value } })
}

onBeforeUnmount(() => clearTimeout(toastTimer))

// ---- Onglets cosmétiques (pas de série par période côté API) ----
const period = ref('today')

// ---- Liste notifiante (demandes, messages, avis) ----
const feedItems = computed(() => {
  const items = []
  for (const r of dash.value.recent_requests || []) {
    items.push({
      key: `req-${r.id}`,
      icon: 'package',
      title: `Demande ${r.tracking_number || `#${r.id}`}`,
      body: statusLabel(r.status),
      at: r.created_at,
      to: () => router.push({ name: 'driver-mission', params: { id: r.id } }),
    })
  }
  for (const m of dash.value.recent_messages || []) {
    items.push({
      key: `msg-${m.id}`,
      icon: 'chat',
      title: m.sender_name || 'Client',
      body: m.content,
      at: m.created_at,
    })
  }
  if (dash.value.average_rating != null) {
    items.push({
      key: 'rating',
      icon: 'star',
      title: 'Nouvel avis client',
      body: `Note moyenne : ${dash.value.average_rating}/5`,
      at: null,
    })
  }
  return items.slice(0, 5)
})

const firstName = computed(() => String(auth.user?.name || 'livreur').split(' ')[0])

onMounted(() => {
  start()
  loadProfiles()
})
</script>

<template>
  <div class="driver-layout">
    <DriverSidebar />

    <main class="driver-main">
      <!-- En-tête -->
        <div class="flex-between wrap mb-16">
          <div>
            <h2>Tableau de bord</h2>
            <p class="muted small">Bonjour {{ firstName }} — voici ton activité aujourd'hui.</p>
          </div>
          <div class="btn-group">
            <span class="pill-pending">Demandes : {{ dash.pending_requests ?? 0 }}</span>
            <RouterLink class="btn btn-primary btn-sm" :to="{ name: 'driver-requests' }">Voir les demandes</RouterLink>
          </div>
        </div>

      <!-- Bandeau profil manquant -->
      <div v-if="!profileLoading && !hasProfile" class="card banner-profil mb-16">
        <div class="flex-between wrap">
          <div>
            <h3>Créez votre profil public</h3>
            <p class="muted small">Votre marque, vos services et vos zones : les clients vous trouvent et commandent via votre page publique et votre QR code.</p>
          </div>
          <button class="btn btn-primary" @click="router.push({ name: 'driver-profile' })">Configurer mon profil</button>
        </div>
      </div>

      <!-- Onglets période (cosmétiques : pas de série quotidienne côté API) -->
      <div class="tabs mb-16">
        <button class="btn tab" :class="period === 'today' ? 'btn-primary' : 'btn-ghost'" @click="period = 'today'">Aujourd'hui</button>
        <button class="btn tab" :class="period === 'week' ? 'btn-primary' : 'btn-ghost'" @click="period = 'week'">Cette semaine</button>
      </div>

      <!-- Squelettes -->
      <template v-if="loading && !data">
        <div class="grid-4">
          <div v-for="i in 4" :key="i" class="skeleton skel-stat"></div>
        </div>
      </template>

      <!-- Erreur initiale -->
      <div v-else-if="hasError" class="card">
        <h3>Impossible de charger le tableau de bord</h3>
        <p class="muted small mt-8">{{ apiError(error, 'Erreur de chargement.') }}</p>
        <button class="btn btn-outline mt-16" @click="start()">Réessayer</button>
      </div>

      <template v-else>
        <!-- 4 cartes stats -->
        <div class="grid-4">
          <StatCard label="Livraisons" :value="dash.delivered_missions ?? '—'" icon="truck" />
          <StatCard label="Revenus" :value="formatPrice(dash.collected_revenue)" icon="cash" />
          <StatCard label="Missions actives" :value="dash.active_missions ?? '—'" icon="bolt" sub="temps réel" />
          <StatCard label="Note" :value="dash.average_rating != null ? `${dash.average_rating}/5` : '—'" icon="star" />
        </div>

      <!-- Graphique sobre : pas de série quotidienne côté backend -->
      <div class="grid-dash mt-16">
        <div class="card chart">
          <div class="flex-between wrap">
            <div>
              <h3>Revenus — 7 derniers jours</h3>
              <p class="small faint mt-8">Statistiques détaillées bientôt disponibles.</p>
            </div>
            <div class="flex wrap chart-stats">
              <div class="chart-stat">
                <span class="small faint">Estimés</span>
                <b>{{ formatPrice(dash.estimated_revenue) }}</b>
              </div>
              <div class="chart-stat">
                <span class="small faint">Encaissés</span>
                <b class="green">{{ formatPrice(dash.collected_revenue) }}</b>
              </div>
            </div>
          </div>
          <div class="bars">
            <div
              v-for="n in 7"
              :key="n"
              class="bar"
              :style="{ height: 34 + ((dash.delivered_missions ?? 0) % 7) * 6 + ((n * 7) % 22) + 'px' }"
              :title="`Jour ${n}`"
            ></div>
          </div>
        </div>

        <!-- Colonne latérale : le fil d'activité en regard du graphique, comme
             dans le prototype, puis le lien client. -->
        <div class="dash-side">
          <!-- Notifications -->
          <section>
            <div class="flex-between wrap mb-16">
              <h3 class="flex">
                Notifications
                <span v-if="(dash.unread_notifications ?? 0) > 0" class="counter">{{ dash.unread_notifications }}</span>
              </h3>
              <RouterLink class="small" :to="{ name: 'driver-notifications' }">Voir tout →</RouterLink>
            </div>
            <div v-if="feedItems.length" class="flex-col">
              <button
                v-for="item in feedItems"
                :key="item.key"
                class="card feed"
                @click="item.to ? item.to() : router.push({ name: 'driver-notifications' })"
              >
                <span class="feed-icon"><AppIcon :name="item.icon" :size="18" /></span>
                <span class="feed-body">
                  <span class="flex-between wrap">
                    <b class="small">{{ item.title }}</b>
                    <span v-if="item.at" class="faint small">{{ timeAgo(item.at) }}</span>
                  </span>
                  <span v-if="item.body" class="small muted">{{ item.body }}</span>
                </span>
              </button>
            </div>
            <div v-else class="card card-soft">
              <p class="muted small">Aucune activité récente. Les nouvelles demandes apparaîtront ici.</p>
            </div>
          </section>

          <!-- Lien client : la porte d'entrée du livreur. Un compte client est
               rattaché au livreur qui l'a amené — sans ce lien, personne ne
               peut commander chez lui. -->
          <section class="card link-card">
            <h3 class="mb-16">Mon lien client</h3>

            <template v-if="hasProfile">
              <p class="small muted">
                Partagez ce lien ou faites scanner le QR code : vos clients arrivent
                directement sur votre page pour commander.
              </p>
              <div class="link-qr">
                <img v-if="qrUrl(slug)" :src="qrUrl(slug)" alt="QR code de ma page client" />
              </div>
              <div class="link-line">
                <b>{{ prettyLink(slug) }}</b>
              </div>
              <p class="faint small">
                Lien actif : <code>{{ publicUrl(slug) }}</code>
              </p>
              <div class="flex wrap mt-16">
                <button class="btn btn-primary" @click="copyClientLink()">
                  <AppIcon name="clipboard" /> Copier le lien
                </button>
                <button class="btn btn-outline" @click="goPublicPage()">
                  <AppIcon name="eye" :size="18" /> Aperçu client
                </button>
              </div>
            </template>

            <template v-else>
              <p class="small muted">
                Créez votre profil public pour obtenir le lien que vous partagerez à vos clients.
              </p>
              <RouterLink class="btn btn-primary mt-16" :to="{ name: 'driver-profile' }">
                Créer mon profil public
              </RouterLink>
            </template>
          </section>
        </div>
      </div>

        <!-- Missions : pleine largeur, comme dans le prototype. Ce sont des
             lignes larges (icône, service, trajet, montant) — les enfermer dans
             une demi-colonne tronquait les adresses. -->
        <section class="mt-16">
          <div class="flex-between wrap mb-16">
            <h3>Missions</h3>
            <RouterLink class="small" :to="{ name: 'driver-requests' }">Voir tout →</RouterLink>
          </div>
          <div v-if="(dash.recent_requests || []).length" class="flex-col">
            <RequestCard v-for="r in dash.recent_requests" :key="r.id" :request="r" arrow />
          </div>
          <div v-else class="card card-soft">
            <p class="muted small">Aucune mission pour le moment. Vos demandes apparaîtront ici.</p>
          </div>
        </section>
      </template>

      <ToastMessage :message="toast" @close="toast = ''" />
    </main>
  </div>
</template>

<style scoped>
.tabs { display: flex; gap: 0.5rem; }
.tab { border-radius: 62.4375rem; padding: 0.5rem 1rem; }
.skel-stat { height: 6.75rem; }
.banner-profil {
  border: 0.0625rem dashed var(--border-2);
  background: var(--surface);
}

/* Pill compteur (en-tête) */
.pill-pending {
  background: var(--green);
  color: var(--green-ink);
  border-radius: 62.4375rem;
  padding: 0.5rem 1rem;
  font-size: 0.8125rem;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  white-space: nowrap;
}
.gap-10 { gap: 0.625rem; }

/* Légende de disponibilité */
/* Graphique + colonne latérale (notifications, lien client) */
.grid-dash {
  display: grid;
  /* minmax(0, …) : sans lui, le graphique impose sa largeur intrinsèque à la
     piste et la page défile horizontalement. */
  grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
  gap: 1.125rem;
  align-items: start;
}

.dash-side {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1.125rem;
}

/* Graphique sobre */
.chart { padding-bottom: 0.875rem; }

/* Disponibilité */
/* Lien client */
.link-card { display: flex; flex-direction: column; }

/* Le QR reste sur fond blanc quel que soit le thème : c'est un code optique,
   il doit garder son contraste noir sur blanc pour rester scannable. */
.link-qr {
  align-self: center;
  background: #fff;
  padding: 0.625rem;
  border-radius: 0.75rem;
  border: 0.0625rem solid var(--border);
  margin: 1rem 0;
}
.link-qr img {
  width: 8.75rem;
  height: 8.75rem;
  display: block;
}

.link-line {
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 0.75rem;
  padding: 0.75rem 0.875rem;
  margin-bottom: 0.5rem;
  font-size: 1rem;
  /* Un slug long ne doit pas élargir la colonne latérale. */
  overflow-wrap: anywhere;
}
.link-line b { color: var(--green); }

.link-card code {
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 0.375rem;
  padding: 0.125rem 0.375rem;
  font-size: 0.78rem;
  overflow-wrap: anywhere;
}

.chart-stats { gap: 1.375rem; }
.chart-stat {
  display: flex;
  flex-direction: column;
  text-align: right;
  font-size: 1.05rem;
  color: var(--fg);
}
.chart-stat .green { color: var(--green); }
.bars {
  display: flex;
  align-items: flex-end;
  gap: 0.625rem;
  height: 5.75rem;
  margin-top: 1.125rem;
  padding-top: 0.5rem;
  border-top: 0.0625rem solid var(--border);
}
.bar {
  flex: 1;
  border-radius: 0.375rem 0.375rem 0 0;
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  min-height: 0.5rem;
}
.bar:nth-child(3n) { background: rgba(34, 197, 111, 0.22); }
.bar:nth-child(7) { background: rgba(34, 197, 111, 0.55); }

/* Fil d'activité */
.feed {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  text-align: left;
  cursor: pointer;
  border: 0.0625rem solid var(--border);
  transition: border-color 0.2s, background 0.2s;
}
.feed:hover { border-color: var(--border-2); background: var(--surface-2); }
.feed-icon {
  font-size: 1.2rem;
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 0.625rem;
  width: 2.375rem;
  height: 2.375rem;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.feed-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}
.feed-body .small.muted {
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

/* « Revenus 7 jours » et « Disponibilité » s'empilent au palier 1024. */
@media (max-width: 1024px) {
  .grid-dash { grid-template-columns: minmax(0, 1fr); }
}
</style>
