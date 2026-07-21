<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import http from '../api/client'

const router = useRouter()
const list = ref([])

onMounted(async () => {
  list.value = (await http.get('/market')).data
})

async function install(id) {
  await http.post(`/plugins/${id}/install`)
  router.push(`/run/${id}`)   // 安装后进入运行页
}
</script>

<template>
  <div class="market">
    <h2>插件市场</h2>
    <ul>
      <li v-for="p in list" :key="p.id">
        <div>
          <strong>{{ p.name }}</strong>
          <span class="meta">by {{ p.developer }} · v{{ p.version }} · {{ p.updated_at_field }}</span>
          <p>{{ p.description }}</p>
        </div>
        <button @click="install(p.id)">安装并运行</button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.market { padding: 16px; }
li { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 12px 0; border-bottom: 1px solid #eee; }
.meta { color: #888; font-size: 12px; margin: 0 8px; }
</style>
