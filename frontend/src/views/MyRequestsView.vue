<script setup>
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
    <h2 class="mb-16">Mes demandes de livraison</h2>

    <!-- Loading -->
    <div v-if="loading" class="flex-col" style="gap: 14px">
      <div class="skeleton" style="height: 100px"></div>
      <div class="skeleton" style="height: 100px"></div>
      <div class="skeleton" style="height: 100px"></div>
    </div>

    <!-- Error -->
    <p v-else-if="errorMsg" class="error-msg">{{ errorMsg }}</p>

    <!-- Empty -->
    <div v-else-if="!requests.length" class="empty-state">
      <span class="empty-icon">📦</span>
      <h3>Aucune demande pour le moment</h3>
      <p class="muted small mt-8">
        Créez votre première demande de livraison pour commencer.
      </p>
      <router-link class="btn btn-primary mt-16" :to="{ name: 'landing' }">
        Trouver un livreur
      </router-link>
    </div>

    <!-- List -->
    <template v-else>
      <div class="requests-list flex-col">
        <RequestCard
          v-for="req in requests"
          :key="req.id"
          :request="req"
        />
      </div>

      <!-- Load more -->
      <div v-if="currentPage < totalPages" class="mt-16" style="text-align: center">
        <button
          class="btn btn-outline"
          :disabled="loadingMore"
          @click="loadMore"
        >
          <span v-if="loadingMore" class="spinner"></span>
          <span v-else>Charger plus</span>
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.my-requests {
  max-width: 740px;
  margin: 0 auto;
  padding-bottom: 48px;
}

.requests-list {
  gap: 12px;
}

.empty-state {
  text-align: center;
  padding: 56px 16px;
}

.empty-icon {
  font-size: 3rem;
  display: block;
  margin-bottom: 12px;
}
</style>
