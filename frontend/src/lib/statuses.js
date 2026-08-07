/**
 * Statuts de demande de livraison — contrat avec le backend
 * (App\Models\DeliveryRequest, constantes STATUS_*).
 */
export const STATUS = {
  EN_ATTENTE: 'en_attente',
  PRIX_PROPOSE: 'prix_propose',
  CONFIRMEE: 'confirmee',
  COLIS_RECUPERE: 'colis_recupere',
  EN_LIVRAISON: 'en_livraison',
  LIVREE: 'livree',
  REFUSEE: 'refusee',
  ECHEC: 'echec',
  ANNULEE: 'annulee',
}

export const TERMINAL_STATUSES = ['livree', 'refusee', 'echec', 'annulee']

/** Labels français + couleur de badge pour l'UI. */
export const STATUS_LABELS = {
  [STATUS.EN_ATTENTE]: { label: 'En attente', color: 'badge-yellow', icon: '⏳' },
  [STATUS.PRIX_PROPOSE]: { label: 'Prix proposé', color: 'badge-blue', icon: '💰' },
  [STATUS.CONFIRMEE]: { label: 'Confirmée', color: 'badge-green', icon: '✅' },
  [STATUS.COLIS_RECUPERE]: { label: 'Colis récupéré', color: 'badge-green', icon: '📦' },
  [STATUS.EN_LIVRAISON]: { label: 'En livraison', color: 'badge-blue', icon: '🛵' },
  [STATUS.LIVREE]: { label: 'Livrée', color: 'badge-green', icon: '🏁' },
  [STATUS.REFUSEE]: { label: 'Refusée', color: 'badge-red', icon: '🚫' },
  [STATUS.ECHEC]: { label: 'Échec', color: 'badge-red', icon: '⚠️' },
  [STATUS.ANNULEE]: { label: 'Annulée', color: 'badge-red', icon: '❌' },
}

/** Ordre d'affichage dans la timeline de suivi. */
export const STATUS_ORDER = [
  STATUS.EN_ATTENTE,
  STATUS.PRIX_PROPOSE,
  STATUS.CONFIRMEE,
  STATUS.COLIS_RECUPERE,
  STATUS.EN_LIVRAISON,
  STATUS.LIVREE,
]

/** Types de preuve (DeliveryProof::TYPES). */
export const PROOF_TYPES = ['photo', 'signature', 'ticket', 'pickup_photo', 'pickup_id_card']

export const PROOF_LABELS = {
  photo: 'Photo',
  signature: 'Signature',
  ticket: 'Ticket',
  pickup_photo: 'Photo de récupération',
  pickup_id_card: "Carte d'identité (récupération)",
}

export function statusLabel(status) {
  return STATUS_LABELS[status]?.label || status
}

export function statusBadgeClass(status) {
  return STATUS_LABELS[status]?.color || 'badge'
}

export function formatPrice(value) {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  if (Number.isNaN(n)) return value
  return `${n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} FCFA`
}

export function formatDateTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function timeAgo(value) {
  if (!value) return '—'
  const d = new Date(value)
  const seconds = Math.floor((Date.now() - d.getTime()) / 1000)
  if (seconds < 60) return "à l'instant"
  if (seconds < 3600) return `il y a ${Math.floor(seconds / 60)} min`
  if (seconds < 86400) return `il y a ${Math.floor(seconds / 3600)} h`
  return formatDateTime(value)
}
