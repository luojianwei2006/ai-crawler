<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import http from '../api/client'

const router = useRouter()
const list = ref([])

async function load() {
  list.value = (await http.get('/my-plugins')).data
}
onMounted(load)

async function toggle(id) {
  await http.post(`/plugins/${id}/toggle`)
  await load()
}
function run(id) {
  router.push(`/run/${id}`)
}
</script>

<template>
  <div class="my">
    <h2>我的插件</h2>
    <ul>
      <li v-for="u in list" :key="u.id">
        <span>
          {{ u.plugin.name }} · {{ u.plugin.version }}
          <em :class="u.enabled ? 'on' : 'off'">{{ u.enabled ? '已启用' : '已停用' }}</em>
        </span>
        <span>
          <button @click="run(u.plugin_id)">运行</button>
          <button @click="toggle(u.plugin_id)">启/停</button>
        </span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.my { padding: 16px; }
li { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
.on { color: #16a34a; font-style: normal; }
.off { color: #888; font-style: normal; }
</style>
