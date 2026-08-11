<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api, { apiError } from '../api/axios'
import AppIcon from '../components/AppIcon.vue'
import BrandLockup from '../components/BrandLockup.vue'

const props = defineProps({
  role: { type: String, default: 'client' },
  slug: { type: String, default: '' },
})

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const errorMsg = ref('')
const submitting = ref(false)
const brand = ref(null)

const isClient = computed(() => props.role === 'client')

// Le client s'inscrit toujours chez le livreur dont il suit le lien : son
// inscription reste donc dans le même contexte.
const registerTo = computed(() =>
  isClient.value
    ? { name: 'register', params: { slug: props.slug } }
    : { name: 'register-driver' },
)

// Marque du livreur : le client doit voir chez qui il se connecte.
onMounted(async () => {
  if (!isClient.value || !props.slug) return
  try {
    const { data } = await api.get(`/drivers/${props.slug}`)
    brand.value = data
    auth.setDriverContext({
      slug: props.slug,
      brand_name: data?.brand_name,
      logo_path: data?.logo_path,
    })
  } catch {
    // La marque est un habillage : son absence ne doit pas bloquer la connexion.
  }
})

async function handleLogin() {
  errorMsg.value = ''
  submitting.value = true
  try {
    if (isClient.value) {
      await auth.loginClient(props.slug, email.value.trim(), password.value)
    } else {
      await auth.loginDriver(email.value.trim(), password.value)
    }
    const redirect = route.query.redirect
    if (redirect) {
      router.push(redirect)
    } else if (isClient.value) {
      router.push({ name: 'ai-assistant', params: { slug: props.slug } })
    } else {
      router.push({ name: 'driver-dashboard' })
    }
  } catch (err) {
    errorMsg.value = apiError(err, 'Erreur de connexion.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="login-split">
    <!-- ===== LEFT PANEL ===== -->
    <div class="login-left" :class="isClient ? 'login-left--client' : 'login-left--driver'">
      <!-- Client panel -->
      <template v-if="isClient">
        <div class="left-label">{{ brand?.brand_name || 'Espace client' }}</div>
        <div class="left-heading">
          Demande. Suivi.<br />Livré en confiance.
        </div>
        <p class="left-desc">
          Un lien, un QR code, et ton colis part. Suis le livreur sur la carte et discute en direct.
        </p>
        <div class="left-features">
          <div class="left-feature">Suivi GPS live</div>
          <div class="left-feature">Chat privé</div>
          <div class="left-feature">Remise confirmée</div>
        </div>
        <!-- Decorative circle -->
        <div class="left-circle"></div>
      </template>

      <!-- Driver panel -->
      <template v-else>
        <div class="left-label left-label--driver">Espace livreur</div>
        <div class="left-heading">
          Ta marque.<br />Tes règles.<br />Tes revenus.
        </div>
        <p class="left-desc left-desc--driver">
          Catalogue de services, zones &amp; tarifs, acceptation des demandes, suivi live et statistiques — tout centralisé.
        </p>
        <div class="left-stats">
          <div class="left-stat">
            <div class="left-stat-value">1 240</div>
            <div class="left-stat-label">livraisons</div>
          </div>
        </div>
      </template>
    </div>

    <!-- ===== RIGHT PANEL (Form) ===== -->
    <div class="login-right">
      <div class="login-form-wrap">
        <div class="au">
          <BrandLockup class="auth-lockup" />
          <div v-if="!isClient" class="form-badge">
            <AppIcon name="bolt" />
            Compte professionnel
          </div>
          <div class="form-title">
            {{ isClient ? 'Connexion client' : 'Connexion livreur' }}
          </div>
          <div class="form-subtitle">
            <template v-if="isClient">
              Votre compte est rattaché à
              <strong>{{ brand?.brand_name || 'ce livreur' }}</strong>.
            </template>
            <template v-else>Pilote ta marque, tes missions et tes revenus.</template>
          </div>

          <form @submit.prevent="handleLogin" class="form-fields">
            <div class="form-field">
              <label>{{ isClient ? 'Email' : 'Email professionnel' }}</label>
              <input
                v-model="email"
                type="email"
                :placeholder="isClient ? 'sara@email.com' : 'rayan@rayanexpress.ma'"
                required
                autocomplete="email"
              />
            </div>

            <div class="form-field">
              <label>Mot de passe</label>
              <input
                v-model="password"
                type="password"
                placeholder="••••••••"
                required
                autocomplete="current-password"
              />
            </div>

            <p v-if="errorMsg" class="form-error">{{ errorMsg }}</p>

            <button
              type="submit"
              class="form-submit"
              :class="isClient ? 'form-submit--green' : 'form-submit--fg'"
              :disabled="submitting"
            >
              <span v-if="submitting" class="spinner"></span>
              <span v-else>Se connecter</span>
            </button>
          </form>

          <div class="form-footer">
            <template v-if="isClient">
              Pas encore de compte ?
              <router-link :to="registerTo">Créer un compte</router-link>
            </template>
            <template v-else>
              Nouveau livreur ?
              <router-link :to="registerTo">Créer un espace</router-link>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
