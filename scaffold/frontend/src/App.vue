<script setup>
import { useRouter, useRoute } from 'vue-router'
import http from './api/client'

const router = useRouter()
const route = useRoute()

const nav = [
  { to: '/market', label: '插件市场' },
  { to: '/my', label: '我的插件' },
  { to: '/models', label: '模型管理' },
  { to: '/dev', label: '插件开发' },
]

async function logout() {
  await http.post('/logout')
  localStorage.removeItem('token')
  router.push('/login')
}
</script>

<template>
  <div class="layout">
    <header v-if="route.name !== 'login'">
      <strong>AI 采集插件平台</strong>
      <nav>
        <router-link v-for="n in nav" :key="n.to" :to="n.to">{{ n.label }}</router-link>
      </nav>
      <button @click="logout">退出</button>
    </header>
    <main>
      <router-view />
    </main>
  </div>
</template>

<style>
.layout { font-family: system-ui, sans-serif; max-width: 1200px; margin: 0 auto; }
header { display: flex; align-items: center; gap: 16px; padding: 12px; border-bottom: 1px solid #eee; }
nav { display: flex; gap: 12px; flex: 1; }
</style>
