<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import http from '../api/client'
import { useTaskStore } from '../stores/taskStore'

const route = useRoute()
const store = useTaskStore()
const pluginId = route.params.pluginId

const form = ref({ url: '', depth: 1, cookie_id: null, model_id: null })
const cookies = ref([])
const models = ref([])
const filter = ref('all')

onMounted(async () => {
  ;[cookies.value, models.value] = await Promise.all([
    http.get('/cookies').then(r => r.data),
    http.get('/models').then(r => r.data),
  ])
  if (models.value[0]) form.value.model_id = models.value[0].id
})

const shown = computed(() =>
  filter.value === 'all'
    ? store.logs
    : store.logs.filter(l => l.level === filter.value)
)
const running = computed(() => store.status === 'running')

async function run() {
  if (!form.value.url || !form.value.model_id) {
    ElMessage.warning('请填写目标网址并选择模型')
    return
  }
  store.stop()
  const { data } = await http.post('/tasks/run', {
    plugin_id: pluginId,
    model_id: form.value.model_id,
    url: form.value.url,
    depth: form.value.depth,
    cookie_id: form.value.cookie_id || undefined,
  })
  store.startSSE(data.task_id)
}
</script>

<template>
  <el-row :gutter="16" class="run">
    <!-- 左：参数 -->
    <el-col :span="8">
      <el-card shadow="never" header="运行参数">
        <el-form :model="form" label-position="top">
          <el-form-item label="目标网址">
            <el-input v-model="form.url" placeholder="https://example.com" />
          </el-form-item>
          <el-form-item label="爬取深度">
            <el-input-number v-model="form.depth" :min="0" :max="3" />
          </el-form-item>
          <el-form-item label="登录 Cookie">
            <el-select v-model="form.cookie_id" placeholder="不使用" clearable>
              <el-option
                v-for="c in cookies"
                :key="c.id"
                :label="`${c.site}（${c.status}）`"
                :value="c.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="模型">
            <el-select v-model="form.model_id" placeholder="请选择">
              <el-option
                v-for="m in models"
                :key="m.id"
                :label="`${m.name} / ${m.model}`"
                :value="m.id"
              />
            </el-select>
          </el-form-item>
          <el-button type="primary" class="full" :loading="running" @click="run">
            {{ running ? '运行中…' : '运行' }}
          </el-button>
        </el-form>
      </el-card>
    </el-col>

    <!-- 右：实时日志（SSE） -->
    <el-col :span="16">
      <el-card shadow="never" class="log-card">
        <template #header>
          <div class="log-head">
            <span>
              实时日志 ·
              <el-tag size="small" :type="running ? 'warning' : 'info'">{{ store.status }}</el-tag>
            </span>
            <el-select v-model="filter" size="small" style="width: 120px">
              <el-option label="全部" value="all" />
              <el-option label="info" value="info" />
              <el-option label="warn" value="warn" />
              <el-option label="error" value="error" />
            </el-select>
          </div>
        </template>
        <div class="logs">
          <p v-for="(l, i) in shown" :key="i" :class="['log', l.level]">{{ l.message }}</p>
          <el-empty v-if="!shown.length" description="暂无日志，点击“运行”开始采集" />
        </div>
      </el-card>
    </el-col>
  </el-row>
</template>

<style scoped>
.run { align-items: stretch; }
.full { width: 100%; }
.log-card :deep(.el-card__body) { padding: 12px; }
.log-head { display: flex; align-items: center; justify-content: space-between; }
.logs {
  height: 62vh; overflow: auto;
  background: #1e1e1e; color: #d9d9d9;
  padding: 12px; border-radius: 6px;
  font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 13px;
}
.logs .log { margin: 0 0 4px; line-height: 1.5; }
.logs .log.error { color: #ff7875; }
.logs .log.warn { color: #ffc53d; }
.logs .log.info { color: #d9d9d9; }
</style>
