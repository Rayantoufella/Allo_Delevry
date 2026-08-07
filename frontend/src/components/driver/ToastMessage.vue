<script setup>
/**
 * Toast global (classe .toast du design system).
 * Le parent gère l'affichage et l'auto-masquage (timeout).
 */
defineProps({
  message: { type: String, default: '' },
})

defineEmits(['close'])
</script>

<template>
  <div v-if="message" class="toast" @click="$emit('close')">{{ message }}</div>
</template>
