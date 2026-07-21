<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import http from '../api/client'
import { useTaskStore } from '../stores/taskStore'

const route = useRoute()
const store = useTaskStore()
const pluginId = route.params.pluginId

const url = ref('')
const depth = ref(1)
const cookieId = ref(null)
const modelId = ref(null)
const cookies = ref([])
const models = ref([])
const filter = ref('all') // all | info | warn | error

onMounted(async () => {
  ;[cookies.value, models.value] = await Promise.all([
    http.get('/cookies').then(r => r.data),
    http.get('/models').then(r => r.data),
  ])
  if (models.value[0]) modelId.value = models.value[0].id
})

const shown = computed(() =>
  filter.value === 'all'
    ? store.logs
    : store.logs.filter(l => l.level === filter.value)
)

async function run() {
  if (! url.value || ! modelId.value) return
  store.stop()
  const { data } = await http.post('/tasks/run', {
    plugin_id: pluginId,
    model_id: modelId.value,
    url: url.value,
    depth: depth.value,
    cookie_id: cookieId.value || undefined,
  })
  store.startSSE(data.task_id)
}
</script>

<template>
  <div class="run">
    <!-- 左：参数 -->
    <section class="pane left">
      <h3>运行参数</h3>
      <label>目标网址
        <input v-model="url" placeholder="https://example.com" />
      </label>
      <label>爬取深度
        <input v-model.number="depth" type="number" min="0" max="3" />
      </label>
      <label>登录 Cookie
        <select v-model="cookieId">
          <option :value="null">不使用</option>
          <option v-for="c in cookies" :key="c.id" :value="c.id">
            {{ c.site }}（{{ c.status }}）
          </option>
        </select>
      </label>
      <label>模型
        <select v-model="modelId">
          <option v-for="m in models" :key="m.id" :value="m.id">{{ m.name }} / {{ m.model }}</option>
        </select>
      </label>
      <button @click="run" :disabled="store.status === 'running'">
        {{ store.status === 'running' ? '运行中…' : '运行' }}
      </button>
    </section>

    <!-- 右：实时日志（SSE） -->
    <section class="pane right">
      <header>
        <span>实时日志 · {{ store.status }}</span>
        <select v-model="filter">
          <option value="all">全部</option>
          <option value="info">info</option>
          <option value="warn">warn</option>
          <option value="error">error</option>
        </select>
      </header>
      <div class="logs">
        <p v-for="(l, i) in shown" :key="i" :class="['log', l.level]">
          {{ l.message }}
        </p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.run { display: grid; grid-template-columns: 360px 1fr; gap: 16px; padding: 16px; height: calc(100vh - 60px); }
.pane { border: 1px solid #eee; border-radius: 8px; padding: 16px; overflow: auto; }
.pane.left label { display: block; margin: 12px 0; }
.pane.left input, .pane.left select { width: 100%; box-sizing: border-box; }
.logs .log.error { color: #c0392b; }
.logs .log.warn { color: #b9770e; }
</style>
