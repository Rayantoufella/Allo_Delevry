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

/** Labels français + couleur de badge pour l'UI (style prototype).
 *
 * Les couleurs suivent la table `STATUS` du prototype et se lisent comme une
 * progression : ambre tant que rien n'est engagé, bleu une fois le prix posé
 * et la demande confirmée, violet pendant la détention du colis, vert dès que
 * la course roule et jusqu'à la livraison. Rouge pour les échecs, gris pour une
 * annulation — qui n'est pas une erreur et ne doit pas alarmer comme telle.
 *
 * `icon` est un nom d'`AppIcon`, plus un emoji : les emojis se rendaient avec
 * la police du système, à une taille et une couleur que le thème ne contrôlait
 * pas, au milieu d'une UI entièrement en icônes ligne.
 */
export const STATUS_LABELS = {
  [STATUS.EN_ATTENTE]: { label: 'En attente', color: 'badge-yellow', icon: 'clock' },
  [STATUS.PRIX_PROPOSE]: { label: 'Prix proposé', color: 'badge-blue', icon: 'cash' },
  [STATUS.CONFIRMEE]: { label: 'Confirmée', color: 'badge-blue', icon: 'check' },
  [STATUS.COLIS_RECUPERE]: { label: 'Colis récupéré', color: 'badge-violet', icon: 'box' },
  [STATUS.EN_LIVRAISON]: { label: 'En livraison', color: 'badge-green', icon: 'truck' },
  [STATUS.LIVREE]: { label: 'Livrée', color: 'badge-green', icon: 'flag' },
  [STATUS.REFUSEE]: { label: 'Refusée', color: 'badge-red', icon: 'ban' },
  [STATUS.ECHEC]: { label: 'Échec', color: 'badge-red', icon: 'warning' },
  [STATUS.ANNULEE]: { label: 'Annulée', color: 'badge-grey', icon: 'close' },
}

/**
 * Rang d'un statut dans la progression d'une livraison — table `STATUS.step`
 * du prototype. Sert à savoir quelles étapes du suivi sont franchies.
 * Les issues négatives (refus, incident, annulation) sortent de la progression
 * et valent -1 : aucune étape n'est cochée derrière elles.
 */
export const STATUS_STEP = {
  [STATUS.EN_ATTENTE]: 0,
  [STATUS.PRIX_PROPOSE]: 1,
  [STATUS.CONFIRMEE]: 2,
  [STATUS.COLIS_RECUPERE]: 3,
  [STATUS.EN_LIVRAISON]: 4,
  [STATUS.LIVREE]: 5,
  [STATUS.REFUSEE]: -1,
  [STATUS.ECHEC]: -1,
  [STATUS.ANNULEE]: -1,
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
  return `${n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} DH`
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
