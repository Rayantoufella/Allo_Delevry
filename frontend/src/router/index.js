import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  // -------- Public --------
  // `layout: 'full'` — voir App.vue : ces vues composent leur pleine page
  // (dégradés jusqu'aux bords, panneau scindé sur toute la hauteur) et ne
  // doivent pas être enfermées dans la colonne centrée de l'espace client.
  {
    path: '/',
    name: 'landing',
    component: () => import('../views/LandingView.vue'),
    meta: { layout: 'full' },
  },
  // L'auth livreur reste globale : c'est lui qui possède un espace sur la plateforme.
  {
    path: '/login/driver',
    name: 'login-driver',
    component: () => import('../views/AuthLoginView.vue'),
    props: { role: 'driver' },
    meta: { layout: 'full' },
  },
  {
    path: '/register/driver',
    name: 'register-driver',
    component: () => import('../views/AuthRegisterView.vue'),
    props: { role: 'driver' },
    meta: { layout: 'full' },
  },
  {
    path: '/drivers/:slug',
    name: 'driver-public',
    component: () => import('../views/DriverPublicView.vue'),
  },

  // L'auth client n'existe QUE dans le contexte d'un livreur : le client arrive
  // par le lien public ou le QR code du livreur, et son compte lui est rattaché.
  {
    path: '/drivers/:slug/login',
    name: 'login',
    component: () => import('../views/AuthLoginView.vue'),
    props: (route) => ({ role: 'client', slug: route.params.slug }),
    meta: { layout: 'full' },
  },
  {
    path: '/drivers/:slug/register',
    name: 'register',
    component: () => import('../views/AuthRegisterView.vue'),
    props: (route) => ({ role: 'client', slug: route.params.slug }),
    meta: { layout: 'full' },
  },
  {
    path: '/tracking/:privateToken',
    name: 'tracking',
    component: () => import('../views/TrackingView.vue'),
  },

  // -------- Espace client (auth: client) --------
  {
    path: '/drivers/:slug/ai',
    name: 'ai-assistant',
    component: () => import('../views/AiAssistantView.vue'),
    meta: { requiresAuth: true, role: 'client' },
  },
  {
    path: '/drivers/:slug/request',
    name: 'request-form',
    component: () => import('../views/RequestFormView.vue'),
    meta: { requiresAuth: true, role: 'client' },
  },
  {
    path: '/requests/:id',
    name: 'request-detail',
    component: () => import('../views/RequestDetailView.vue'),
    meta: { requiresAuth: true, role: 'client' },
  },

  // /my/requests a été supprimée : les anciens liens retombent sur le chat IA.
  {
    path: '/my/requests',
    redirect: () => clientHomeRoute(),
  },

  // -------- Espace livreur (auth: driver) --------
  {
    path: '/driver/dashboard',
    name: 'driver-dashboard',
    component: () => import('../views/DashboardView.vue'),
    meta: { requiresAuth: true, role: 'driver' },
  },
  {
    path: '/driver/requests',
    name: 'driver-requests',
    component: () => import('../views/RequestsListView.vue'),
    meta: { requiresAuth: true, role: 'driver' },
  },
  {
    path: '/driver/requests/:id',
    name: 'driver-mission',
    component: () => import('../views/MissionView.vue'),
    meta: { requiresAuth: true, role: 'driver' },
  },
  {
    path: '/driver/profile',
    name: 'driver-profile',
    component: () => import('../views/ProfileView.vue'),
    meta: { requiresAuth: true, role: 'driver' },
  },
  {
    path: '/driver/zones',
    name: 'driver-zones',
    component: () => import('../views/ZonesTarifsView.vue'),
    meta: { requiresAuth: true, role: 'driver' },
  },
  {
    path: '/driver/notifications',
    name: 'driver-notifications',
    component: () => import('../views/NotificationsView.vue'),
    meta: { requiresAuth: true, role: 'driver' },
  },

  // -------- Anciens liens client globaux (plus de porte d'entrée hors livreur) --------
  { path: '/login', redirect: () => rememberedClientAuth('login') },
  { path: '/register', redirect: () => rememberedClientAuth('register') },

  // -------- Fallback --------
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

/**
 * Ramène un ancien lien /login ou /register vers le livreur mémorisé.
 * On lit localStorage plutôt que le store : ces redirections sont évaluées
 * pendant la résolution de route, avant tout composant.
 */
function rememberedClientAuth(name) {
  try {
    const slug = JSON.parse(localStorage.getItem('allo_driver') || 'null')?.slug
    if (slug) return { name, params: { slug } }
  } catch {
    // contexte illisible : on retombe sur l'accueil
  }
  return { name: 'landing' }
}

const router = createRouter({
  history: createWebHistory(),
  routes,
})

/**
 * Où renvoyer un client non connecté : toujours vers le login de SON livreur.
 * Le slug vient de la route visée si elle en porte un (ex. /drivers/x/request),
 * sinon du livreur mémorisé. Sans aucun des deux, le client n'a pas de porte
 * d'entrée — c'est la conséquence assumée d'une auth rattachée au livreur.
 */
function clientLoginRoute(to, auth) {
  const slug = to.params.slug || auth.driverSlug
  if (!slug) return { name: 'landing' }
  return { name: 'login', params: { slug }, query: { redirect: to.fullPath } }
}

/**
 * Page d'accueil du client connecté : l'assistant IA de SON livreur.
 * Sans livreur connu, le client n'a pas d'espace — retour à l'accueil.
 */
function clientHomeRoute() {
  const auth = useAuthStore()
  if (auth.driverSlug) return { name: 'ai-assistant', params: { slug: auth.driverSlug } }
  return { name: 'landing' }
}

/** Guard global : auth + rôle (client/driver). */
router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    // Les pages driver mènent au login livreur, les pages client à celui du livreur concerné.
    if (to.meta.role === 'driver') {
      return { name: 'login-driver', query: { redirect: to.fullPath } }
    }
    return clientLoginRoute(to, auth)
  }

  if (to.meta.role && auth.isAuthenticated && auth.user?.role !== to.meta.role) {
    // Un driver ne visite pas l'espace client et inversement.
    if (auth.isDriver) return { name: 'driver-dashboard' }
    return clientHomeRoute()
  }

  // Un client rattaché au livreur A ne commande pas chez B. Le backend renvoie
  // déjà 403 ; on l'arrête avant l'appel pour éviter un écran d'erreur inutile.
  if (
    to.meta.role === 'client' &&
    auth.isClient &&
    to.params.slug &&
    auth.driverSlug &&
    to.params.slug !== auth.driverSlug
  ) {
    return clientHomeRoute()
  }

  // Déjà connecté → ni login ni inscription n'ont de sens.
  const AUTH_ROUTES = ['login', 'login-driver', 'register', 'register-driver']
  if (AUTH_ROUTES.includes(to.name) && auth.isAuthenticated) {
    return auth.isDriver ? { name: 'driver-dashboard' } : clientHomeRoute()
  }

  return true
})

export default router
