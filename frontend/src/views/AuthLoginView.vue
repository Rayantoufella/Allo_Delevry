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

const title = computed(() => props.role === 'driver' ? 'Espace livreur' : 'Espace client')
const registerName = computed(() => props.role === 'driver' ? 'register-driver' : 'register')

async function handleLogin() {
  errorMsg.value = ''
  submitting.value = true
  try {
    await auth.login(email.value.trim(), password.value)
    const redirect = route.query.redirect
    if (redirect) {
      router.push(redirect)
    } else if (props.role === 'driver') {
      router.push({ name: 'driver-dashboard' })
    } else {
      router.push({ name: 'my-requests' })
    }
  } catch (err) {
    errorMsg.value = apiError(err, 'Erreur de connexion.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="login-wrap">
    <div class="card login-card">
      <h2>{{ title }}</h2>
      <p class="muted small mt-8">Connectez-vous pour continuer</p>

      <form @submit.prevent="handleLogin" class="mt-24 flex-col">
        <div class="field">
          <label for="email">Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            class="input"
            placeholder="vous@exemple.com"
            required
            autocomplete="email"
          />
        </div>

        <div class="field">
          <label for="password">Mot de passe</label>
          <input
            id="password"
            v-model="password"
            type="password"
            class="input"
            placeholder="••••••••"
            required
            autocomplete="current-password"
          />
        </div>

        <p v-if="errorMsg" class="error-msg mt-8">{{ errorMsg }}</p>

        <button
          type="submit"
          class="btn btn-primary btn-lg"
          :disabled="submitting"
          style="width: 100%; margin-top: 12px"
        >
          <span v-if="submitting" class="spinner"></span>
          <span v-else>Se connecter</span>
        </button>
      </form>

      <p class="small muted mt-16" style="text-align: center">
        Pas encore de compte ?
        <router-link :to="{ name: registerName }">Créer un compte</router-link>
      </p>
    </div>
  </div>
</template>

<style scoped>
.login-wrap {
  display: flex;
  justify-content: center;
  padding-top: 48px;
}
.login-card {
  width: 100%;
  max-width: 420px;
  padding: 32px 28px;
}
</style>
