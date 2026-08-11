import { onBeforeUnmount, ref } from 'vue'

/**
 * Polling — rafraîchissement périodique des données (chat, statuts,
 * notifications). Le temps réel WebSocket (Reverb) a été retiré du
 * projet : le frontend interroge les endpoints classiques par polling.
 *
 * @param {Function} fetcher fonction async qui renvoie les données
 * @param {number} intervalMs délai entre deux appels (défaut 4000)
 */
export function usePolling(fetcher, intervalMs = 4000) {
  const data = ref(null)
  const loading = ref(false)
  const error = ref(null)
  let timer = null
  let running = false

  async function tick() {
    if (running) return
    running = true
    loading.value = true
    try {
      data.value = await fetcher()
      error.value = null
    } catch (err) {
      error.value = err
    } finally {
      running = false
      loading.value = false
    }
  }

  function start() {
    tick()
    if (timer) clearInterval(timer)
    timer = setInterval(tick, intervalMs)
  }

  function stop() {
    if (timer) clearInterval(timer)
    timer = null
  }

  onBeforeUnmount(stop)

  return { data, loading, error, start, stop, refresh: tick }
}
