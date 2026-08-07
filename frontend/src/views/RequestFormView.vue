<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { apiError } from '../api/axios'
import { formatPrice } from '../lib/statuses'

const route = useRoute()
const router = useRouter()
const slug = computed(() => route.params.slug)

const driver = ref(null)
const loadingDriver = ref(true)

const form = ref({
  service_id: null,
  delivery_zone_id: null,
  recipient_name: '',
  recipient_phone: '',
  pickup_address: '',
  delivery_address: '',
  package_description: '',
  product_amount: '',
  amount_to_collect: '',
  proposed_price: '',
  scheduled_at: '',
})

const errorMsg = ref('')
const fieldErrors = ref({})
const submitting = ref(false)

const activeServices = computed(() =>
  (driver.value?.services || []).filter(s => s.is_active)
)

const activeZones = computed(() =>
  (driver.value?.delivery_zones || []).filter(z => z.is_active)
)

onMounted(async () => {
  try {
    const { data } = await api.get(`/drivers/${slug.value}`)
    driver.value = data
  } catch {
    // silent
  } finally {
    loadingDriver.value = false
  }
})

async function handleSubmit() {
  errorMsg.value = ''
  fieldErrors.value = {}
  submitting.value = true
  try {
    const payload = {
      recipient_name: form.value.recipient_name.trim(),
      recipient_phone: form.value.recipient_phone.trim(),
      pickup_address: form.value.pickup_address.trim(),
      delivery_address: form.value.delivery_address.trim(),
    }
    if (form.value.service_id) payload.service_id = Number(form.value.service_id)
    if (form.value.delivery_zone_id) payload.delivery_zone_id = Number(form.value.delivery_zone_id)
    if (form.value.package_description.trim()) payload.package_description = form.value.package_description.trim()
    if (form.value.product_amount) payload.product_amount = Number(form.value.product_amount)
    if (form.value.amount_to_collect) payload.amount_to_collect = Number(form.value.amount_to_collect)
    if (form.value.proposed_price) payload.proposed_price = Number(form.value.proposed_price)
    if (form.value.scheduled_at) payload.scheduled_at = form.value.scheduled_at

    const { data } = await api.post(`/drivers/${slug.value}/delivery-requests`, payload)
    router.push({ name: 'request-detail', params: { id: data.id } })
  } catch (err) {
    const data = err?.response?.data
    if (data?.errors) {
      fieldErrors.value = data.errors
    }
    errorMsg.value = apiError(err, 'Erreur lors de la création de la demande.')
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
  <div class="form-page">
    <h2 class="mb-16">📦 Nouvelle demande de livraison</h2>

    <!-- Loading driver info -->
    <div v-if="loadingDriver" class="flex-col" style="gap: 12px">
      <div class="skeleton" style="height: 36px"></div>
      <div class="skeleton" style="height: 120px"></div>
    </div>

    <template v-else>
      <!-- Warning indispo -->
      <div v-if="driver && !driver.is_available" class="unavailable-banner mb-16">
        <span>⚠️</span>
        <span>Ce livreur est indisponible. Votre demande sera traitée dès qu'il sera de retour.</span>
      </div>

      <form @submit.prevent="handleSubmit" class="card flex-col">
        <!-- Service -->
        <div v-if="activeServices.length" class="field">
          <label for="svc">Service</label>
          <select id="svc" v-model="form.service_id" class="input">
            <option :value="null">— Choisir un service (optionnel) —</option>
            <option v-for="s in activeServices" :key="s.id" :value="s.id">
              {{ s.name }} — {{ formatPrice(s.base_price) }}
            </option>
          </select>
        </div>

        <!-- Zone -->
        <div v-if="activeZones.length" class="field">
          <label for="zone">Zone de livraison</label>
          <select id="zone" v-model="form.delivery_zone_id" class="input">
            <option :value="null">— Choisir une zone (optionnel) —</option>
            <option v-for="z in activeZones" :key="z.id" :value="z.id">
              {{ z.origin_zone }} → {{ z.destination_zone }}
              <template v-if="z.fixed_price"> — {{ formatPrice(z.fixed_price) }}</template>
            </option>
          </select>
        </div>

        <div class="divider"></div>

        <!-- Destinataire -->
        <div class="grid-2">
          <div class="field">
            <label for="r-name">Nom du destinataire *</label>
            <input
              id="r-name"
              v-model="form.recipient_name"
              class="input"
              :class="{ 'input-error': fieldError('recipient_name') }"
              required
              placeholder="Nom complet"
            />
            <p v-if="fieldError('recipient_name')" class="error-msg">{{ fieldError('recipient_name') }}</p>
          </div>
          <div class="field">
            <label for="r-phone">Téléphone du destinataire *</label>
            <input
              id="r-phone"
              v-model="form.recipient_phone"
              class="input"
              :class="{ 'input-error': fieldError('recipient_phone') }"
              required
              placeholder="+225 00 00 00 00"
            />
            <p v-if="fieldError('recipient_phone')" class="error-msg">{{ fieldError('recipient_phone') }}</p>
          </div>
        </div>

        <!-- Adresses -->
        <div class="field">
          <label for="pickup">Adresse de ramassage *</label>
          <input
            id="pickup"
            v-model="form.pickup_address"
            class="input"
            :class="{ 'input-error': fieldError('pickup_address') }"
            required
            placeholder="Ex: Rue des Palmiers, Cocody"
          />
          <p v-if="fieldError('pickup_address')" class="error-msg">{{ fieldError('pickup_address') }}</p>
        </div>

        <div class="field">
          <label for="delivery">Adresse de livraison *</label>
          <input
            id="delivery"
            v-model="form.delivery_address"
            class="input"
            :class="{ 'input-error': fieldError('delivery_address') }"
            required
            placeholder="Ex: Boulevard de Marseille, Yopougon"
          />
          <p v-if="fieldError('delivery_address')" class="error-msg">{{ fieldError('delivery_address') }}</p>
        </div>

        <!-- Description -->
        <div class="field">
          <label for="desc">Description du colis</label>
          <textarea
            id="desc"
            v-model="form.package_description"
            rows="3"
            placeholder="Décrivez le contenu du colis (optionnel)"
          ></textarea>
        </div>

        <!-- Montants -->
        <div class="grid-2">
          <div class="field">
            <label for="prod-amount">Montant du produit (FCFA)</label>
            <input
              id="prod-amount"
              v-model="form.product_amount"
              type="number"
              class="input"
              placeholder="0"
            />
          </div>
          <div class="field">
            <label for="collect">Montant à encaisser (FCFA)</label>
            <input
              id="collect"
              v-model="form.amount_to_collect"
              type="number"
              class="input"
              placeholder="0"
            />
          </div>
        </div>

        <!-- Prix proposé -->
        <div class="field">
          <label for="price">Votre prix proposé (FCFA)</label>
          <input
            id="price"
            v-model="form.proposed_price"
            type="number"
            class="input"
            placeholder="Optionnel — le livreur validera"
          />
        </div>

        <!-- Date -->
        <div class="field">
          <label for="sched">Date et heure souhaitées</label>
          <input
            id="sched"
            v-model="form.scheduled_at"
            type="datetime-local"
            class="input"
          />
        </div>

        <p v-if="errorMsg" class="error-msg mt-8">{{ errorMsg }}</p>

        <button
          type="submit"
          class="btn btn-primary btn-lg mt-16"
          :disabled="submitting || !form.recipient_name || !form.recipient_phone || !form.pickup_address || !form.delivery_address"
          style="width: 100%"
        >
          <span v-if="submitting" class="spinner"></span>
          <span v-else>Envoyer la demande</span>
        </button>
      </form>
    </template>
  </div>
</template>

<style scoped>
.form-page {
  max-width: 680px;
  margin: 0 auto;
}

.unavailable-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  background: rgba(248, 113, 113, 0.1);
  border: 1px solid rgba(248, 113, 113, 0.3);
  border-radius: var(--radius-sm);
  font-weight: 700;
  color: var(--danger);
  font-size: 0.9rem;
}
</style>
