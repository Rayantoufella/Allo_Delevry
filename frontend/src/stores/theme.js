import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

/**
 * Store de thème (clair/sombre) — bouton ☀ du bandeau.
 * Persistance : localStorage, défaut sombre (comme le prototype).
 */
export const useThemeStore = defineStore('theme', () => {
  const stored = localStorage.getItem('allo_theme') || 'dark'
  const theme = ref(stored)

  const isDark = computed(() => theme.value === 'dark')

  function setTheme(value) {
    theme.value = value
    localStorage.setItem('allo_theme', value)
    document.documentElement.dataset.theme = value
  }

  function toggle() {
    setTheme(theme.value === 'dark' ? 'light' : 'dark')
  }

  // Applique le thème au chargement.
  document.documentElement.dataset.theme = stored

  return { theme, isDark, setTheme, toggle }
})
