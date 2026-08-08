import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '../api/axios'

/**
 * Store d'authentification (Sanctum token + profil).
 * Persistance : localStorage (token + user), comme une SPA simple.
 */
export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('allo_token') || null)
  const user = ref(JSON.parse(localStorage.getItem('allo_user') || 'null'))
  const loading = ref(false)

  const isAuthenticated = computed(() => !!token.value)
  const isClient = computed(() => user.value?.role === 'client')
  const isDriver = computed(() => user.value?.role === 'driver')

  function setSession(newToken, newUser) {
    token.value = newToken
    user.value = newUser
    localStorage.setItem('allo_token', newToken)
    localStorage.setItem('allo_user', JSON.stringify(newUser))
  }

  function clearSession() {
    token.value = null
    user.value = null
    localStorage.removeItem('allo_token')
    localStorage.removeItem('allo_user')
  }

  async function login(email, password) {
    loading.value = true
    try {
      const { data } = await api.post('/login', { email, password })
      setSession(data.token, data.user)
      return data.user
    } finally {
      loading.value = false
    }
  }

  async function register(payload) {
    loading.value = true
    try {
      const { data } = await api.post('/register', payload)
      setSession(data.token, data.user)
      return data.user
    } finally {
      loading.value = false
    }
  }

  async function fetchMe() {
    const { data } = await api.get('/me')
    user.value = data
    localStorage.setItem('allo_user', JSON.stringify(data))
    return data
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
    loading,
    isAuthenticated,
    isClient,
    isDriver,
    login,
    register,
    fetchMe,
    logout,
    clearSession,
  }
})
