<script setup>
import { computed } from 'vue'
import { statusBadgeClass, statusLabel } from '../lib/statuses'

const props = defineProps({
  status: { type: String, required: true },
})

const label = computed(() => statusLabel(props.status))
const cls = computed(() => statusBadgeClass(props.status))
</script>

<template>
  <span class="badge" :class="cls">{{ label }}</span>
</template>
