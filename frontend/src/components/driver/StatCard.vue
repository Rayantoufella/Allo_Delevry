<script setup>
import AppIcon from '../AppIcon.vue'

/**
 * Carte statistique du tableau de bord livreur.
 *
 * L'icône est purement décorative : elle redouble le libellé déjà lu, donc
 * elle reste masquée aux lecteurs d'écran.
 *
 * Les quatre cartes sont de même rang : leurs valeurs partagent la même
 * couleur, comme dans le prototype. Les teinter chacune différemment (vert,
 * bleu, ambre) laissait croire à quatre natures d'indicateur et faisait du
 * bandeau de KPI la zone la plus bariolée de l'écran. Le seul accent est la
 * ligne d'évolution, en vert.
 *
 * Props :
 *  - label  : intitulé de l'indicateur
 *  - value  : valeur affichée (String|Number)
 *  - icon   : nom d'icône du jeu AppIcon
 *  - sub    : ligne d'évolution (optionnel)
 */
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: '—' },
  icon: { type: String, default: '' },
  sub: { type: String, default: '' },
})
</script>

<template>
  <div class="card stat">
    <div class="flex-between">
      <span class="label">{{ label }}</span>
      <span v-if="icon" class="icon-chip">
        <AppIcon :name="icon" :size="18" />
      </span>
    </div>
    <div class="value">{{ value }}</div>
    <div v-if="sub" class="sub">{{ sub }}</div>
  </div>
</template>

<style scoped>
.stat {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-height: 6.75rem;
  /* Autorise la carte à se réduire dans sa piste de grille. */
  min-width: 0;
}
.label {
  font-weight: 700;
  font-size: 0.78rem;
  color: var(--fg-2);
}
.value {
  /* Fluide : un montant long ne doit ni déborder ni forcer la colonne. */
  font-size: clamp(1.35rem, 1.1rem + 0.9vw, 1.75rem);
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.15;
  margin-top: 0.5rem;
  overflow-wrap: anywhere;
  color: var(--fg);
}
.sub {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--green);
}

/* Pastille d'icône du prototype : 2rem, surface-2, tracé vert. */
.icon-chip {
  flex: none;
  width: 2rem;
  height: 2rem;
  border-radius: 0.5625rem;
  background: var(--surface-2);
  color: var(--green);
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
