<script setup>
import { ref, onMounted } from 'vue'
import http from '../api/client'

const list = ref([])
const result = ref(null)   // 测试按钮回显：延迟/token/错误码

async function load() { list.value = (await http.get('/models')).data }
onMounted(load)

async function test(id) {
  result.value = '测试中…'
  const { data } = await http.post(`/models/${id}/test`)
  result.value = data   // {latency_ms, usage, error}
}
</script>

<template>
  <div class="models">
    <h2>模型管理</h2>
    <table>
      <thead><tr><th>名称</th><th>供应商</th><th>模型</th><th>操作</th></tr></thead>
      <tbody>
        <tr v-for="m in list" :key="m.id">
          <td>{{ m.name }}</td><td>{{ m.vendor }}</td><td>{{ m.model }}</td>
          <td><button @click="test(m.id)">测试</button></td>
        </tr>
      </tbody>
    </table>
    <pre v-if="result" class="result">{{ JSON.stringify(result, null, 2) }}</pre>
  </div>
</template>

<style scoped>
.models { padding: 16px; }
table { width: 100%; border-collapse: collapse; }
td, th { text-align: left; padding: 8px; border-bottom: 1px solid #eee; }
.result { background: #f6f6f6; padding: 12px; border-radius: 6px; }
</style>
