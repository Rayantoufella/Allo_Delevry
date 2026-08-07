<script setup>
import { computed } from 'vue'
import { STATUS_LABELS } from '../lib/statuses'

/**
 * Timeline de suivi d'une demande (historique des statuts).
 * Props :
 *  - history : [{ old_status, new_status, comment, created_at }]
 *  - current : statut courant (met en avant la dernière étape)
 */
const props = defineProps({
  history: { type: Array, default: () => [] },
  current: { type: String, default: '' },
})

// Timeline inversée : la plus récente en haut, l'état courant surligné.
const items = computed(() => [...props.history].reverse())

function displayLabel(status) {
  return STATUS_LABELS[status]?.label || status
}

function isCurrent(item) {
  return item.new_status === props.current
}
</script>

<template>
  <ol class="timeline">
    <li
      v-for="(item, i) in items"
      :key="i"
      class="timeline-item"
      :class="{ current: isCurrent(item) }"
    >
      <span class="dot" :class="{ done: true, live: isCurrent(item) }"></span>
      <div class="timeline-body">
        <div class="flex-between">
          <span class="bold small">
            {{ displayLabel(item.new_status) }}
            <template v-if="item.old_status && item.old_status !== item.new_status">
              <span class="faint">(depuis {{ displayLabel(item.old_status) }})</span>
            </template>
          </span>
          <span class="faint small">{{ item.created_at ? new Date(item.created_at).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '' }}</span>
        </div>
        <p v-if="item.comment" class="small muted mt-8">{{ item.comment }}</p>
      </div>
    </li>
    <li v-if="items.length === 0" class="timeline-item">
      <span class="dot"></span>
      <div class="timeline-body small muted">Aucun événement enregistré pour le moment.</div>
    </li>
  </ol>
</template>

<style scoped>
.timeline {
  list-style: none;
  padding: 0;
  margin: 0;
}
.timeline-item {
  display: flex;
  gap: 14px;
  padding-bottom: 18px;
  position: relative;
}
.timeline-item:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 7px;
  top: 18px;
  bottom: 0;
  width: 2px;
  background: var(--border-strong);
}
.dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid var(--border-strong);
  background: var(--card);
  flex-shrink: 0;
  margin-top: 2px;
}
.dot.done {
  border-color: var(--brand);
  background: var(--brand);
}
.dot.live {
  box-shadow: 0 0 0 4px var(--brand-soft);
}
.timeline-body {
  flex: 1;
}
.timeline-item.current .timeline-body {
  background: var(--card-soft);
  border-radius: 10px;
  padding: 10px 14px;
  margin-top: -6px;
}
</style>
