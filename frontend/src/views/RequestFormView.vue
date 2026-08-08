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
  amount_to_collect: '',
  scheduled_at: '',
})

const paymentMethod = ref('cash') // UI-only: 'cash' | 'rib'
const errorMsg = ref('')
const fieldErrors = ref({})
const submitting = ref(false)

const activeServices = computed(() =>
  (driver.value?.services || []).filter((s) => s.is_active)
)

const activeZones = computed(() =>
  (driver.value?.delivery_zones || []).filter((z) => z.is_active)
)

// Price calculation
const selectedZone = computed(() => {
  if (!form.value.delivery_zone_id || !activeZones.value.length) return null
  return activeZones.value.find((z) => z.id === Number(form.value.delivery_zone_id)) || null
})

const deliveryFee = computed(() => {
  return selectedZone.value?.fixed_price ? Number(selectedZone.value.fixed_price) : 0
})

const productAmount = computed(() => {
  return form.value.amount_to_collect ? Number(form.value.amount_to_collect) : 0
})

const totalToCollect = computed(() => {
  return productAmount.value + deliveryFee.value
})

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
    if (form.value.amount_to_collect) payload.amount_to_collect = Number(form.value.amount_to_collect)
    if (form.value.scheduled_at.trim()) payload.scheduled_at = form.value.scheduled_at.trim()

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
    <!-- Sticky segmented tabs -->
    <div class="form-tabs">
      <span class="form-tab form-tab--active">✍️ Remplissage manuel</span>
      <router-link
        :to="{ name: 'ai-assistant', params: { slug } }"
        class="form-tab"
      >
        ✦ Assistant IA
      </router-link>
    </div>

    <h2 style="font-size: 1.75rem; margin-top: 8px">Vérifier la demande</h2>
    <p class="form-subtitle">
      Corrige si besoin, puis choisis la zone. Le tarif est calculé automatiquement.
    </p>

    <!-- Loading driver info -->
    <div v-if="loadingDriver" class="flex-col" style="gap: 12px; margin-top: 24px">
      <div class="skeleton" style="height: 36px"></div>
      <div class="skeleton" style="height: 120px"></div>
    </div>

    <template v-else>
      <!-- Warning indispo -->
      <div v-if="driver && !driver.is_available" class="unavailable-banner">
        <span>⚠️</span>
        <span>Ce livreur est indisponible. Votre demande sera traitée dès qu'il sera de retour.</span>
      </div>

      <form @submit.prevent="handleSubmit" class="form-card">
        <!-- Type de service — chips -->
        <div v-if="activeServices.length" class="form-field">
          <label>Type de service</label>
          <div class="chips-wrap">
            <button
              type="button"
              class="chip"
              :class="{ active: form.service_id === null }"
              @click="form.service_id = null"
            >
              Aucun
            </button>
            <button
              v-for="s in activeServices"
              :key="s.id"
              type="button"
              class="chip"
              :class="{ active: form.service_id === s.id }"
              @click="form.service_id = s.id"
            >
              {{ s.name }} — {{ formatPrice(s.base_price) }}
            </button>
          </div>
          <p v-if="fieldError('service_id')" class="field-error">{{ fieldError('service_id') }}</p>
        </div>

        <div class="form-divider"></div>

        <!-- Destinataire -->
        <div class="form-field">
          <label for="r-name">Destinataire</label>
          <input
            id="r-name"
            v-model="form.recipient_name"
            class="form-input"
            :class="{ 'input-error': fieldError('recipient_name') }"
            required
            placeholder="Nom du destinataire"
          />
          <p v-if="fieldError('recipient_name')" class="field-error">{{ fieldError('recipient_name') }}</p>
        </div>

        <!-- Téléphone -->
        <div class="form-field">
          <label for="r-phone">Téléphone</label>
          <input
            id="r-phone"
            v-model="form.recipient_phone"
            class="form-input"
            :class="{ 'input-error': fieldError('recipient_phone') }"
            required
            placeholder="+212 06 00 00 00"
          />
          <p v-if="fieldError('recipient_phone')" class="field-error">{{ fieldError('recipient_phone') }}</p>
        </div>

        <!-- Adresse de retrait -->
        <div class="form-field">
          <label for="pickup">Adresse de retrait</label>
          <input
            id="pickup"
            v-model="form.pickup_address"
            class="form-input"
            :class="{ 'input-error': fieldError('pickup_address') }"
            required
            placeholder="Ex : Avenue Hassan II, Agadir"
          />
          <p v-if="fieldError('pickup_address')" class="field-error">{{ fieldError('pickup_address') }}</p>
        </div>

        <!-- Adresse de livraison -->
        <div class="form-field">
          <label for="delivery">Adresse de livraison</label>
          <input
            id="delivery"
            v-model="form.delivery_address"
            class="form-input"
            :class="{ 'input-error': fieldError('delivery_address') }"
            required
            placeholder="Ex : Quartier Al Houda, Agadir"
          />
          <p v-if="fieldError('delivery_address')" class="field-error">{{ fieldError('delivery_address') }}</p>
        </div>

        <!-- Description du colis -->
        <div class="form-field">
          <label for="desc">Description du colis</label>
          <textarea
            id="desc"
            v-model="form.package_description"
            class="form-textarea"
            rows="3"
            placeholder="Décrivez le contenu du colis (optionnel)"
          ></textarea>
        </div>

        <!-- Montant à encaisser -->
        <div class="form-field">
          <label for="collect">Montant à encaisser (DH)</label>
          <input
            id="collect"
            v-model="form.amount_to_collect"
            type="number"
            class="form-input"
            placeholder="0"
            min="0"
          />
        </div>

        <!-- Créneau souhaité -->
        <div class="form-field">
          <label for="sched">Créneau souhaité</label>
          <input
            id="sched"
            v-model="form.scheduled_at"
            type="text"
            class="form-input"
            placeholder="Ex : demain avant 15:00"
          />
        </div>

        <div class="form-divider"></div>

        <!-- Méthode de paiement (UI-only) -->
        <div class="form-field">
          <label>Méthode de paiement</label>
          <div class="payment-grid">
            <div
              class="payment-card"
              :class="{ active: paymentMethod === 'cash' }"
              @click="paymentMethod = 'cash'"
            >
              <span class="payment-icon">💵</span>
              <div>
                <div class="payment-title">Espèces à la livraison</div>
                <div class="payment-sub">Paiement cash au livreur</div>
              </div>
            </div>
            <div
              class="payment-card"
              :class="{ active: paymentMethod === 'rib' }"
              @click="paymentMethod = 'rib'"
            >
              <span class="payment-icon">🏦</span>
              <div>
                <div class="payment-title">Virement bancaire par RIB</div>
                <div class="payment-sub">Paiement par virement</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Localisation & zone -->
        <div v-if="activeZones.length" class="form-field">
          <label>Localisation &amp; zone</label>
          <div class="zones-grid">
            <div
              v-for="zone in activeZones"
              :key="zone.id"
              class="zone-card"
              :class="{ active: form.delivery_zone_id === zone.id }"
              @click="form.delivery_zone_id = zone.id"
            >
              <div class="zone-name">{{ zone.origin_zone }} → {{ zone.destination_zone }}</div>
              <div v-if="zone.fixed_price" class="zone-price">{{ formatPrice(zone.fixed_price) }}</div>
            </div>
          </div>
          <p v-if="fieldError('delivery_zone_id')" class="field-error">{{ fieldError('delivery_zone_id') }}</p>
        </div>

        <!-- Récap prix -->
        <div v-if="selectedZone" class="recap-strip">
          <div class="recap-row">
            <span class="recap-label">Produit</span>
            <span class="recap-value">{{ productAmount > 0 ? formatPrice(productAmount) : '0 DH' }}</span>
          </div>
          <div class="recap-row">
            <span class="recap-label">Frais de livraison</span>
            <span class="recap-value">{{ deliveryFee > 0 ? formatPrice(deliveryFee) : '—' }}</span>
          </div>
          <div class="recap-divider"></div>
          <div class="recap-row">
            <span class="recap-label" style="font-weight: 800; color: var(--fg)">Total à encaisser</span>
            <span class="recap-value" style="color: var(--green); font-size: 1.1rem">{{ formatPrice(totalToCollect) }}</span>
          </div>
        </div>

        <p v-if="errorMsg" class="error-msg" style="margin-top: 12px">{{ errorMsg }}</p>

        <button
          type="submit"
          class="form-submit"
          :disabled="submitting || !form.recipient_name || !form.recipient_phone || !form.pickup_address || !form.delivery_address"
        >
          <span v-if="submitting" class="spinner"></span>
          <span v-else>Envoyer la demande →</span>
        </button>
      </form>
    </template>
  </div>
