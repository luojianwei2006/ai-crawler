import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// 私有化自托管：前端 5173 代理 /api 到 Laravel 8000
export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://localhost:8000',
    },
  },
})
