import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig(({ mode }) => {
  // Vite n'expose au client que les variables préfixées VITE_, et ne peuple pas
  // process.env depuis .env : sans loadEnv, PROXY_TARGET restait ignoré et le
  // proxy retombait silencieusement sur sa valeur par défaut.
  const env = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [vue()],
    server: {
      host: '0.0.0.0',
      port: 5173,
      // Les événements de fichiers ne traversent pas un montage Docker sous
      // Windows : sans scrutation active, Vite ne voit aucune modification et
      // le hot-reload reste muet jusqu'au redémarrage du conteneur.
      watch: {
        usePolling: true,
        interval: 300,
      },
      // Proxy API : évite tout CORS en dev. La cible est le service backend
      // du réseau docker (laravel.test pour la stack Sail de backend/compose.yaml).
      proxy: {
        '/api': {
          target: env.PROXY_TARGET || 'http://laravel.test',
          changeOrigin: true,
        },
      },
    },
  }
})
