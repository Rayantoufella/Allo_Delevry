<script setup>
import AppIcon from "../components/AppIcon.vue"
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api, { apiError } from '../api/axios'
import RequestCard from '../components/client/RequestCard.vue'

const router = useRouter()

const requests = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const errorMsg = ref('')
const currentPage = ref(1)
const totalPages = ref(1)

onMounted(() => {
  loadRequests()
})

async function loadRequests(page = 1) {
  if (page === 1) {
    loading.value = true
  } else {
    loadingMore.value = true
  }
  errorMsg.value = ''
  try {
    const { data } = await api.get('/delivery-requests', {
      params: { page },
    })
    if (page === 1) {
      requests.value = data.data || []
    } else {
      requests.value.push(...(data.data || []))
    }
    totalPages.value = data.meta?.last_page || 1
    currentPage.value = data.meta?.current_page || 1
  } catch (err) {
    errorMsg.value = apiError(err, 'Erreur lors du chargement des demandes.')
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

function loadMore() {
  loadRequests(currentPage.value + 1)
}
</script>

<template>
  <div class="my-requests">
    <h2 style="font-size: 1.75rem; margin-bottom: 1.25rem">Mes demandes</h2>

    <!-- Loading -->
    <div v-if="loading" class="flex-col" style="gap: 0.875rem">
      <div class="skeleton" style="height: 6.25rem"></div>
      <div class="skeleton" style="height: 6.25rem"></div>
      <div class="skeleton" style="height: 6.25rem"></div>
    </div>

    <!-- Error -->
    <p v-else-if="errorMsg" class="error-msg">{{ errorMsg }}</p>

    <!-- Empty -->
    <div v-else-if="!requests.length" class="empty-state card">
      <span class="empty-icon"><AppIcon name="package" :size="34" /></span>
      <h3>Aucune demande pour le moment</h3>
      <p class="muted small" style="margin-top: 0.5rem">
        Créez votre première demande de livraison pour commencer.
      </p>
      <router-link class="btn btn-primary" style="margin-top: 1rem" :to="{ name: 'landing' }">
        Trouver un livreur
      </router-link>
    </div>

    <!-- List -->
    <template v-else>
      <div class="requests-list">
        <RequestCard
          v-for="req in requests"
          :key="req.id"
          :request="req"
        />
      </div>

      <!-- Load more -->
      <div v-if="currentPage < totalPages" style="text-align: center; margin-top: 1rem">
        <button
          class="btn btn-outline"
          :disabled="loadingMore"
          @click="loadMore"
        >
          <span v-if="loadingMore" class="spinner spinner-sm"></span>
          <span v-else>Charger plus</span>
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.my-requests {
  max-width: 46.25rem;
  margin: 0 auto;
  padding-bottom: 3rem;
}

.requests-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.empty-state {
  text-align: center;
  padding: 3.5rem 1rem;
}

.empty-icon {
  font-size: 3rem;
  display: block;
  margin-bottom: 0.75rem;
}
</style>
