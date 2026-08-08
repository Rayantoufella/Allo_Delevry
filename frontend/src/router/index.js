import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  // -------- Public --------
  {
    path: '/',
    name: 'landing',
    component: () => import('../views/LandingView.vue'),
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/AuthLoginView.vue'),
    props: { role: 'client' },
  },
  {
    path: '/login/driver',
    name: 'login-driver',
    component: () => import('../views/AuthLoginView.vue'),
    props: { role: 'driver' },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('../views/AuthRegisterView.vue'),
    props: { role: 'client' },
  },
  {
    path: '/register/driver',
    name: 'register-driver',
    component: () => import('../views/AuthRegisterView.vue'),
    props: { role: 'driver' },
  },
  {
    path: '/drivers/:slug',
    name: 'driver-public',
    component: () => import('../views/DriverPublicView.vue'),
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
    path: '/my/requests',
    name: 'my-requests',
    component: () => import('../views/MyRequestsView.vue'),
    meta: { requiresAuth: true, role: 'client' },
  },
  {
    path: '/requests/:id',
    name: 'request-detail',
    component: () => import('../views/RequestDetailView.vue'),
    meta: { requiresAuth: true, role: 'client' },
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
    path: '/driver/notifications',
    name: 'driver-notifications',
    component: () => import('../views/NotificationsView.vue'),
    meta: { requiresAuth: true, role: 'driver' },
  },

  // -------- Fallback --------
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

/** Guard global : auth + rôle (client/driver). */
router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    // Les pages driver/request mènent au login adapté.
    if (to.meta.role === 'driver') {
      return { name: 'login-driver', query: { redirect: to.fullPath } }
    }
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.role && auth.isAuthenticated && auth.user?.role !== to.meta.role) {
    // Un driver ne visite pas l'espace client et inversement.
    if (auth.isDriver) return { name: 'driver-dashboard' }
    return { name: 'my-requests' }
  }

  // Déjà connecté → on évite de repasser par le login.
  if ((to.name === 'login' || to.name === 'login-driver') && auth.isAuthenticated) {
    return auth.isDriver ? { name: 'driver-dashboard' } : { name: 'my-requests' }
  }

  return true
})

export default router
