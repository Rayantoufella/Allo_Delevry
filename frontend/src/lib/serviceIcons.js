/**
 * Icône d'un service du catalogue livreur.
 *
 * Le prototype donne une icône propre à chacun de ses cinq services (colis,
 * documents, courses, repas, pharmacie). Côté application, le catalogue est
 * libre : le livreur nomme ses services comme il veut. On retrouve donc
 * l'icône par mots-clés sur le libellé, et on retombe sur `box` — l'icône
 * générique « colis » du prototype — quand rien ne correspond.
 *
 * L'ordre des entrées compte : la première règle qui matche gagne. « Courses
 * de pharmacie » doit sortir en pharmacie, pas en courses, d'où les termes
 * les plus spécifiques en premier.
 */
const RULES = [
  { icon: 'pill', words: ['pharmac', 'medicament', 'parapharmac', 'ordonnance'] },
  { icon: 'food', words: ['repas', 'plat', 'food', 'restaurant', 'resto', 'nourriture', 'traiteur'] },
  { icon: 'doc', words: ['document', 'pli', 'papier', 'contrat', 'courrier', 'dossier', 'administratif'] },
  { icon: 'cart', words: ['course', 'achat', 'shopping', 'marche', 'superette', 'epicerie'] },
  { icon: 'box', words: ['colis', 'paquet', 'envoi', 'carton'] },
]

/* Décompose puis retire les diacritiques : « médicament » doit matcher la
   règle écrite en ASCII « medicament ». */
function normalize(value) {
  return String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
}

/**
 * @param {string} name  Libellé du service (`service.name`).
 * @returns {string} Nom d'icône `AppIcon`.
 */
export function serviceIcon(name) {
  const haystack = normalize(name)
  const rule = RULES.find((r) => r.words.some((w) => haystack.includes(w)))
  return rule ? rule.icon : 'box'
}
