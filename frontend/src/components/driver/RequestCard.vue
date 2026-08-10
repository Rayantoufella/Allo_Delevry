<script setup>
import AppIcon from "../AppIcon.vue"
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { STATUS, formatPrice, timeAgo } from '../../lib/statuses'
import { serviceIcon } from '../../lib/serviceIcons'
import StatusBadge from '../StatusBadge.vue'

/**
 * Carte récapitulative d'une demande de livraison (espace livreur).
 * Tolère les objets partiels (tableau de bord : id, tracking_number,
 * status, proposed_price, created_at uniquement).
 * Props :
 *  - request    : objet demande de livraison
 *  - arrow      : bouton compact "→" au lieu de "Gérer la mission"
 */
const props = defineProps({
  request: { type: Object, required: true },
  arrow: { type: Boolean, default: false },
})

const router = useRouter()

const isNew = computed(() => props.request.status === STATUS.EN_ATTENTE)
const amount = computed(() =>
  props.request.amount_to_collect ?? props.request.proposed_price ?? null,
)
/* La ligne porte l'icône de son service, comme dans le prototype : une liste
   de missions toutes marquées « colis » ne se parcourt pas du regard. */
const icon = computed(() => serviceIcon(props.request.service?.name))
const routeLine = computed(() => {
  const from = props.request.pickup_address
  const to = props.request.delivery_address
  if (from && to) return `${from} → ${to}`
  return from || to || ''
})

function go() {
  router.push({ name: 'driver-mission', params: { id: props.request.id } })
}
</script>

<template>
  <div class="req" :class="{ clickable: arrow }" @click="arrow && go()">
    <div class="req-icon">
      <AppIcon :name="icon" :size="20" />
    </div>

    <div class="req-main">
      <div class="req-line">
        <span class="req-title">{{ request.service?.name || request.recipient_name || 'Mission' }}</span>
        <StatusBadge :status="request.status" />
      </div>
      <div class="req-sub">
        <span v-if="request.pickup_address" class="req-stop">
          <svg style="width:0.75rem;height:0.75rem" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6" /></svg>
          <span>{{ request.pickup_address }}</span>
        </span>
        <span v-if="request.pickup_address && request.delivery_address" class="req-sep">→</span>
        <span v-if="request.delivery_address" class="req-stop req-stop-dest">
          <svg style="width:0.75rem;height:0.75rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11z" /><circle cx="12" cy="10" r="2.6" /></svg>
          <span>{{ request.delivery_address }}</span>
        </span>
      </div>
    </div>

    <div class="req-right">
      <div v-if="amount != null" class="req-price">{{ formatPrice(amount) }}</div>
      <div v-if="request.created_at" class="req-time">{{ timeAgo(request.created_at) }}</div>
    </div>

    <span v-if="arrow" class="req-arrow">→</span>

    <div v-if="!arrow" class="req-full">
      <p v-if="routeLine" class="req-route">{{ routeLine }}</p>
      <p v-if="request.recipient_name || request.recipient_phone" class="req-recipient">
        <AppIcon name="pin" :size="15" /> {{ request.recipient_name }}<template v-if="request.recipient_phone"> · {{ request.recipient_phone }}</template>
      </p>
      <button class="btn btn-outline" @click="go">Gérer la mission</button>
    </div>
  </div>
</template>

<style scoped>
.req {
  position: relative;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.875rem;
  padding: 1rem 1.125rem;
  border-radius: 1rem;
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  transition: border-color 0.2s, transform 0.2s;
}
.req.clickable { cursor: pointer; }
.req.clickable:hover { border-color: var(--border-2); }
.req.clickable:active { transform: scale(0.995); }

.req-icon {
  flex: 0 0 auto;
  width: 2.875rem;
  height: 2.875rem;
  border-radius: 0.8125rem;
  background: var(--surface-2);
  color: var(--green);
  display: flex;
  align-items: center;
  justify-content: center;
}

.req-main { flex: 1; min-width: 0; }
.req-line { display: flex; align-items: center; gap: 0.625rem; flex-wrap: wrap; }
.req-title { font-weight: 800; font-size: 0.9688rem; color: var(--fg); }

.req-sub {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-top: 0.1875rem;
}
.req-stop {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
  color: var(--fg-2);
  font-weight: 600;
}
.req-stop svg { flex-shrink: 0; color: var(--fg-3); }
.req-stop-dest svg { color: var(--green); }
.req-sep { color: var(--fg-3); font-weight: 800; font-size: 0.8125rem; }

.req-right {
  flex: 0 0 auto;
  text-align: right;
  margin-left: auto;
}
.req-price { font-weight: 800; font-size: 1.0625rem; color: var(--green); }
.req-time { font-size: 0.75rem; color: var(--fg-2); font-weight: 600; margin-top: 0.125rem; }

.req-arrow {
  flex: 0 0 auto;
  color: var(--fg-3);
  font-weight: 800;
  font-size: 0.9375rem;
  margin-left: 0.125rem;
}

.req-full { flex: 1 1 100%; margin-top: 0.25rem; }
.req-route { font-size: 0.8125rem; color: var(--fg-2); margin-bottom: 0.375rem; }
.req-recipient { font-size: 0.8125rem; color: var(--fg-2); margin-bottom: 0.75rem; }

/* Sur téléphone, prix et flèche volaient assez de largeur au bloc central
   pour que les adresses se cassent après deux ou trois mots. On donne la
   ligne entière au contenu et on descend le prix en dessous. */
@media (max-width: 560px) {
  .req {
    align-items: flex-start;
    gap: 0.625rem;
    padding: 0.875rem;
  }
  .req-icon {
    width: 2.375rem;
    height: 2.375rem;
    border-radius: 0.6875rem;
  }
  .req-main { flex: 1 1 calc(100% - 3rem); }
  .req-right {
    flex: 1 1 100%;
    margin-left: 0;
    text-align: left;
    display: flex;
    align-items: baseline;
    gap: 0.625rem;
  }
  .req-time { margin-top: 0; }
  /* La carte entière est cliquable : le chevron n'apporte rien ici. */
  .req-arrow { display: none; }
}
</style>
