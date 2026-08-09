<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import api from '../api/axios'
import { usePolling } from '../composables/usePolling'
import { useAuthStore } from '../stores/auth'

/**
 * Panneau de chat d'une demande de livraison (partagé client/livreur).
 * Fonctionne par polling (pas de WebSocket frontend : voir composables/usePolling).
 *
 * Props :
 *  - deliveryRequestId : id de la demande (requis)
 *  - compact : mode compact (hauteur réduite) — défaut false
 */
const props = defineProps({
  deliveryRequestId: { type: [Number, String], required: true },
  compact: { type: Boolean, default: false },
})

const auth = useAuthStore()
const messages = ref([])
const content = ref('')
const sending = ref(false)
const sendError = ref('')
const listEl = ref(null)

const canSend = computed(() => auth.isAuthenticated)

const { data, start, stop } = usePolling(async () => {
  const { data } = await api.get('/chat-messages', {
    params: { delivery_request_id: props.deliveryRequestId },
  })
  return data.data ?? data
}, 3000)

watch(data, async (rows) => {
  if (!rows) return
  messages.value = rows
  await nextTick()
  scrollToBottom()
})

function scrollToBottom() {
  if (listEl.value) {
    listEl.value.scrollTop = listEl.value.scrollHeight
  }
}

async function send() {
  const text = content.value.trim()
  if (!text || !canSend.value) return
  sending.value = true
  sendError.value = ''
  try {
    await api.post('/chat-messages', {
      delivery_request_id: Number(props.deliveryRequestId),
      content: text,
    })
    content.value = ''
    await start() // refresh immédiat
  } catch (err) {
    sendError.value = err?.response?.data?.message || "Impossible d'envoyer le message."
  } finally {
    sending.value = false
  }
}

function onKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    send()
  }
}

start()
</script>

<template>
  <div class="chat" :class="{ compact }">
    <div ref="listEl" class="chat-list">
      <div v-if="!messages.length" class="chat-empty small muted">
        Aucun message — échangez avec votre interlocuteur ici.
      </div>
      <div
        v-for="m in messages"
        :key="m.id"
        class="chat-msg"
        :class="{ mine: auth.user?.id === m.sender_id, theirs: auth.user?.id !== m.sender_id }"
      >
        <div class="chat-bubble">
          <div class="chat-meta">
            <span class="bold small">{{ m.sender_name || 'Livreur' }}</span>
            <span class="faint small">{{ new Date(m.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}</span>
          </div>
          <p class="small">{{ m.content }}</p>
        </div>
      </div>
    </div>

    <div class="chat-input">
      <textarea
        v-model="content"
        :disabled="!canSend"
        :placeholder="canSend ? 'Écrire un message…' : 'Connectez-vous pour participer à la conversation'"
        rows="2"
        @keydown="onKeydown"
      ></textarea>
      <button class="btn btn-primary" :disabled="!canSend || sending || !content.trim()" @click="send">
        {{ sending ? '…' : 'Envoyer' }}
      </button>
    </div>
    <p v-if="sendError" class="error-msg">{{ sendError }}</p>
  </div>
</template>

<style scoped>
.chat {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}
.chat-list {
  max-height: 21.25rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  padding: 0.25rem;
}
.chat.compact .chat-list { max-height: 13.75rem; }
.chat-empty { text-align: center; padding: 1.125rem 0; }
.chat-msg { display: flex; }
.chat-msg.mine { justify-content: flex-end; }
.chat-msg.theirs { justify-content: flex-start; }
.chat-bubble {
  max-width: 78%;
  background: var(--card-soft);
  border: 0.0625rem solid var(--border);
  border-radius: 0.75rem;
  padding: 0.5rem 0.75rem;
}
.chat-msg.mine .chat-bubble {
  background: var(--brand-soft);
  border-color: transparent;
}
.chat-meta {
  display: flex;
  gap: 0.5rem;
  align-items: baseline;
  margin-bottom: 0.125rem;
}
.chat-input {
  display: flex;
  gap: 0.5rem;
  align-items: flex-end;
}
.chat-input textarea { flex: 1; }
.chat-input .btn { white-space: nowrap; }
</style>
