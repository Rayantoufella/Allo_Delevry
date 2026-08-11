<script setup>
import { computed } from 'vue'
import { STATUS, STATUS_LABELS, STATUS_STEP, statusLabel } from '../lib/statuses'

/**
 * Deux traitements du prototype, un seul composant :
 *
 *  - `variant="steps"` — « Étapes de la livraison » (côté client). Les cinq
 *    étapes canoniques sont **toujours** affichées, cochées ou non. Le client
 *    doit voir ce qu'il reste à venir ; n'afficher que l'historique lui donnait
 *    une liste qui s'allonge sans jamais annoncer la suite.
 *  - `variant="history"` — « Historique des statuts » (côté livreur) : le
 *    journal brut, une pastille à la couleur du statut, pas de rail.
 *
 * Props :
 *  - history : [{ old_status, new_status, comment, created_at }]
 *  - current : statut courant
 */
const props = defineProps({
  history: { type: Array, default: () => [] },
  current: { type: String, default: '' },
  variant: {
    type: String,
    default: 'steps',
    validator: (v) => ['steps', 'history'].includes(v),
  },
})

/* Les jalons montrés au client. « Livreur arrivé » et « Colis livré » sont
   confirmés par le livreur lui-même (tous les boutons de statut sont côté
   livreur ; la remise respecte RG06). */
const STEPS = [
  { key: STATUS.CONFIRMEE, label: 'Demande confirmée', step: 2 },
  { key: STATUS.COLIS_RECUPERE, label: 'Colis récupéré', step: 3 },
  { key: STATUS.EN_LIVRAISON, label: 'En route vers vous', step: 4 },
  { key: STATUS.LIVREUR_ARRIVE, label: 'Livreur arrivé', step: 5 },
  { key: STATUS.LIVREE, label: 'Colis livré', step: 6 },
]

function formatTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const currentStep = computed(() => STATUS_STEP[props.current] ?? 0)

const steps = computed(() =>
  STEPS.map((s, i) => {
    const entry = props.history.find((h) => h.new_status === s.key)
    const done = currentStep.value >= s.step
    return {
      key: `${s.key}-${i}`,
      label: s.label,
      // L'étape en cours est la première non franchie qui suit immédiatement.
      active: !done && currentStep.value + 1 >= s.step,
      done,
      time: done ? formatTime(entry?.created_at) : '—',
      last: i === STEPS.length - 1,
    }
  }),
)

/* Journal : le plus récent en haut. */
const entries = computed(() =>
  [...props.history].reverse().map((item, i) => ({
    key: i,
    label: statusLabel(item.new_status),
    from:
      item.old_status && item.old_status !== item.new_status
        ? statusLabel(item.old_status)
        : '',
    comment: item.comment,
    time: formatTime(item.created_at),
    tone: STATUS_LABELS[item.new_status]?.color || 'badge-grey',
  })),
)
</script>

<template>
  <!-- Étapes de la livraison -->
  <ol v-if="variant === 'steps'" class="steps">
    <li v-for="s in steps" :key="s.key" class="step">
      <div class="rail">
        <span class="dot" :class="{ done: s.done, active: s.active }">
          <svg v-if="s.done" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 12.5 9 17.5 20 6.5" />
          </svg>
        </span>
        <span v-if="!s.last" class="line" :class="{ done: s.done }"></span>
      </div>
      <div class="step-body">
        <div class="step-label" :class="{ reached: s.done || s.active }">{{ s.label }}</div>
        <div class="step-time">{{ s.time }}</div>
      </div>
    </li>
  </ol>

  <!-- Historique des statuts -->
  <ol v-else class="history">
    <li v-for="e in entries" :key="e.key" class="entry">
      <span class="pip" :class="e.tone"></span>
      <div>
        <div class="entry-label">
          {{ e.label }}
          <span v-if="e.from" class="entry-from">(depuis {{ e.from }})</span>
        </div>
        <div class="entry-time">{{ e.time }}</div>
        <p v-if="e.comment" class="entry-comment">{{ e.comment }}</p>
      </div>
    </li>
    <li v-if="entries.length === 0" class="entry">
      <span class="pip badge-grey"></span>
      <div class="entry-time">Aucun événement enregistré pour le moment.</div>
    </li>
  </ol>
</template>

<style scoped>
.steps,
.history {
  list-style: none;
  padding: 0;
  margin: 0;
}

/* ---------- Étapes ---------- */
.step {
  display: flex;
  gap: 0.75rem;
}

/* La colonne du rail s'étire sur toute la hauteur de l'étape : c'est le trait
   qui relie les pastilles, il doit donc absorber la hauteur du libellé. */
.rail {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.dot {
  width: 1.375rem;
  height: 1.375rem;
  border-radius: 50%;
  flex: none;
  display: grid;
  place-items: center;
  background: var(--surface-2);
  color: var(--fg-3);
  border: 0.125rem solid var(--surface-3);
}
.dot.active { border-color: var(--fg-2); }
.dot.done {
  background: var(--green);
  color: var(--green-ink);
  border-color: var(--green);
}
.dot svg {
  width: 0.75rem;
  height: 0.75rem;
}

.line {
  width: 0.125rem;
  flex: 1;
  min-height: 1.375rem;
  background: var(--border);
}
.line.done { background: var(--green); }

.step-body { padding-bottom: 0.875rem; }

.step-label {
  font-size: 0.84rem;
  font-weight: 600;
  color: var(--fg-3);
}
.step-label.reached {
  font-weight: 800;
  color: var(--fg);
}

.step-time {
  font-size: 0.72rem;
  color: var(--fg-3);
  font-weight: 600;
}

/* ---------- Historique ---------- */
.entry {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  padding-bottom: 0.75rem;
}

/* La pastille reprend le fond du badge de statut : `.badge-*` porte la paire
   fond + texte, on n'en garde ici que le fond. */
.pip {
  width: 0.5625rem;
  height: 0.5625rem;
  border-radius: 50%;
  margin-top: 0.3125rem;
  flex: none;
  background: currentColor;
}

.entry-label {
  font-weight: 700;
  font-size: 0.84rem;
  color: var(--fg);
}
.entry-from {
  color: var(--fg-3);
  font-weight: 600;
}
.entry-time {
  font-size: 0.72rem;
  color: var(--fg-3);
  font-weight: 600;
}
.entry-comment {
  font-size: 0.8rem;
  color: var(--fg-2);
  margin: 0.375rem 0 0;
}
</style>
