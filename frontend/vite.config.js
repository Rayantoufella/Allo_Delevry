import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    // Proxy API : évite tout CORS en dev. Le backend est joignable
    // via le service docker "nginx" (ou PROXY_TARGET en local).
    proxy: {
      '/api': {
        target: process.env.PROXY_TARGET || 'http://nginx',
        changeOrigin: true,
      },
    },
  },
})
