<script setup>
import { useRouter, useRoute } from 'vue-router'
import { computed, onMounted } from 'vue'
import { SwitchButton } from '@element-plus/icons-vue'
import http from './api/client'

const router = useRouter()
const route = useRoute()

onMounted(() => {
  const t = localStorage.getItem('token')
  console.log('[APP] mounted', { path: route.path, name: route.name, has_token: !!t, token_preview: t ? t.substring(0, 25) + '…' : 'null' })
})

const activeIndex = computed(() => route.path.startsWith('/run') ? '/market' : route.path)
const showChrome = computed(() => route.name !== 'login')

const nav = [
  { index: '/market', label: '插件市场' },
  { index: '/my', label: '我的插件' },
  { index: '/models', label: '模型管理' },
  { index: '/dev', label: '插件开发' },
]

function handleSelect(index) {
  router.push(index)
}

async function logout() {
  try { await http.post('/logout') } catch (_) { /* 忽略 */ }
  localStorage.removeItem('token')
  router.push('/login')
}
</script>

<template>
  <el-container v-if="showChrome" class="layout">
    <el-header class="header">
      <div class="brand">AI 采集插件平台</div>
      <el-menu :default-active="activeIndex" mode="horizontal" class="nav" @select="handleSelect">
        <el-menu-item v-for="n in nav" :key="n.index" :index="n.index">{{ n.label }}</el-menu-item>
      </el-menu>
      <el-button text type="primary" :icon="SwitchButton" @click="logout">退出</el-button>
    </el-header>
    <el-main class="main">
      <router-view />
    </el-main>
  </el-container>
  <router-view v-else />
</template>

<style>
html, body, #app { height: 100%; margin: 0; }
.layout { min-height: 100vh; }
.header {
  display: flex; align-items: center; gap: 24px;
  background: #fff; border-bottom: 1px solid var(--el-border-color-light);
}
.brand { font-weight: 700; font-size: 16px; color: var(--el-color-primary); white-space: nowrap; }
.nav { flex: 1; border-bottom: none; }
.main { background: var(--el-bg-color-page); padding: 24px; }
</style>
