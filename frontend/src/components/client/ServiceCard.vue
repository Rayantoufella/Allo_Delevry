<script setup>
import { computed } from 'vue'
import { formatPrice } from '../../lib/statuses'
import { serviceIcon } from '../../lib/serviceIcons'
import AppIcon from '../AppIcon.vue'

const props = defineProps({
  service: { type: Object, required: true },
})

/* Le prototype donne une icône par service ; la carte affichait le même
   « colis » partout, ce qui rendait le catalogue illisible d'un coup d'œil. */
const icon = computed(() => serviceIcon(props.service.name))
</script>

<template>
  <div class="svc-card" :class="{ inactive: !service.is_active }">
    <div class="svc-icon">
      <AppIcon :name="icon" :size="20" />
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
  gap: 0.875rem;
  align-items: center;
  padding: 1rem;
  border-radius: 1rem;
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  transition: border-color 0.2s, transform 0.2s;
  cursor: pointer;
  position: relative;
}

.svc-card:hover {
  border-color: var(--green);
  transform: translateY(-0.1875rem);
}

.svc-card.inactive {
  opacity: 0.55;
  cursor: default;
}

.svc-icon {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 0.75rem;
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
  font-size: 0.9375rem;
}

.svc-desc {
  color: var(--fg-2);
  font-size: 0.7813rem;
  line-height: 1.35;
  margin-top: 0.125rem;
}

.svc-price {
  font-weight: 800;
  color: var(--green);
  font-size: 0.8125rem;
  white-space: nowrap;
  flex-shrink: 0;
}

.svc-price-val {
  /* inherits green from parent */
}

.svc-unavail {
  position: absolute;
  bottom: 0.25rem;
  left: 1rem;
  font-size: 0.72rem;
}
</style>
