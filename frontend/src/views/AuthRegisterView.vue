<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api, { apiError } from '../api/axios'
import AppIcon from '../components/AppIcon.vue'
import BrandLockup from '../components/BrandLockup.vue'

const props = defineProps({
  role: { type: String, default: 'client' },
  slug: { type: String, default: '' },
})

const router = useRouter()
const auth = useAuthStore()
const brand = ref(null)

const form = ref({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
})

const errorMsg = ref('')
const fieldErrors = ref({})
const submitting = ref(false)

const isClient = computed(() => props.role === 'client')

const loginTo = computed(() =>
  isClient.value ? { name: 'login', params: { slug: props.slug } } : { name: 'login-driver' },
)

// Marque du livreur : le client doit voir chez qui il crée son compte.
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
    // La marque est un habillage : son absence ne doit pas bloquer l'inscription.
  }
})

async function handleRegister() {
  errorMsg.value = ''
  fieldErrors.value = {}
  submitting.value = true
  try {
    const payload = {
      name: form.value.name.trim(),
      email: form.value.email.trim(),
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
      role: props.role,
    }
    if (form.value.phone.trim()) {
      payload.phone = form.value.phone.trim()
    }
    if (isClient.value) {
      // Le rattachement au livreur vient du slug de l'URL, jamais du formulaire.
      await auth.registerClient(props.slug, payload)
      router.push({ name: 'ai-assistant', params: { slug: props.slug } })
    } else {
      await auth.registerDriver(payload)
      router.push({ name: 'driver-profile' })
    }
  } catch (err) {
    const data = err?.response?.data
    if (data?.errors) {
      fieldErrors.value = data.errors
    }
    errorMsg.value = apiError(err, "Erreur lors de l'inscription.")
  } finally {
    submitting.value = false
  }
}

function fieldError(field) {
  const errs = fieldErrors.value[field]
  return Array.isArray(errs) ? errs[0] : null
}
</script>

<template>
  <div class="login-split">
    <!-- ===== LEFT PANEL ===== -->
    <div class="login-left" :class="isClient ? 'login-left--client' : 'login-left--driver'">
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
          <div class="left-feature">Code de remise</div>
        </div>
        <div class="left-circle"></div>
      </template>
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
          <div class="left-stat">
            <div class="left-stat-value">4.9★</div>
            <div class="left-stat-label">note moyenne</div>
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
            {{ isClient ? 'Créer un compte client' : 'Créer un espace livreur' }}
          </div>
          <div class="form-subtitle">
            <template v-if="isClient">
              Votre compte sera rattaché à
              <strong>{{ brand?.brand_name || 'ce livreur' }}</strong>.
            </template>
            <template v-else>Crée ton compte pour gérer ton activité de livraison.</template>
          </div>

          <form @submit.prevent="handleRegister" class="form-fields">
            <div class="form-field">
              <label for="reg-name">Nom complet</label>
              <input
                id="reg-name"
                v-model="form.name"
                type="text"
                placeholder="Votre nom"
                :class="{ 'input-error': fieldError('name') }"
                required
              />
              <p v-if="fieldError('name')" class="field-error">{{ fieldError('name') }}</p>
            </div>

            <div class="form-field">
              <label for="reg-email">{{ isClient ? 'Email' : 'Email professionnel' }}</label>
              <input
                id="reg-email"
                v-model="form.email"
                type="email"
                :placeholder="isClient ? 'vous@exemple.com' : 'marque@exemple.ma'"
                :class="{ 'input-error': fieldError('email') }"
                required
                autocomplete="email"
              />
              <p v-if="fieldError('email')" class="field-error">{{ fieldError('email') }}</p>
            </div>

            <div class="form-field">
              <label for="reg-phone">Téléphone <span class="faint-label">(optionnel)</span></label>
              <input
                id="reg-phone"
                v-model="form.phone"
                type="tel"
                placeholder="+212 06 00 00 00"
                :class="{ 'input-error': fieldError('phone') }"
              />
              <p v-if="fieldError('phone')" class="field-error">{{ fieldError('phone') }}</p>
            </div>

            <div class="form-field">
              <label for="reg-password">Mot de passe</label>
              <input
                id="reg-password"
                v-model="form.password"
                type="password"
                placeholder="8 caractères minimum"
                :class="{ 'input-error': fieldError('password') }"
                required
                autocomplete="new-password"
              />
              <p v-if="fieldError('password')" class="field-error">{{ fieldError('password') }}</p>
            </div>

            <div class="form-field">
              <label for="reg-password-confirmation">Confirmer le mot de passe</label>
              <input
                id="reg-password-confirmation"
                v-model="form.password_confirmation"
                type="password"
                placeholder="Retapez le mot de passe"
                required
                autocomplete="new-password"
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
              <span v-else>Créer mon compte</span>
            </button>
          </form>

          <div class="form-footer">
            Déjà inscrit ?
            <router-link :to="loginTo">Se connecter</router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
