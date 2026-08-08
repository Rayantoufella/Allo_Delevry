<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { apiError } from '../api/axios'

const props = defineProps({
  role: { type: String, default: 'client' },
})

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const errorMsg = ref('')
const submitting = ref(false)

const isClient = computed(() => props.role === 'client')
const registerName = computed(() => isClient.value ? 'register' : 'register-driver')

async function handleLogin() {
  errorMsg.value = ''
  submitting.value = true
  try {
    await auth.login(email.value.trim(), password.value)
    const redirect = route.query.redirect
    if (redirect) {
      router.push(redirect)
    } else if (isClient.value) {
      router.push({ name: 'my-requests' })
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
            {{ isClient ? 'Connexion client' : 'Connexion livreur' }}
          </div>
          <div class="form-subtitle">
            {{ isClient ? 'Accède à tes demandes et au suivi de tes colis.' : 'Pilote ta marque, tes missions et tes revenus.' }}
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
              <router-link :to="{ name: registerName }">Créer un compte</router-link>
            </template>
            <template v-else>
              Nouveau livreur ?
              <router-link :to="{ name: registerName }">Créer un espace</router-link>
            </template>
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

/* Decorative circle (client only) */
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

/* ===== RIGHT PANEL (Form) ===== */
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

/* Form fields */
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
}

.form-field input::placeholder {
  color: var(--fg-3);
}

.form-field input:focus {
  outline: none;
  border-color: var(--green) !important;
}

/* Last input margin */
.form-field:nth-last-child(1) input {
  margin-bottom: 22px;
}

/* Error */
.form-error {
  color: var(--red);
  font-size: 0.8rem;
  margin-bottom: 8px;
}

/* Submit button */
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

/* Footer link */
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

/* Spinner */
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

/* Responsive: stack vertically on mobile */
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
