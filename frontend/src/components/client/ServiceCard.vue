<script setup>
import { formatPrice } from '../../lib/statuses'

defineProps({
  service: { type: Object, required: true },
})
</script>

<template>
  <div class="card card-service" :class="{ inactive: !service.is_active }">
    <div class="flex-between mb-16">
      <h4>{{ service.name }}</h4>
      <span class="badge badge-green" v-if="service.base_price">{{ formatPrice(service.base_price) }}</span>
    </div>
    <p v-if="service.description" class="small muted">{{ service.description }}</p>
    <p v-if="!service.is_active" class="small faint mt-8">Service temporairement indisponible</p>
  </div>
</template>

<style scoped>
.card-service { transition: border-color 0.2s; }
.card-service:hover { border-color: var(--brand); }
.card-service.inactive { opacity: 0.55; }
</style>
