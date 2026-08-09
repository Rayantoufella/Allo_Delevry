<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api/axios'
import { apiError } from '../api/axios'
import { usePolling } from '../composables/usePolling'
import { timeAgo } from '../lib/statuses'
import AppIcon from '../components/AppIcon.vue'
import DriverSidebar from '../components/driver/DriverSidebar.vue'

/**
 * Notifications livreur — GET /notifications paginé ({ data, meta }) + polling 10 s.
 */
const router = useRouter()

const { data, loading, error, start, refresh } = usePolling(async () => {
  const res = await api.get('/notifications')
  return res.data
}, 10000)

const notifications = computed(() => data.value?.data ?? data.value ?? [])
const meta = computed(() => data.value?.meta ?? null)
const unreadCount = computed(() => notifications.value.filter((n) => !n.read_at).length)

const markingAll = ref(false)
const loadingMore = ref(false)
const actionError = ref('')

const TYPE_META = {
  delivery_request_created: { icon: 'package', label: 'Nouvelle demande' },
  status_changed: { icon: 'refresh', label: 'Statut mis à jour' },
  chat_message: { icon: 'chat', label: 'Nouveau message' },
  review: { icon: 'star', label: 'Nouvel avis' },
}

function typeMeta(type) {
  return TYPE_META[type] || { icon: 'bell', label: 'Notification' }
}

async function markAllRead() {
  markingAll.value = true
  actionError.value = ''
  try {
    await api.patch('/notifications/read-all')
    await refresh()
  } catch (err) {
    actionError.value = apiError(err)
  } finally {
    markingAll.value = false
  }
}

async function openNotification(n) {
  if (!n.read_at) {
    try {
      await api.patch(`/notifications/${n.id}/read`)
      n.read_at = new Date().toISOString()
    } catch {
      // Navigation conservée même si la lecture échoue.
    }
  }
  if (n.delivery_request_id) {
    router.push({ name: 'driver-mission', params: { id: n.delivery_request_id } })
  }
}

async function loadMore() {
  if (loadingMore.value || !meta.value || !meta.value.next_page_url) return
  loadingMore.value = true
  try {
    const res = await api.get('/notifications', {
      params: { page: (meta.value.current_page || 1) + 1 },
    })
    data.value = {
      ...data.value,
      data: [...notifications.value, ...(res.data.data ?? [])],
      meta: res.data.meta ?? null,
    }
  } catch (err) {
    actionError.value = apiError(err)
  } finally {
    loadingMore.value = false
  }
}

onMounted(() => {
  start()
})
</script>

<template>
  <div class="driver-layout">
    <DriverSidebar />

    <main class="driver-main">
      <div class="flex-between wrap mb-16">
        <div>
          <h2>Notifications</h2>
          <p class="muted small">
            {{ unreadCount > 0 ? `${unreadCount} non lue${unreadCount > 1 ? 's' : ''}` : 'Tout est à jour' }}.
          </p>
        </div>
        <button class="btn btn-outline" :disabled="markingAll || unreadCount === 0" @click="markAllRead()">
          {{ markingAll ? '…' : 'Tout marquer comme lu' }}
        </button>
      </div>

      <span v-if="actionError" class="error-msg block mb-16">{{ actionError }}</span>

      <!-- Erreur initiale -->
      <div v-if="error && !data" class="card">
        <h3>Impossible de charger les notifications</h3>
        <p class="muted small mt-8">{{ apiError(error, 'Erreur de chargement.') }}</p>
        <button class="btn btn-outline mt-16" @click="start()">Réessayer</button>
      </div>

      <!-- Squelettes -->
      <div v-else-if="loading && !data" class="flex-col">
        <div v-for="i in 5" :key="i" class="skeleton skel-notif"></div>
      </div>

      <!-- Liste -->
      <div v-else-if="notifications.length" class="flex-col">
        <button
          v-for="n in notifications"
          :key="n.id"
          class="card notif"
          :class="{ unread: !n.read_at }"
          @click="openNotification(n)"
        >
          <div class="notif-icon"><AppIcon :name="typeMeta(n.type).icon" :size="18" /></div>
          <div class="notif-body">
            <div class="flex-between wrap">
              <span class="bold small">{{ n.title || typeMeta(n.type).label }}</span>
              <span class="faint small">{{ timeAgo(n.created_at) }}</span>
            </div>
            <p v-if="n.body" class="small muted mt-8">{{ n.body }}</p>
            <span class="small faint mt-8 chip-type">{{ typeMeta(n.type).label }}</span>
          </div>
          <span v-if="!n.read_at" class="dot"></span>
        </button>

        <div v-if="meta?.next_page_url" class="mt-16 center">
          <button class="btn btn-outline" :disabled="loadingMore" @click="loadMore()">
            {{ loadingMore ? 'Chargement…' : 'Charger plus' }}
          </button>
        </div>
      </div>

      <!-- État vide -->
      <div v-else class="card card-soft empty">
        <p class="muted">Aucune notification.</p>
        <p class="faint small mt-8">Les nouvelles demandes, changements de statut et messages apparaîtront ici.</p>
      </div>
    </main>
  </div>
</template>

<style scoped>
.block { display: block; }
.skel-notif {
  height: 5.25rem;
}
.notif {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  text-align: left;
  cursor: pointer;
  border: 0.0625rem solid var(--border);
  transition: border-color 0.2s, background 0.2s;
  position: relative;
}
.notif:hover {
  border-color: var(--border-2);
  background: var(--surface-2);
}
.notif.unread {
  border-color: rgba(34, 197, 111, 0.35);
  background: var(--surface-2);
}
.notif-icon {
  /* Même couleur pour toutes les notifications : elles sont de même niveau. */
  color: var(--green);
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 0.625rem;
  width: 2.75rem;
  height: 2.75rem;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.notif-body {
  flex: 1;
  min-width: 0;
}
.chip-type {
  display: inline-block;
  background: var(--surface-2);
  border-radius: 62.4375rem;
  padding: 0.125rem 0.625rem;
  margin-top: 0.5rem;
}
.dot {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 50%;
  background: var(--green);
  flex-shrink: 0;
}
.empty {
  text-align: center;
  padding: 2.25rem 1.25rem;
}
.center { text-align: center; }
</style>