</template>

<style scoped>
.form-page {
  max-width: 680px;
  margin: 0 auto;
  padding-bottom: 48px;
}

/* Sticky segmented tabs */
.form-tabs {
  display: flex;
  gap: 4px;
  padding: 4px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  position: sticky;
  top: 64px;
  z-index: 30;
  margin-bottom: 20px;
}

.form-tab {
  padding: 7px 14px;
  border-radius: 9px;
  border: none;
  font-weight: 800;
  font-size: 13px;
  cursor: pointer;
  font-family: inherit;
  text-decoration: none;
  color: var(--fg-2);
  background: transparent;
  transition: background 0.15s, color 0.15s;
}

.form-tab--active {
  background: var(--surface);
  color: var(--fg);
}

.form-tab:hover:not(.form-tab--active) {
  color: var(--fg);
}

.form-subtitle {
  color: var(--fg-2);
  font-size: 14.5px;
  margin-top: 8px;
  max-width: 600px;
  line-height: 1.55;
}

/* Unavailable banner */
.unavailable-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  background: rgba(248, 113, 113, 0.1);
  border: 1px solid rgba(248, 113, 113, 0.3);
  border-radius: 10px;
  margin-bottom: 20px;
  font-weight: 700;
  color: var(--red);
  font-size: 0.9rem;
}

