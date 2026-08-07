<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { STATUS, formatPrice, timeAgo } from '../../lib/statuses'
import StatusBadge from '../StatusBadge.vue'

/**
 * Carte récapitulative d'une demande de livraison (espace livreur).
 * Tolère les objets partiels (tableau de bord : id, tracking_number,
 * status, proposed_price, created_at uniquement).
 * Props :
 *  - request    : objet demande de livraison
 *  - showManage : afficher le bouton "Gérer la mission" (défaut true)
 */
const props = defineProps({
  request: { type: Object, required: true },
  showManage: { type: Boolean, default: true },
})

const router = useRouter()

const isNew = computed(() => props.request.status === STATUS.EN_ATTENTE)

function go() {
  router.push({ name: 'driver-mission', params: { id: props.request.id } })
}
</script>

<template>
  <div class="card req">
    <div class="flex-between wrap">
      <div class="flex wrap">
        <span class="tracking bold">{{ request.tracking_number || `#${request.id}` }}</span>
        <StatusBadge :status="request.status" />
        <span v-if="isNew" class="badge badge-yellow">Nouveau</span>
      </div>
      <span class="faint small">{{ timeAgo(request.created_at) }}</span>
    </div>

    <div v-if="request.pickup_address || request.delivery_address" class="route small mt-8">
      <span class="muted">{{ request.pickup_address || '—' }}</span>
      <span class="arrow">→</span>
      <span class="bold">{{ request.delivery_address || '—' }}</span>
    </div>

    <div class="flex wrap mt-8 chips">
      <span v-if="request.recipient_name" class="chip small muted">
        📍 {{ request.recipient_name }}<template v-if="request.recipient_phone"> · {{ request.recipient_phone }}</template>
      </span>
      <span v-if="request.amount_to_collect" class="chip small muted">
        À encaisser : <b class="text">{{ formatPrice(request.amount_to_collect) }}</b>
      </span>
      <span v-if="request.proposed_price" class="chip small muted">
        Prix proposé : <b class="text">{{ formatPrice(request.proposed_price) }}</b>
      </span>
    </div>

    <div v-if="showManage" class="mt-16">
      <button class="btn btn-outline" @click="go">Gérer la mission</button>
    </div>
  </div>
</template>

<style scoped>
.tracking {
  font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
  letter-spacing: 0.02em;
}
.route {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.route .arrow {
  color: var(--text-faint);
  font-weight: 800;
}
.chips {
  gap: 6px;
}
.chip {
  background: var(--card-soft);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 3px 10px;
}
.chip .text {
  color: var(--text);
}
</style>
