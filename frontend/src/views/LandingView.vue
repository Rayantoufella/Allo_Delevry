<script setup>
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

function goClient() {
  if (auth.isAuthenticated && auth.user?.role === 'client') {
    router.push({ name: 'my-requests' })
  } else if (auth.isAuthenticated && auth.user?.role === 'driver') {
    router.push({ name: 'my-requests' })
  } else {
    router.push({ name: 'login' })
  }
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
        Demande assistée par IA, acceptation &amp; tarif par le livreur, suivi GPS en direct, chat privé, code de remise sécurisé et preuve de livraison — le workflow complet, sans intermédiaire.
      </p>

      <!-- DEUX CARTES CTA -->
      <div class="cta-grid">
        <!-- Client -->
        <div class="cta-card" @click="goClient">
          <div class="cta-icon-wrap cta-icon-green">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"></path>
              <path d="M4 21a8 8 0 0 1 16 0"></path>
            </svg>
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
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M13 2 4 14h7l-1 8 9-12h-7z"></path>
            </svg>
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
    radial-gradient(900px 500px at 80% -10%, color-mix(in srgb, var(--green) 26%, transparent), transparent 60%),
    radial-gradient(700px 500px at 5% 110%, color-mix(in srgb, var(--green) 14%, transparent), transparent 55%);
  pointer-events: none;
}

.hero-content {
  position: relative;
  flex: 1 1 0%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 20px 40px 60px;
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
}

/* Badge pill */
.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  align-self: flex-start;
  padding: 7px 14px;
  border-radius: 30px;
  background: var(--surface);
  border: 1px solid var(--border);
  font-size: 12.5px;
  font-weight: 700;
  color: var(--fg-2);
  margin-bottom: 22px;
  animation: fadeUp 0.5s cubic-bezier(0.2, 0.7, 0.3, 1) both;
}

.badge-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--green);
  animation: pulse 2s ease infinite;
}

/* H1 */
.hero-title {
  margin: 0;
  font-size: clamp(40px, 6vw, 74px);
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
  font-size: clamp(16px, 1.6vw, 20px);
  color: var(--fg-2);
  line-height: 1.5;
  margin: 22px 0 42px;
  font-weight: 500;
  animation: fadeUp 0.5s cubic-bezier(0.2, 0.7, 0.3, 1) both;
  animation-delay: 0.1s;
}

/* CTA Grid */
.cta-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 22px;
  max-width: 900px;
  animation: fadeUp 0.5s cubic-bezier(0.2, 0.7, 0.3, 1) both;
  animation-delay: 0.15s;
}

/* CTA Card base */
.cta-card {
  cursor: pointer;
  position: relative;
  overflow: hidden;
  padding: 30px;
  border-radius: 22px;
  background: var(--surface);
  border: 1px solid var(--border);
  box-shadow: var(--shadow);
  transition: transform 0.25s, border-color 0.25s;
  display: flex;
  flex-direction: column;
}

.cta-card:hover {
  transform: translateY(-3px);
  border-color: var(--green);
}

/* CTA Card inverted (driver) */
.cta-card-inverted {
  background: var(--fg);
  color: var(--bg);
  border: 1px solid var(--fg);
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
  width: 52px;
  height: 52px;
  border-radius: 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 18px;
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
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.cta-desc {
  color: var(--fg-2);
  font-size: 14.5px;
  margin: 8px 0 20px;
  line-height: 1.5;
}

.cta-link {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--green);
  font-weight: 800;
  font-size: 14px;
}

/* Animation */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(14px); }
  to { opacity: 1; transform: none; }
}

@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(34, 197, 111, 0.55); }
  70% { box-shadow: 0 0 0 16px rgba(34, 197, 111, 0); }
  100% { box-shadow: 0 0 0 0 rgba(34, 197, 111, 0); }
}

/* Responsive */
@media (max-width: 768px) {
  .hero-content {
    padding: 20px 20px 40px;
  }
  .cta-grid {
    grid-template-columns: 1fr;
    max-width: 400px;
  }
}
</style>
