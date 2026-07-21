<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import http from '../api/client'

const router = useRouter()
const email = ref('')
const password = ref('')
const err = ref('')

async function login() {
  err.value = ''
  try {
    const { data } = await http.post('/login', { email: email.value, password: password.value })
    localStorage.setItem('token', data.token)
    router.push('/market')
  } catch (e) {
    err.value = e.response?.data?.error || '登录失败'
  }
}
</script>

<template>
  <div class="login">
    <h2>登录</h2>
    <input v-model="email" placeholder="email" />
    <input v-model="password" type="password" placeholder="密码" />
    <button @click="login">登录</button>
    <p v-if="err" class="err">{{ err }}</p>
  </div>
</template>

<style scoped>
.login { max-width: 320px; margin: 80px auto; display: flex; flex-direction: column; gap: 12px; }
.err { color: #c0392b; }
</style>
