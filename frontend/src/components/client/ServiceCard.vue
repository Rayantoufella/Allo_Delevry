<script setup>
import { formatPrice } from '../../lib/statuses'

defineProps({
  service: { type: Object, required: true },
})
</script>

<template>
  <div class="svc-card" :class="{ inactive: !service.is_active }">
    <div class="svc-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3.3 7.5 12 3l8.7 4.5v9L12 21l-8.7-4.5z"></path>
        <path d="M3.3 7.5 12 12l8.7-4.5"></path>
        <path d="M12 12v9"></path>
      </svg>
    </div>
    <div class="svc-info">
      <div class="svc-name">{{ service.name }}</div>
      <div v-if="service.description" class="svc-desc">{{ service.description }}</div>
    </div>
    <div v-if="service.base_price" class="svc-price">
      dès <span class="svc-price-val">{{ formatPrice(service.base_price) }}</span>
    </div>
    <p v-if="!service.is_active" class="small faint svc-unavail">Service temporairement indisponible</p>
  </div>
</template>

<style scoped>
.svc-card {
  display: flex;
  gap: 14px;
  align-items: center;
  padding: 16px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--border);
  transition: border-color 0.2s, transform 0.2s;
  cursor: pointer;
  position: relative;
}

.svc-card:hover {
  border-color: var(--green);
  transform: translateY(-1px);
}

.svc-card.inactive {
  opacity: 0.55;
  cursor: default;
}

.svc-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--surface-2);
  color: var(--green);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.svc-info {
  flex: 1 1 0%;
  min-width: 0;
}

.svc-name {
  font-weight: 800;
  font-size: 15px;
}

.svc-desc {
  color: var(--fg-2);
  font-size: 12.5px;
  line-height: 1.35;
  margin-top: 2px;
}

.svc-price {
  font-weight: 800;
  color: var(--green);
  font-size: 13px;
  white-space: nowrap;
  flex-shrink: 0;
}

.svc-price-val {
  /* inherits green from parent */
}

.svc-unavail {
  position: absolute;
  bottom: 4px;
  left: 16px;
  font-size: 0.72rem;
}
</style>
