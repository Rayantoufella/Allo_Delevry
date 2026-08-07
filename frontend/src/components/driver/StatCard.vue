<script setup>
/**
 * Carte statistique du tableau de bord livreur.
 * Props :
 *  - label  : intitulé de l'indicateur
 *  - value  : valeur affichée (String|Number)
 *  - icon   : emoji décoratif
 *  - accent : couleur de la valeur ('' | green | yellow | blue | red)
 *  - sub    : ligne secondaire (optionnel)
 */
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: '—' },
  icon: { type: String, default: '' },
  accent: { type: String, default: '' },
  sub: { type: String, default: '' },
})
</script>

<template>
  <div class="card stat" :class="accent ? `accent-${accent}` : ''">
    <div class="flex-between">
      <span class="label small muted">{{ label }}</span>
      <span v-if="icon" class="icon">{{ icon }}</span>
    </div>
    <div class="value bold">{{ value }}</div>
    <div v-if="sub" class="sub small faint">{{ sub }}</div>
  </div>
</template>

<style scoped>
.stat {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-height: 108px;
}
.value {
  font-size: 1.7rem;
  letter-spacing: -0.02em;
  line-height: 1.15;
  overflow-wrap: anywhere;
}
.accent-yellow .value { color: var(--warning); }
.accent-green .value { color: var(--brand); }
.accent-blue .value { color: var(--info); }
.accent-red .value { color: var(--danger); }
.icon { font-size: 1.2rem; }
</style>
