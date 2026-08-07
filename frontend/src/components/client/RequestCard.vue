<script setup>
import StatusBadge from '../StatusBadge.vue'
import { formatPrice, formatDateTime } from '../../lib/statuses'

defineProps({
  request: { type: Object, required: true },
})
</script>

<template>
  <router-link
    :to="{ name: 'request-detail', params: { id: request.id } }"
    class="card card-request"
  >
    <div class="flex-between mb-16">
      <span class="bold small">#{{ request.tracking_number }}</span>
      <StatusBadge :status="request.status" />
    </div>

    <div class="addresses">
      <div class="addr">
        <span class="faint small">Ramassage</span>
        <span class="small">{{ request.pickup_address }}</span>
      </div>
      <span class="arrow faint">→</span>
      <div class="addr">
        <span class="faint small">Livraison</span>
        <span class="small">{{ request.delivery_address }}</span>
      </div>
    </div>

    <div class="divider"></div>

    <div class="flex-between">
      <span v-if="request.recipient_name" class="small muted">
        Pour : {{ request.recipient_name }}
      </span>
      <span class="small faint">{{ formatDateTime(request.created_at) }}</span>
    </div>

    <div v-if="request.proposed_price || request.amount_to_collect" class="flex-between mt-8">
      <span v-if="request.amount_to_collect" class="small bold">
        À encaisser : {{ formatPrice(request.amount_to_collect) }}
      </span>
      <span v-if="request.proposed_price" class="small bold">
        Prix proposé : {{ formatPrice(request.proposed_price) }}
      </span>
    </div>
  </router-link>
</template>

<style scoped>
.card-request {
  display: block;
  text-decoration: none;
  color: inherit;
  transition: border-color 0.2s, transform 0.15s;
}
.card-request:hover {
  border-color: var(--brand);
  transform: translateY(-2px);
  text-decoration: none;
}

.addresses {
  display: flex;
  align-items: center;
  gap: 10px;
}

.addr {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.addr .small:last-child {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.arrow {
  font-size: 1.1rem;
  flex-shrink: 0;
}
</style>
