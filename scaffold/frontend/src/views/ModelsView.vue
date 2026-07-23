<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import http from '../api/client'

const list = ref([])
const result = ref(null)   // 测试按钮回显：{latency_ms, usage, error}
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    list.value = (await http.get('/models')).data
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function test(id) {
  result.value = '测试中…'
  const { data } = await http.post(`/models/${id}/test`)
  result.value = data   // {latency_ms, usage, error}
}

// ---- 新增模型 ----
const dialog = ref(false)
const saving = ref(false)
const form = ref({ name: '', vendor: 'openai', base_url: '', api_key: '', model: '' })

function openCreate() {
  form.value = { name: '', vendor: 'openai', base_url: '', api_key: '', model: '' }
  dialog.value = true
}
async function save() {
  saving.value = true
  try {
    await http.post('/models', form.value)
    ElMessage.success('已新增模型')
    dialog.value = false
    await load()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '保存失败')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <div class="page-head">
      <h2>模型管理</h2>
      <span class="muted">配置 GLM / DeepSeek / GPT 等 OpenAI 兼容模型，可一键测试连通性</span>
      <el-button type="primary" class="add" :icon="Plus" @click="openCreate">新增模型</el-button>
    </div>

    <el-table :data="list" v-loading="loading" stripe empty-text="暂无模型">
      <el-table-column prop="name" label="名称" min-width="140" />
      <el-table-column prop="vendor" label="供应商" width="120" />
      <el-table-column prop="model" label="模型" min-width="160" />
      <el-table-column prop="status" label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 'active' ? 'success' : 'info'">{{ row.status }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="100" fixed="right">
        <template #default="{ row }">
          <el-button size="small" type="primary" @click="test(row.id)">测试</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-alert
      v-if="result"
      class="result"
      :title="typeof result === 'string' ? result : '测试结果'"
      type="info"
      :closable="false"
    >
      <pre>{{ typeof result === 'string' ? '' : JSON.stringify(result, null, 2) }}</pre>
    </el-alert>

    <el-dialog v-model="dialog" title="新增模型" width="460px">
      <el-form :model="form" label-width="92px">
        <el-form-item label="名称">
          <el-input v-model="form.name" placeholder="如 默认 GPT" />
        </el-form-item>
        <el-form-item label="供应商">
          <el-select v-model="form.vendor">
            <el-option label="openai" value="openai" />
            <el-option label="glm" value="glm" />
            <el-option label="deepseek" value="deepseek" />
          </el-select>
        </el-form-item>
        <el-form-item label="端点">
          <el-input v-model="form.base_url" placeholder="https://api.openai.com/v1" />
        </el-form-item>
        <el-form-item label="模型">
          <el-input v-model="form.model" placeholder="gpt-4o" />
        </el-form-item>
        <el-form-item label="API Key">
          <el-input v-model="form.api_key" type="password" show-password placeholder="后端以 AES-256 加密存储" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.page-head { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.muted { color: var(--el-text-color-secondary); font-size: 13px; flex: 1; }
.result { margin-top: 16px; white-space: pre-wrap; }
.result pre { margin: 0; font-size: 13px; }
</style>
