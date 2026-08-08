import axios from 'axios'

/**
 * Client axios central — API backend Allo Delivery (Sanctum Bearer).
 * Le proxy Vite (/api) évite tout problème de CORS en dev :
 *   frontend:5173 -> vite proxy -> nginx:8000 (backend)
 */
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: { Accept: 'application/json' },
})

// Interceptor : attache le token Sanctum à chaque requête.
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('allo_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Interceptor : déconnexion automatique si le token expire (401).
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const isAuthRoute = ['/login', '/register'].some((r) =>
        String(error.config?.url || '').includes(r),
      )
      if (!isAuthRoute) {
        localStorage.removeItem('allo_token')
        localStorage.removeItem('allo_user')
      }
    }
    return Promise.reject(error)
  },
)

export default api

/** Extrait le message d'erreur lisible d'une réponse API Laravel. */
export function apiError(error, fallback = 'Une erreur est survenue.') {
  const data = error?.response?.data
  if (data?.message) return data.message
  if (data?.errors) {
    const first = Object.values(data.errors)[0]
    if (Array.isArray(first)) return first[0]
    return String(first)
  }
  return fallback
}
