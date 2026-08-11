<script setup>
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()

/**
 * Un compte client est rattaché à un livreur : la carte « Je suis client »
 * mène donc à la page publique d'un livreur. On ouvre celui mémorisé
 * (`driverSlug`), sinon le livreur démo — la page est ainsi toujours
 * accessible depuis l'accueil.
 */
function goClient() {
  if (auth.isAuthenticated && auth.isClient) {
    router.push({ name: 'ai-assistant', params: { slug: auth.driverSlug || 'rayan-express' } })
    return
  }
  router.push({ name: 'driver-public', params: { slug: auth.driverSlug || 'rayan-express' } })
}

function goDriver() {
  if (auth.isAuthenticated && auth.user?.role === 'driver') {
    router.push({ name: 'driver-dashboard' })
  } else {
    router.push({ name: 'login-driver' })
  }
}
</script>

<template>
  <div class="landing">
    <!-- Background gradient overlay -->
    <div class="hero-bg"></div>

    <div class="hero-content">
      <!-- Badge -->
      <div class="hero-badge">
        <span class="badge-dot"></span>
        Plateforme de livraison entre clients &amp; livreurs-entrepreneurs
      </div>

      <!-- H1 -->
      <h1 class="hero-title">
        Chaque livreur, sa <span class="hero-accent">marque</span>. Chaque colis, un <span class="hero-accent">suivi</span>.
      </h1>

      <!-- Subtitle -->
      <p class="hero-sub">
        Demande assistée par IA, acceptation &amp; tarif par le livreur, suivi GPS en direct, chat privé, remise confirmée en un bouton et preuve de livraison — le workflow complet, sans intermédiaire.
      </p>

      <!-- DEUX CARTES CTA -->
      <div class="cta-grid">
        <!-- Client -->
        <div class="cta-card" @click="goClient">
          <div class="cta-icon-wrap cta-icon-green">
            <AppIcon name="user" :size="24" />
          </div>
          <div class="cta-title">Je suis client</div>
          <p class="cta-desc">
            Ouvre la page d'un livreur, décris ta demande à l'IA ou choisis un service, puis suis ton colis en direct.
          </p>
          <div class="cta-link">Demander une livraison →</div>
        </div>

        <!-- Driver -->
        <div class="cta-card cta-card-inverted" @click="goDriver">
          <div class="cta-icon-wrap cta-icon-white">
            <AppIcon name="bolt" :size="24" />
          </div>
          <div class="cta-title">Je suis livreur-entrepreneur</div>
          <p class="cta-desc cta-desc-inverted">
            Ta marque, ton catalogue, tes zones &amp; tarifs, tes missions et tes revenus dans un tableau de bord.
          </p>
          <div class="cta-link">Ouvrir mon espace →</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.landing {
  position: relative;
  flex: 1 1 0%;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

/* Background radial gradients */
.hero-bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(56.25rem 31.25rem at 80% -10%, color-mix(in srgb, var(--green) 26%, transparent), transparent 60%),
    radial-gradient(43.75rem 31.25rem at 5% 110%, color-mix(in srgb, var(--green) 14%, transparent), transparent 55%);
  pointer-events: none;
}

/* Colonne centrée à 1200px, comme le prototype : sans ce cap, le titre et le
   sous-titre s'étalent d'un bord à l'autre sur un écran large et la page perd
   la mesure de lecture qui tient le bloc ensemble. */
.hero-content {
  position: relative;
  flex: 1 1 0%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 1.25rem 2.5rem 3.75rem;
  max-width: 75rem;
  margin: 0 auto;
  width: 100%;
}

/* Badge pill */
.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5625rem;
  align-self: flex-start;
  padding: 0.4375rem 0.875rem;
  border-radius: 1.875rem;
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  font-size: 0.7813rem;
  font-weight: 700;
  color: var(--fg-2);
  margin-bottom: 1.375rem;
  animation: fadeUp 0.5s cubic-bezier(0.2, 0.7, 0.3, 1) both;
}

.badge-dot {
  width: 0.4375rem;
  height: 0.4375rem;
  border-radius: 50%;
  background: var(--green);
  animation: pulse 2s ease infinite;
}

/* H1 */
.hero-title {
  margin: 0;
  font-size: clamp(2.5rem, 6vw, 4.625rem);
  font-weight: 800;
  line-height: 0.98;
  letter-spacing: -0.035em;
  max-width: 16ch;
  animation: fadeUp 0.5s cubic-bezier(0.2, 0.7, 0.3, 1) both;
  animation-delay: 0.05s;
}

.hero-accent {
  color: var(--green);
}

/* Subtitle */
.hero-sub {
  max-width: 60ch;
  font-size: clamp(1rem, 1.6vw, 1.25rem);
  color: var(--fg-2);
  line-height: 1.5;
  margin: 1.375rem 0 2.625rem;
  font-weight: 500;
  animation: fadeUp 0.5s cubic-bezier(0.2, 0.7, 0.3, 1) both;
  animation-delay: 0.1s;
}

/* CTA Grid */
.cta-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr));
  gap: 1.375rem;
  max-width: 56.25rem;
  animation: fadeUp 0.5s cubic-bezier(0.2, 0.7, 0.3, 1) both;
  animation-delay: 0.15s;
}

/* CTA Card base */
.cta-card {
  cursor: pointer;
  position: relative;
  overflow: hidden;
  padding: 1.875rem;
  border-radius: 1.375rem;
  background: var(--surface);
  border: 0.0625rem solid var(--border);
  box-shadow: var(--shadow);
  transition: transform 0.25s, border-color 0.25s;
  display: flex;
  flex-direction: column;
}

.cta-card:hover {
  transform: translateY(-0.1875rem);
  border-color: var(--green);
}

/* CTA Card inverted (driver) */
.cta-card-inverted {
  background: var(--fg);
  color: var(--bg);
  border: 0.0625rem solid var(--fg);
}

.cta-card-inverted:hover {
  border-color: var(--green);
}

.cta-desc-inverted {
  color: var(--bg);
  opacity: 0.7;
}

/* Icon wrappers */
.cta-icon-wrap {
  width: 3.25rem;
  height: 3.25rem;
  border-radius: 0.9375rem;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.125rem;
}

.cta-icon-green {
  background: color-mix(in srgb, var(--green) 16%, transparent);
  color: var(--green);
}

.cta-icon-white {
  background: var(--green);
  color: var(--green-ink);
}

/* Card text */
.cta-title {
  font-size: 1.375rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.cta-desc {
  color: var(--fg-2);
  font-size: 0.9063rem;
  margin: 0.5rem 0 1.25rem;
  line-height: 1.5;
}

.cta-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--green);
  font-weight: 800;
  font-size: 0.875rem;
}

/* Animation */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(0.875rem); }
  to { opacity: 1; transform: none; }
}

@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(34, 197, 111, 0.55); }
  70% { box-shadow: 0 0 0 1rem rgba(34, 197, 111, 0); }
  100% { box-shadow: 0 0 0 0 rgba(34, 197, 111, 0); }
}

/* Responsive */
@media (max-width: 768px) {
  .hero-content {
    padding: 1.25rem 1.25rem 2.5rem;
  }
  .cta-grid {
    grid-template-columns: 1fr;
    max-width: 25rem;
  }
}
</style>
