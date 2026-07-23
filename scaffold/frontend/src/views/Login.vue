<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import http from '../api/client'

const router = useRouter()
const form = ref({ email: '', password: '' })
const loading = ref(false)

async function login() {
  loading.value = true
  try {
    const { data } = await http.post('/login', form.value)
    console.log('[LOGIN] token received:', data.token ? data.token.substring(0, 30) + '…' : 'MISSING', 'role:', data.role)
    localStorage.setItem('token', data.token)
    console.log('[LOGIN] stored in localStorage, navigating to /market')
    ElMessage.success('登录成功')
    router.push('/market')
  } catch (e) {
    console.warn('[LOGIN] failed:', e.response?.data || e.message)
    ElMessage.error(e.response?.data?.error || '登录失败')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-wrap">
    <el-card class="login-card" shadow="always">
      <div class="title">AI 采集插件平台</div>
      <div class="subtitle">登录后台</div>
      <el-form :model="form" label-position="top" @submit.prevent="login">
        <el-form-item label="邮箱">
          <el-input v-model="form.email" placeholder="admin@example.com" size="large" />
        </el-form-item>
        <el-form-item label="密码">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="请输入密码"
            size="large"
            show-password
            @keyup.enter="login"
          />
        </el-form-item>
        <el-button type="primary" size="large" class="full" :loading="loading" @click="login">
          登录
        </el-button>
      </el-form>
    </el-card>
  </div>
</template>

<style scoped>
.login-wrap {
  height: 100vh; display: flex; align-items: center; justify-content: center;
  background: var(--el-bg-color-page);
}
.login-card { width: 380px; }
.title { font-size: 20px; font-weight: 700; text-align: center; color: var(--el-color-primary); }
.subtitle { text-align: center; color: var(--el-text-color-secondary); margin: 4px 0 20px; }
.full { width: 100%; }
</style>
