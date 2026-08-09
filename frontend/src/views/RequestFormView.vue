<script setup>
import AppIcon from "../components/AppIcon.vue"
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { apiError } from '../api/axios'
import { formatPrice } from '../lib/statuses'
import { serviceIcon } from '../lib/serviceIcons'

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
      <span class="form-tab form-tab--active"><AppIcon name="pen" :size="16" /> Remplissage manuel</span>
      <router-link
        :to="{ name: 'ai-assistant', params: { slug } }"
        class="form-tab"
      >
        <AppIcon name="sparkle" :size="16" /> Assistant IA
      </router-link>
    </div>

    <h2 style="font-size: 1.75rem; margin-top: 0.5rem">Vérifier la demande</h2>
    <p class="form-subtitle">
      Corrige si besoin, puis choisis la zone. Le tarif est calculé automatiquement.
    </p>

    <!-- Loading driver info -->
    <div v-if="loadingDriver" class="flex-col" style="gap: 0.75rem; margin-top: 1.5rem">
      <div class="skeleton" style="height: 2.25rem"></div>
      <div class="skeleton" style="height: 7.5rem"></div>
    </div>

    <template v-else>
      <!-- Warning indispo -->
      <div v-if="driver && !driver.is_available" class="unavailable-banner">
        <AppIcon name="warning" :size="18" />
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
              <AppIcon :name="serviceIcon(s.name)" :size="16" />
              <span>{{ s.name }} — {{ formatPrice(s.base_price) }}</span>
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
              <span class="payment-icon"><AppIcon name="cash" :size="20" /></span>
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
              <span class="payment-icon"><AppIcon name="bank" :size="20" /></span>
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
              <div class="zone-name">{{ zone.destination_zone || zone.origin_zone }}</div>
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

        <p v-if="errorMsg" class="error-msg" style="margin-top: 0.75rem">{{ errorMsg }}</p>

        <button
          type="submit"
          class="form-submit"
          :disabled="submitting || !form.recipient_name || !form.recipient_phone || !form.pickup_address || !form.delivery_address"
        >
          <span v-if="submitting" class="spinner spinner-ink"></span>
          <span v-else>Envoyer la demande →</span>
        </button>
      </form>
    </template>
  </div>
</template>

<style scoped>
/* 840px : largeur de l'écran « Vérifier la demande » dans le prototype. Le
   formulaire y tient sur deux colonnes ; à 680px les paires de champs se
   serraient au point de repasser sur une seule. */
.form-page {
  max-width: 52.5rem;
  margin: 0 auto;
  padding-bottom: 3rem;
}

/* Sticky segmented tabs */
.form-tabs {
  display: flex;
  gap: 0.25rem;
  padding: 0.25rem;
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 0.75rem;
  position: sticky;
  top: 4rem;
  z-index: 30;
  margin-bottom: 1.25rem;
}

.form-tab {
  padding: 0.4375rem 0.875rem;
  border-radius: 0.5625rem;
  border: none;
  font-weight: 800;
  font-size: 0.8125rem;
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
  font-size: 0.9063rem;
  margin-top: 0.5rem;
  max-width: 37.5rem;
  line-height: 1.55;
}

/* Unavailable banner */
.unavailable-banner {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.75rem 1.125rem;
  background: rgba(248, 113, 113, 0.1);
  border: 0.0625rem solid rgba(248, 113, 113, 0.3);
  border-radius: 0.625rem;
  margin-bottom: 1.25rem;
  font-weight: 700;
  color: var(--red);
  font-size: 0.9rem;
}

/* Form card */
.form-card {
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  border-radius: 1rem;
  padding: 1.5rem;
  margin-top: 1rem;
}

/* Fields */
.form-field {
  display: flex;
  flex-direction: column;
  margin-bottom: 1rem;
}

.form-field label {
  display: block;
  font-size: 0.7813rem;
  font-weight: 700;
  color: var(--fg-2);
  margin-bottom: 0.375rem;
}

.form-input {
  width: 100%;
  padding: 0.8125rem 0.9375rem;
  border-radius: 0.75rem;
  border: 0.0625rem solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 0.9375rem;
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
  padding: 0.8125rem 0.9375rem;
  border-radius: 0.75rem;
  border: 0.0625rem solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 0.9375rem;
  font-family: inherit;
  resize: vertical;
  min-height: 4.375rem;
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
  margin-top: 0.25rem;
}

.form-divider {
  height: 0.0625rem;
  background: var(--border);
  margin: 0.25rem 0 1rem;
}

/* Chips */
.chips-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

/* Pilule de service du prototype : un rectangle arrondi à 11px cerné d'un
   filet de 1.5px, pas une gélule. Le libellé est en `--fg` tant que rien n'est
   choisi — en gris, la liste des services se lisait comme désactivée. */
.chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4375rem;
  background: var(--surface);
  border: 0.0938rem solid var(--border);
  border-radius: 0.6875rem;
  color: var(--fg);
  font-size: 0.8125rem;
  font-weight: 700;
  padding: 0.5625rem 0.8125rem;
  cursor: pointer;
  font-family: inherit;
  transition: border-color 0.15s, background 0.15s, color 0.15s;
}

.chip:hover {
  border-color: var(--green);
}

.chip.active {
  background: color-mix(in srgb, var(--green) 14%, transparent);
  border-color: var(--green);
  color: var(--green);
}

/* Payment cards */
.payment-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.payment-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  border-radius: 0.875rem;
  background: var(--surface-2);
  border: 0.0938rem solid var(--border);
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
  font-size: 0.8125rem;
  font-weight: 800;
}

.payment-sub {
  font-size: 0.6875rem;
  color: var(--fg-2);
  margin-top: 0.125rem;
}

/* Zone cards */
.zones-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.625rem;
}

.zone-card {
  padding: 0.875rem 1rem;
  border-radius: 0.875rem;
  background: var(--surface-2);
  border: 0.0938rem solid var(--border);
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
  font-size: 0.8125rem;
  font-weight: 700;
}

.zone-price {
  font-size: 0.8125rem;
  font-weight: 800;
  color: var(--green);
  margin-top: 0.25rem;
}

/* Recap strip */
.recap-strip {
  background: var(--surface-2);
  border: 0.0625rem solid var(--border);
  border-radius: 0.875rem;
  padding: 1rem 1.125rem;
  margin-top: 0.5rem;
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
  height: 0.0625rem;
  background: var(--border);
  margin: 0.625rem 0;
}

/* Submit */
.form-submit {
  width: 100%;
  padding: 0.9375rem;
  border-radius: 0.8125rem;
  border: none;
  background: var(--green);
  color: var(--green-ink);
  font-weight: 800;
  font-size: 0.9688rem;
  cursor: pointer;
  font-family: inherit;
  margin-top: 1rem;
  transition: transform 0.15s, box-shadow 0.15s, opacity 0.2s;
}

.form-submit:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.form-submit:active:not(:disabled) {
  transform: scale(0.98);
}

/* Responsive */
@media (max-width: 600px) {
  .payment-grid,
  .zones-grid {
    grid-template-columns: 1fr;
  }
}
</style>
