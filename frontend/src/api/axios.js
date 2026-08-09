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

/**
 * Le backend renvoie quatre formes de réponse selon l'endpoint :
 *   1. trait ApiResponse      -> { success, message, data: <charge> }
 *   2. `return new XxxResource` -> { data: <charge> }   (wrapping Laravel par défaut)
 *   3. `response()->json(new XxxResource)` -> <charge>  (à plat, pas de wrapping)
 *   4. `XxxResource::collection(...->paginate())` -> { data: [...], links, meta }
 *
 * On normalise ici pour que chaque appelant reçoive directement la charge utile,
 * sans avoir à deviner la forme. Seule la pagination (cas 4) garde son enveloppe,
 * puisque `meta` et `links` font partie de l'information utile.
 */
function isPlainObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
}

function unwrapEnvelope(response) {
  const body = response.data
  if (!isPlainObject(body)) return response

  // Cas 1 : enveloppe explicite du trait ApiResponse.
  if (typeof body.success === 'boolean' && 'data' in body) {
    response.data = body.data
    return response
  }

  // Cas 2 : wrapping Laravel d'une Resource unique. On le distingue de la
  // pagination par l'absence de `meta`/`links`, qui accompagnent toujours
  // une collection paginée.
  if ('data' in body && !('meta' in body) && !('links' in body)) {
    response.data = body.data
  }

  return response
}

// Interceptor : déballage de l'enveloppe + déconnexion automatique si le token expire (401).
api.interceptors.response.use(
  (response) => unwrapEnvelope(response),
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
