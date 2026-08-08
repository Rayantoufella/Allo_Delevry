<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { apiError } from '../api/axios'

const props = defineProps({
  role: { type: String, default: 'client' },
})

const router = useRouter()
const auth = useAuthStore()

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
const loginName = computed(() => isClient.value ? 'login' : 'login-driver')

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
    await auth.register(payload)
    if (isClient.value) {
      router.push({ name: 'my-requests' })
    } else {
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
        <div class="left-label">Espace client</div>
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
          <div v-if="!isClient" class="form-badge">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M13 2 4 14h7l-1 8 9-12h-7z"></path>
            </svg>
            Compte professionnel
          </div>
          <div class="form-title">
            {{ isClient ? 'Créer un compte client' : 'Créer un espace livreur' }}
          </div>
          <div class="form-subtitle">
            {{ isClient ? 'Rejoins la plateforme et commence à envoyer des colis.' : 'Crée ton compte pour gérer ton activité de livraison.' }}
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
            <router-link :to="{ name: loginName }">Se connecter</router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-split {
  flex: 1 1 0%;
  display: grid;
  grid-template-columns: 1.05fr 0.95fr;
  min-height: 0;
}

/* ===== LEFT PANEL ===== */
.login-left {
  position: relative;
  overflow: hidden;
  padding: 56px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.login-left--client {
  background: var(--green);
  color: var(--green-ink);
}

.login-left--driver {
  background: var(--fg);
  color: var(--bg);
}

.left-label {
  font-weight: 800;
  font-size: 20px;
}

.left-label--driver {
  color: var(--green);
}

.left-heading {
  font-size: clamp(30px, 3.4vw, 46px);
  font-weight: 800;
  line-height: 1.05;
  letter-spacing: -0.03em;
}

.left-desc {
  max-width: 38ch;
  font-size: 16px;
  opacity: 0.82;
  line-height: 1.5;
  margin-top: 18px;
  font-weight: 600;
}

.left-desc--driver {
  max-width: 40ch;
  opacity: 0.6;
}

.left-features {
  display: flex;
  gap: 26px;
  font-weight: 700;
  font-size: 13px;
  opacity: 0.9;
}

.left-stats {
  display: flex;
  gap: 12px;
}

.left-stat {
  flex: 1 1 0%;
  padding: 16px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.left-stat-value {
  font-size: 24px;
  font-weight: 800;
  color: var(--green);
}

.left-stat-label {
  font-size: 12px;
  opacity: 0.6;
  font-weight: 600;
}

.left-circle {
  position: absolute;
  right: -60px;
  bottom: -40px;
  width: 280px;
  height: 280px;
  border: 40px solid rgba(255, 255, 255, 0.12);
  border-radius: 50%;
  pointer-events: none;
}

/* ===== RIGHT PANEL ===== */
.login-right {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
  background: var(--bg);
}

.login-form-wrap {
  width: 100%;
  max-width: 380px;
}

.form-title {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.form-subtitle {
  color: var(--fg-2);
  margin: 6px 0 26px;
  font-weight: 500;
}

.form-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 20px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  font-size: 12px;
  font-weight: 700;
  color: var(--fg-2);
  margin-bottom: 20px;
}

.form-fields {
  display: flex;
  flex-direction: column;
}

.form-field {
  margin-bottom: 0;
}

.form-field label {
  display: block;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--fg-2);
  margin-bottom: 6px;
}

.faint-label {
  color: var(--fg-3);
  font-weight: 500;
}

.form-field input {
  width: 100%;
  padding: 13px 15px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 15px;
  margin-bottom: 16px;
  font-family: inherit;
  transition: border-color 0.2s;
}

.form-field input::placeholder {
  color: var(--fg-3);
}

.form-field input:focus {
  outline: none;
  border-color: var(--green) !important;
}

.form-field input.input-error {
  border-color: var(--red) !important;
}

.field-error {
  color: var(--red);
  font-size: 0.78rem;
  margin-top: -12px;
  margin-bottom: 12px;
}

.form-error {
  color: var(--red);
  font-size: 0.8rem;
  margin-bottom: 8px;
}

.form-submit {
  width: 100%;
  padding: 15px;
  border-radius: 13px;
  border: none;
  font-weight: 800;
  font-size: 15.5px;
  cursor: pointer;
  font-family: inherit;
  transition: transform 0.15s, box-shadow 0.15s, opacity 0.2s;
}

.form-submit:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.form-submit:active:not(:disabled) {
  transform: scale(0.98);
}

.form-submit--green {
  background: var(--green);
  color: var(--green-ink);
}

.form-submit--fg {
  background: var(--fg);
  color: var(--bg);
}

.form-footer {
  text-align: center;
  color: var(--fg-2);
  font-size: 14px;
  margin-top: 20px;
}

.form-footer a {
  color: var(--green);
  font-weight: 800;
  text-decoration: none;
  cursor: pointer;
}

.spinner {
  display: inline-block;
  width: 20px;
  height: 20px;
  border: 2.5px solid var(--border);
  border-top-color: var(--green);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 900px) {
  .login-split {
    grid-template-columns: 1fr;
  }
  .login-left {
    padding: 36px 28px;
    min-height: auto;
  }
  .login-right {
    padding: 32px 24px;
  }
}
</style>
