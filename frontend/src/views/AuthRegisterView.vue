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

const title = computed(() =>
  props.role === 'driver' ? 'Créer un espace livreur' : 'Créer un compte client'
)
const loginName = computed(() => props.role === 'driver' ? 'login-driver' : 'login')

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
    if (props.role === 'driver') {
      router.push({ name: 'driver-profile' })
    } else {
      router.push({ name: 'landing' })
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
  <div class="register-wrap">
    <div class="card register-card">
      <h2>{{ title }}</h2>
      <p v-if="role === 'driver'" class="muted small mt-8">
        Après inscription, complétez votre profil livreur (nom de marque, services, zones).
      </p>
      <p v-else class="muted small mt-8">Rejoignez la plateforme de livraison à la demande</p>

      <form @submit.prevent="handleRegister" class="mt-24 flex-col">
        <div class="field">
          <label for="name">Nom complet</label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            class="input"
            :class="{ 'input-error': fieldError('name') }"
            placeholder="Votre nom"
            required
          />
          <p v-if="fieldError('name')" class="error-msg">{{ fieldError('name') }}</p>
        </div>

        <div class="field">
          <label for="email">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="input"
            :class="{ 'input-error': fieldError('email') }"
            placeholder="vous@exemple.com"
            required
            autocomplete="email"
          />
          <p v-if="fieldError('email')" class="error-msg">{{ fieldError('email') }}</p>
        </div>

        <div class="field">
          <label for="phone">Téléphone <span class="faint">(optionnel)</span></label>
          <input
            id="phone"
            v-model="form.phone"
            type="tel"
            class="input"
            :class="{ 'input-error': fieldError('phone') }"
            placeholder="+225 00 00 00 00"
          />
          <p v-if="fieldError('phone')" class="error-msg">{{ fieldError('phone') }}</p>
        </div>

        <div class="field">
          <label for="password">Mot de passe</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            class="input"
            :class="{ 'input-error': fieldError('password') }"
            placeholder="8 caractères minimum"
            required
            autocomplete="new-password"
          />
          <p v-if="fieldError('password')" class="error-msg">{{ fieldError('password') }}</p>
        </div>

        <div class="field">
          <label for="password_confirmation">Confirmer le mot de passe</label>
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            class="input"
            placeholder="Retapez le mot de passe"
            required
            autocomplete="new-password"
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
          <span v-else>Créer mon compte</span>
        </button>
      </form>

      <p class="small muted mt-16" style="text-align: center">
        Déjà inscrit ?
        <router-link :to="{ name: loginName }">Se connecter</router-link>
      </p>
    </div>
  </div>
</template>

<style scoped>
.register-wrap {
  display: flex;
  justify-content: center;
  padding-top: 32px;
}
.register-card {
  width: 100%;
  max-width: 460px;
  padding: 32px 28px;
}
</style>
