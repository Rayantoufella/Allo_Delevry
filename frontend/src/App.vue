<script setup>
import AppHeader from './components/AppHeader.vue'
</script>

<template>
  <AppHeader />
  <main class="page">
    <div class="container">
      <RouterView />
    </div>
  </main>
</template>
