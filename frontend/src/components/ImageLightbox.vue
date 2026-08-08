<script setup>
import { onBeforeUnmount, onMounted } from 'vue'

/**
 * Lightbox plein écran pour agrandir une image (preuve, photo…).
 * Conforme au prototype : fond sombre, téléchargement, fermeture au clic ou Échap.
 */
const props = defineProps({
  src: { type: String, required: true },
  alt: { type: String, default: 'Image' },
})

const emit = defineEmits(['close'])

function onKeydown(e) {
  if (e.key === 'Escape') emit('close')
}

function download() {
  const a = document.createElement('a')
  a.href = props.src
  a.download = ''
  a.target = '_blank'
  a.rel = 'noopener'
  document.body.appendChild(a)
  a.click()
  a.remove()
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div class="lightbox" @click="emit('close')">
    <div class="lightbox-inner" @click.stop>
      <img :src="src" :alt="alt" />
      <div class="lightbox-actions">
        <button class="btn btn-primary" @click="download">⬇ Télécharger</button>
        <button class="btn lightbox-close" @click="emit('close')">Fermer</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.lightbox {
  position: fixed;
  inset: 0;
  z-index: 95;
  background: rgba(0, 0, 0, 0.82);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.lightbox-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  max-width: min(92vw, 900px);
}
.lightbox-inner img {
  max-width: 100%;
  max-height: 76vh;
  border-radius: 14px;
  box-shadow: 0 24px 60px -20px rgba(0, 0, 0, 0.7);
}
.lightbox-actions {
  display: flex;
  gap: 10px;
}
.lightbox-close {
  border: 1px solid rgba(255, 255, 255, 0.25);
  background: transparent;
  color: #fff;
  font-weight: 800;
}
</style>
