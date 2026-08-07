<script setup>
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()
</script>

<template>
  <div class="landing">
    <!-- HERO -->
    <section class="hero">
      <div class="container hero-inner">
        <span class="badge badge-green hero-badge">Livraison à la demande</span>
        <h1 class="hero-title">
          Chaque livreur, sa marque.<br />
          <span class="hero-accent">Chaque colis, un suivi.</span>
        </h1>
        <p class="hero-sub">
          Allo Delivery connecte les clients à des livreurs professionnels indépendants.
          Suivez chaque colis en temps réel, avec chat et preuves de livraison.
        </p>
        <div class="hero-cta">
          <button class="btn btn-primary btn-lg" @click="router.push({ name: auth.isAuthenticated ? 'my-requests' : 'register' })">
            Créer mon compte client
          </button>
          <button class="btn btn-outline btn-lg" @click="router.push({ name: 'register-driver' })">
            Devenir livreur
          </button>
        </div>
      </div>
    </section>

    <!-- COMMENT ÇA MARCHE -->
    <section class="container section">
      <h2 class="section-title">Comment ça marche</h2>
      <div class="grid-3 steps">
        <div class="card step-card">
          <span class="step-icon">🔍</span>
          <h3>Choisissez votre livreur</h3>
          <p class="small muted">
            Parcourez les profils publics, comparez les services et tarifs de chaque livreur dans votre ville.
          </p>
        </div>
        <div class="card step-card">
          <span class="step-icon">📝</span>
          <h3>Envoyez votre demande</h3>
          <p class="small muted">
            Remplissez le formulaire ou décrivez votre besoin à l'assistant IA. Le livreur reçoit votre demande instantanément.
          </p>
        </div>
        <div class="card step-card">
          <span class="step-icon">📡</span>
          <h3>Suivez en temps réel</h3>
          <p class="small muted">
            Suivez l'avancement, échangez par chat, et recevez des preuves photos à chaque étape clé de la livraison.
          </p>
        </div>
      </div>
    </section>

    <!-- POURQUOI ALLO DELIVERY -->
    <section class="container section">
      <h2 class="section-title">Pourquoi Allo Delivery</h2>
      <div class="grid-3 reasons">
        <div class="reason-item">
          <span class="reason-icon">🛡️</span>
          <h4>Sécurité totale</h4>
          <p class="small muted">
            Code de confirmation à 6 chiffres pour valider la remise. Preuves photos obligatoires à chaque étape critique.
          </p>
        </div>
        <div class="reason-item">
          <span class="reason-icon">💬</span>
          <h4>Chat intégré</h4>
          <p class="small muted">
            Communiquez directement avec votre livreur. Demandez un changement d'adresse ou posez des questions en direct.
          </p>
        </div>
        <div class="reason-item">
          <span class="reason-icon">🏪</span>
          <h4>Chaque livreur a sa vitrine</h4>
          <p class="small muted">
            Les livreurs ont leur propre page publique avec services, tarifs et zones de livraison. Choisissez en confiance.
          </p>
        </div>
        <div class="reason-item">
          <span class="reason-icon">🤖</span>
          <h4>Assistant IA</h4>
          <p class="small muted">
            Décrivez votre demande en langage naturel — l'IA pré-remplit le formulaire automatiquement.
          </p>
        </div>
        <div class="reason-item">
          <span class="reason-icon">💰</span>
          <h4>Tarifs transparents</h4>
          <p class="small muted">
            Chaque service affiche son prix de base. Le livreur peut proposer un prix adapté, que vous acceptez ou refusez.
          </p>
        </div>
        <div class="reason-item">
          <span class="reason-icon">⚡</span>
          <h4>Rapidité</h4>
          <p class="small muted">
            Création de demande en quelques secondes. Notifications en temps réel pour chaque changement de statut.
          </p>
        </div>
      </div>
    </section>

    <!-- CTA FINAL -->
    <section class="cta-section">
      <div class="container cta-inner">
        <h2>Prêt à livrer ou à être livré ?</h2>
        <p class="muted mt-8">
          Rejoignez Allo Delivery aujourd'hui et profitez d'un service de livraison moderne et fiable.
        </p>
        <div class="hero-cta mt-24">
          <button class="btn btn-primary btn-lg" @click="router.push({ name: auth.isAuthenticated ? 'my-requests' : 'register' })">
            Commencer maintenant
          </button>
          <button class="btn btn-outline btn-lg" @click="router.push({ name: 'register-driver' })">
            Je suis livreur
          </button>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.landing {
  display: flex;
  flex-direction: column;
}

/* Hero */
.hero {
  padding: 72px 0 64px;
  text-align: center;
}
.hero-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}
.hero-badge {
  font-size: 0.85rem;
  padding: 6px 16px;
}
.hero-title {
  font-size: 3rem;
  line-height: 1.08;
  max-width: 700px;
}
.hero-accent {
  color: var(--brand);
}
.hero-sub {
  max-width: 540px;
  color: var(--text-dim);
  font-size: 1.05rem;
  line-height: 1.6;
}
.hero-cta {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  justify-content: center;
}

/* Sections */
.section {
  padding: 56px 0 40px;
}
.section-title {
  text-align: center;
  margin-bottom: 32px;
}

/* Steps */
.steps .step-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 10px;
  padding: 28px 20px;
}
.step-icon {
  font-size: 2.2rem;
  margin-bottom: 4px;
}

/* Reasons */
.reasons {
  gap: 28px;
}
.reason-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 8px 0;
}
.reason-icon {
  font-size: 1.6rem;
}

/* CTA */
.cta-section {
  padding: 64px 0 56px;
  text-align: center;
  border-top: 1px solid var(--border);
}
.cta-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* Responsive */
@media (max-width: 768px) {
  .hero { padding: 48px 0 40px; }
  .hero-title { font-size: 2rem; }
  .hero-sub { font-size: 0.95rem; }
  .section { padding: 36px 0 24px; }
}
</style>
