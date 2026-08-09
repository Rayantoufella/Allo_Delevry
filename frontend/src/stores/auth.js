import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '../api/axios'

const TOKEN_KEY = 'allo_token'
const USER_KEY = 'allo_user'
const DRIVER_KEY = 'allo_driver'

function readJson(key) {
  try {
    return JSON.parse(localStorage.getItem(key) || 'null')
  } catch {
    return null
  }
}

/**
 * Store d'authentification (Sanctum token + profil).
 *
 * Un compte client est rattaché à un livreur : il s'inscrit et se connecte
 * toujours dans le contexte du livreur dont il a suivi le lien ou scanné le QR.
 * On mémorise donc ce livreur (`driver`) pour savoir où ramener le client à son
 * retour — sans lui, un client n'a aucune porte de connexion.
 */
export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem(TOKEN_KEY) || null)
  const user = ref(readJson(USER_KEY))
  const driver = ref(readJson(DRIVER_KEY))
  const loading = ref(false)

  const isAuthenticated = computed(() => !!token.value)
  const isClient = computed(() => user.value?.role === 'client')
  const isDriver = computed(() => user.value?.role === 'driver')

  /** Slug du livreur auquel le client est rattaché (null pour un livreur). */
  const driverSlug = computed(() => driver.value?.slug || null)

  function setSession(newToken, newUser, newDriver = null) {
    token.value = newToken
    user.value = newUser
    localStorage.setItem(TOKEN_KEY, newToken)
    localStorage.setItem(USER_KEY, JSON.stringify(newUser))
    setDriverContext(newDriver)
  }

  /** Mémorise le livreur d'origine du client (lien public ou QR code). */
  function setDriverContext(newDriver) {
    if (!newDriver?.slug) return
    driver.value = newDriver
    localStorage.setItem(DRIVER_KEY, JSON.stringify(newDriver))
  }

  function clearSession() {
    token.value = null
    user.value = null
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USER_KEY)
    // On garde volontairement le contexte livreur : après déconnexion, le
    // client doit pouvoir se reconnecter chez le même livreur.
  }

  /** Extrait `{ user, driver }` d'une réponse déjà déballée par l'interceptor. */
  function readPayload(payload) {
    const nextUser = payload?.user ?? payload
    return { user: nextUser, driver: payload?.driver ?? nextUser?.driver ?? null }
  }

  async function loginClient(slug, email, password) {
    loading.value = true
    try {
      const { data } = await api.post(`/drivers/${slug}/login`, { email, password })
      const payload = readPayload(data)
      setSession(data.token, payload.user, payload.driver)
      return payload.user
    } finally {
      loading.value = false
    }
  }

  async function loginDriver(email, password) {
    loading.value = true
    try {
      const { data } = await api.post('/login', { email, password })
      setSession(data.token, readPayload(data).user)
      return user.value
    } finally {
      loading.value = false
    }
  }

  async function registerClient(slug, payload) {
    loading.value = true
    try {
      const { data } = await api.post(`/drivers/${slug}/register`, payload)
      const parsed = readPayload(data)
      setSession(data.token, parsed.user, parsed.driver)
      return parsed.user
    } finally {
      loading.value = false
    }
  }

  async function registerDriver(payload) {
    loading.value = true
    try {
      const { data } = await api.post('/register', payload)
      setSession(data.token, readPayload(data).user)
      return user.value
    } finally {
      loading.value = false
    }
  }

  async function fetchMe() {
    const { data } = await api.get('/me')
    const parsed = readPayload(data)
    user.value = parsed.user
    localStorage.setItem(USER_KEY, JSON.stringify(parsed.user))
    setDriverContext(parsed.driver)
    return parsed.user
  }

  async function logout() {
    try {
      await api.post('/logout')
    } catch {
      // Même si le backend répond mal, on déconnecte localement.
    } finally {
      clearSession()
    }
  }

  return {
    token,
    user,
    driver,
    driverSlug,
    loading,
    isAuthenticated,
    isClient,
    isDriver,
    loginClient,
    loginDriver,
    registerClient,
    registerDriver,
    setDriverContext,
    fetchMe,
    logout,
    clearSession,
  }
})
