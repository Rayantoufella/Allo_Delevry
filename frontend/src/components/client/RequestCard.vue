<script setup>
import StatusBadge from '../StatusBadge.vue'
import { formatPrice, formatDateTime, timeAgo } from '../../lib/statuses'

defineProps({
  request: { type: Object, required: true },
})
</script>

<template>
  <router-link
    :to="{ name: 'request-detail', params: { id: request.id } }"
    class="card-request"
  >
    <div class="card-request-header">
      <span class="bold small">#{{ request.tracking_number }}</span>
      <div class="flex" style="gap: 0.5rem; align-items: center">
        <StatusBadge :status="request.status" />
        <span class="arrow-btn">→</span>
      </div>
    </div>

    <div class="card-request-addresses">
      <span class="small card-addr">{{ request.pickup_address }}</span>
      <span class="arrow faint">→</span>
      <span class="small card-addr">{{ request.delivery_address }}</span>
    </div>

    <div class="card-request-footer">
      <span v-if="request.recipient_name" class="small muted">
        {{ request.recipient_name }}
      </span>
      <span v-if="request.amount_to_collect" class="small bold">
        {{ formatPrice(request.amount_to_collect) }}
      </span>
    </div>

    <div class="card-request-meta">
      <span v-if="request.proposed_price" class="small muted">
        Prix : {{ formatPrice(request.proposed_price) }}
      </span>
      <span class="small faint">{{ timeAgo(request.created_at) }}</span>
    </div>
  </router-link>
</template>

<style scoped>
.card-request {
  display: block;
  text-decoration: none;
  color: inherit;
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  border-radius: 1rem;
  padding: 1.125rem;
  transition: border-color 0.2s, transform 0.15s;
}

.card-request:hover {
  border-color: var(--green);
  transform: translateY(-0.125rem);
  text-decoration: none;
}

.card-request-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
}

.card-request-addresses {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  white-space: nowrap;
  overflow: hidden;
}

.card-addr {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.arrow {
  font-size: 1rem;
  flex-shrink: 0;
}

.card-request-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 0.5rem;
}

.card-request-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 0.5rem;
}

.arrow-btn {
  color: var(--fg-3);
  font-size: 1rem;
  flex-shrink: 0;
}
</style>
