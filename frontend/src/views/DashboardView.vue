<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api/axios'
import { apiError } from '../api/axios'
import { useAuthStore } from '../stores/auth'
import { usePolling } from '../composables/usePolling'
import { formatPrice, timeAgo, statusLabel } from '../lib/statuses'
import DriverSidebar from '../components/driver/DriverSidebar.vue'
import StatCard from '../components/driver/StatCard.vue'
import RequestCard from '../components/driver/RequestCard.vue'

/**
 * Tableau de bord livreur — GET /dashboard (wrapper { success, data }) + polling 10 s.
 * Conforme au prototype : sidebar, 4 stats, graphique sobre, notifications, missions.
 */
const router = useRouter()
const auth = useAuthStore()

const { data, loading, error, start } = usePolling(async () => {
  const res = await api.get('/dashboard')
  return res.data.data
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

// ---- Disponibilité : PATCH partiel driver-profiles/{id} (is_available) ----
const availabilityBusy = ref(false)
const isAvailable = computed(() =>
  profiles.value[0] ? profiles.value[0].is_available !== false : true,
)

async function toggleAvailability() {
  const p = profiles.value[0]
  if (!p || availabilityBusy.value) return
  availabilityBusy.value = true
  try {
    const next = p.is_available === false
    const res = await api.patch(`/driver-profiles/${p.id}`, { is_available: next })
    const updated = res.data.data ?? res.data
    if (updated && updated.id) profiles.value = [updated]
  } catch {
    // L'état local reste inchangé si la mise à jour échoue.
  } finally {
    availabilityBusy.value = false
  }
}

// ---- Onglets cosmétiques (pas de série par période côté API) ----
const period = ref('today')

// ---- Liste notifiante (📦 demandes, 💬 messages, ⭐ avis) ----
const feedItems = computed(() => {
  const items = []
  for (const r of dash.value.recent_requests || []) {
    items.push({
      key: `req-${r.id}`,
      icon: '📦',
      title: `Demande ${r.tracking_number || `#${r.id}`}`,
      body: statusLabel(r.status),
      at: r.created_at,
      to: () => router.push({ name: 'driver-mission', params: { id: r.id } }),
    })
  }
  for (const m of dash.value.recent_messages || []) {
    items.push({
      key: `msg-${m.id}`,
      icon: '💬',
      title: m.sender_name || 'Client',
      body: m.content,
      at: m.created_at,
    })
  }
  if (dash.value.average_rating != null) {
    items.push({
      key: 'rating',
      icon: '⭐',
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
          <div class="flex wrap gap-10">
            <span class="pill-pending">Demandes : {{ dash.pending_requests ?? 0 }}</span>
            <RouterLink class="btn btn-primary btn-sm" :to="{ name: 'driver-requests' }">Voir les demandes</RouterLink>
          </div>
        </div>

      <!-- Bandeau profil manquant -->
      <div v-if="!profileLoading && !hasProfile" class="card banner-profil mb-16">
        <div class="flex-between wrap">
          <div>
            <h3>Créez votre profil public ✨</h3>
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
          <StatCard label="Livraisons" :value="dash.delivered_missions ?? '—'" icon="📦" />
          <StatCard label="Revenus" :value="formatPrice(dash.collected_revenue)" icon="💰" accent="green" />
          <StatCard label="Missions actives" :value="dash.active_missions ?? '—'" icon="🛵" accent="blue" sub="temps réel" />
          <StatCard label="Note" :value="dash.average_rating != null ? `${dash.average_rating}/5` : '—'" icon="⭐" accent="yellow" />
        </div>

      <!-- Légende de disponibilité -->
      <div class="legend mb-16">
        <span class="legend-item"><span class="dot-online"></span> En ligne</span>
        <span class="legend-item"><span class="legend-dot off"></span> En pause</span>
      </div>

      <!-- Graphique sobre : pas de série quotidienne côté backend -->
      <div class="grid-dash">
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

        <!-- Disponibilité -->
        <section class="card avail">
          <div class="flex-between">
            <h3>Disponibilité</h3>
            <span class="status-dot" :class="isAvailable ? 'on' : 'off'"></span>
          </div>
          <p class="avail-text">{{ isAvailable ? 'Vous êtes en ligne' : 'Vous êtes en pause' }}</p>
          <p class="small muted">
            {{ isAvailable
              ? 'Les clients peuvent vous envoyer des demandes de livraison.'
              : 'Vous n’êtes plus visible pour les nouvelles demandes.' }}
          </p>
          <button
            class="switch"
            :class="{ on: isAvailable }"
            :disabled="availabilityBusy || !hasProfile"
            role="switch"
            :aria-checked="isAvailable"
            :title="hasProfile ? 'Changer ma disponibilité' : 'Créez d’abord votre profil public'"
            @click="toggleAvailability()"
          >
            <span class="knob"></span>
          </button>
          <p class="faint small mt-8">
            {{ hasProfile ? 'Mettez en pause quand vous n’êtes plus disponible.' : 'Créez votre profil public pour gérer votre disponibilité.' }}
          </p>
        </section>
      </div>

        <div class="grid-2 mt-16">
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
                <span class="feed-icon">{{ item.icon }}</span>
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

          <!-- Missions -->
          <section>
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
        </div>
      </template>
    </main>
  </div>
</template>

<style scoped>
.tabs { display: flex; gap: 8px; }
.tab { border-radius: 999px; padding: 8px 16px; }
.skel-stat { height: 108px; }
.banner-profil {
  border: 1px dashed var(--border-2);
  background: var(--surface);
}

/* Pill compteur (en-tête) */
.pill-pending {
  background: var(--green);
  color: var(--green-ink);
  border-radius: 999px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}
.gap-10 { gap: 10px; }

/* Légende de disponibilité */
.legend {
  display: flex;
  gap: 18px;
  align-items: center;
  font-size: 12px;
  color: var(--fg-2);
  font-weight: 600;
}
.legend-item { display: inline-flex; align-items: center; gap: 6px; }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.legend-dot.off { background: var(--amber); }

/* Graphique + carte disponibilité */
.grid-dash {
  display: grid;
  grid-template-columns: 1.5fr 1fr;
  gap: 14px;
  align-items: start;
}

/* Graphique sobre */
.chart { padding-bottom: 14px; }

/* Disponibilité */
.avail { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
.status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--surface-3);
  flex-shrink: 0;
}
.status-dot.on { background: var(--green); box-shadow: var(--green-glow) 0 0 0 4px; }
.status-dot.off { background: var(--amber); }
.avail-text { font-size: 1.35rem; font-weight: 800; color: var(--fg); margin-top: 8px; }

.switch {
  position: relative;
  width: 46px;
  height: 26px;
  border-radius: 26px;
  background: var(--surface-3);
  border: 1px solid var(--border);
  cursor: pointer;
  padding: 0;
  transition: background 0.2s;
  margin-top: 12px;
  flex-shrink: 0;
}
.switch .knob {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #fff;
  transition: transform 0.2s;
}
.switch.on { background: var(--green); border-color: transparent; }
.switch.on .knob { transform: translateX(20px); }
.switch:disabled { opacity: 0.5; cursor: not-allowed; }
.chart-stats { gap: 22px; }
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
  gap: 10px;
  height: 92px;
  margin-top: 18px;
  padding-top: 8px;
  border-top: 1px solid var(--border);
}
.bar {
  flex: 1;
  border-radius: 6px 6px 0 0;
  background: var(--surface-2);
  border: 1px solid var(--border);
  min-height: 8px;
}
.bar:nth-child(3n) { background: rgba(34, 197, 111, 0.22); }
.bar:nth-child(7) { background: rgba(34, 197, 111, 0.55); }

/* Fil d'activité */
.feed {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  text-align: left;
  cursor: pointer;
  border: 1px solid var(--border);
  transition: border-color 0.2s, background 0.2s;
}
.feed:hover { border-color: var(--border-2); background: var(--surface-2); }
.feed-icon {
  font-size: 1.2rem;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 10px;
  width: 38px;
  height: 38px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.feed-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.feed-body .small.muted {
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

@media (max-width: 980px) {
  .grid-dash { grid-template-columns: 1fr; }
}
</style>