/* Form card */
.form-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 24px;
  margin-top: 16px;
}

/* Fields */
.form-field {
  display: flex;
  flex-direction: column;
  margin-bottom: 16px;
}

.form-field label {
  display: block;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--fg-2);
  margin-bottom: 6px;
}

.form-input {
  width: 100%;
  padding: 13px 15px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 15px;
  font-family: inherit;
  transition: border-color 0.2s;
}

.form-input::placeholder {
  color: var(--fg-3);
}

.form-input:focus {
  outline: none;
  border-color: var(--green) !important;
}

.form-input.input-error {
  border-color: var(--red) !important;
}

.form-textarea {
  width: 100%;
  padding: 13px 15px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 15px;
  font-family: inherit;
  resize: vertical;
  min-height: 70px;
  line-height: 1.5;
  transition: border-color 0.2s;
}

.form-textarea:focus {
  outline: none;
  border-color: var(--green) !important;
}

.form-textarea::placeholder {
  color: var(--fg-3);
}

.field-error {
  color: var(--red);
  font-size: 0.78rem;
  margin-top: 4px;
}

.form-divider {
  height: 1px;
  background: var(--border);
  margin: 4px 0 16px;
}

/* Chips */
.chips-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.chip {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 20px;
  color: var(--fg-2);
  font-size: 13px;
  font-weight: 700;
  padding: 7px 16px;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.15s;
}

.chip:hover {
  border-color: var(--green);
  color: var(--fg);
}

.chip.active {
  background: rgba(34, 197, 111, 0.16);
  border-color: var(--green);
  color: var(--green);
}

/* Payment cards */
.payment-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.payment-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-radius: 14px;
  background: var(--surface-2);
  border: 1.5px solid var(--border);
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}

.payment-card:hover {
  border-color: var(--fg-2);
}

.payment-card.active {
  border-color: var(--green);
  background: color-mix(in srgb, var(--green) 8%, var(--surface));
}

.payment-icon {
  font-size: 1.4rem;
  flex-shrink: 0;
}

.payment-title {
  font-size: 13px;
  font-weight: 800;
}

.payment-sub {
  font-size: 11px;
  color: var(--fg-2);
  margin-top: 2px;
}

/* Zone cards */
.zones-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.zone-card {
  padding: 14px 16px;
  border-radius: 14px;
  background: var(--surface-2);
  border: 1.5px solid var(--border);
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}

.zone-card:hover {
  border-color: var(--fg-2);
}

.zone-card.active {
  border-color: var(--green);
  background: color-mix(in srgb, var(--green) 8%, var(--surface));
}

.zone-name {
  font-size: 13px;
  font-weight: 700;
}

.zone-price {
  font-size: 13px;
  font-weight: 800;
  color: var(--green);
  margin-top: 4px;
}

/* Recap strip */
.recap-strip {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px 18px;
  margin-top: 8px;
}

.recap-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.recap-label {
  font-size: 0.82rem;
  color: var(--fg-2);
  font-weight: 600;
}

.recap-value {
  font-size: 0.82rem;
  font-weight: 800;
}

.recap-divider {
  height: 1px;
  background: var(--border);
  margin: 10px 0;
}

/* Submit */
.form-submit {
  width: 100%;
  padding: 15px;
  border-radius: 13px;
  border: none;
  background: var(--green);
  color: var(--green-ink);
  font-weight: 800;
  font-size: 15.5px;
  cursor: pointer;
  font-family: inherit;
  margin-top: 16px;
  transition: transform 0.15s, box-shadow 0.15s, opacity 0.2s;
}

.form-submit:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.form-submit:active:not(:disabled) {
  transform: scale(0.98);
}

.spinner {
  display: inline-block;
  width: 20px;
  height: 20px;
  border: 2.5px solid rgba(4, 20, 11, 0.3);
  border-top-color: var(--green-ink);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 600px) {
  .payment-grid,
  .zones-grid {
    grid-template-columns: 1fr;
  }
}
</style>
