<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import http from '../api/client'

const router = useRouter()
const list = ref([])
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    list.value = (await http.get('/my-plugins')).data
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function toggle(row) {
  await http.post(`/plugins/${row.plugin_id}/toggle`)
  ElMessage.success('已切换启用状态')
  await load()
}
function run(row) {
  router.push(`/run/${row.plugin_id}`)
}
</script>

<template>
  <div>
    <div class="page-head">
      <h2>我的插件</h2>
      <span class="muted">管理已安装插件，启停或运行采集任务</span>
    </div>
    <el-table :data="list" v-loading="loading" stripe empty-text="尚未安装任何插件">
      <el-table-column label="插件" min-width="180">
        <template #default="{ row }">
          {{ row.plugin.name }}
          <span class="muted">v{{ row.plugin.version }}</span>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="110">
        <template #default="{ row }">
          <el-tag :type="row.enabled ? 'success' : 'info'">
            {{ row.enabled ? '已启用' : '已停用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="run(row)">运行</el-button>
          <el-button
            size="small"
            :type="row.enabled ? 'warning' : 'success'"
            @click="toggle(row)"
          >
            启/停
          </el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<style scoped>
.page-head { display: flex; align-items: baseline; gap: 12px; margin-bottom: 16px; }
.muted { color: var(--el-text-color-secondary); font-size: 13px; }
</style>
