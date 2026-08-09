/**
 * Le lien public d'un livreur — sa porte d'entrée client.
 *
 * C'est par ce lien (ou son QR code) que le client arrive : son compte est
 * rattaché au livreur qui l'a amené. Le livreur doit donc pouvoir le copier
 * facilement, d'où sa présence à la fois sur le tableau de bord et dans
 * « Profil & marque ». Les deux écrans passent par ces fonctions plutôt que de
 * recomposer l'URL chacun de leur côté, où elles finiraient par diverger.
 */

/** URL réellement ouvrable, sur l'hôte courant. C'est elle que l'on copie. */
export function publicUrl(slug) {
  return slug ? `${window.location.origin}/drivers/${slug}` : ''
}

/** Forme courte, pour l'affichage seul — elle n'est pas cliquable. */
export function prettyLink(slug) {
  return slug ? `allo.delivery/r/${slug}` : ''
}

/** QR code servi par l'API, à scanner par le client. */
export function qrUrl(slug) {
  return slug ? `/api/drivers/${slug}/qr` : ''
}

/**
 * Copie le lien public dans le presse-papiers.
 *
 * @returns {Promise<string>} le message à afficher : confirmation si la copie a
 * abouti, sinon le lien lui-même — `navigator.clipboard` échoue hors contexte
 * sécurisé (HTTP) et sur un refus de permission, et le livreur doit alors
 * pouvoir sélectionner l'URL à la main plutôt que de rester sans rien.
 */
export async function copyPublicLink(slug) {
  const url = publicUrl(slug)
  if (!url) return ''
  try {
    await navigator.clipboard.writeText(url)
    return 'Lien copié dans le presse-papiers'
  } catch {
    return url
  }
}
