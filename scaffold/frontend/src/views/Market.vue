<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import http from '../api/client'

const router = useRouter()
const list = ref([])
const loading = ref(false)

onMounted(load)

async function load() {
  loading.value = true
  try {
    list.value = (await http.get('/market')).data
  } finally {
    loading.value = false
  }
}

async function install(row) {
  await http.post(`/plugins/${row.id}/install`)
  ElMessage.success('已安装，进入运行页')
  router.push(`/run/${row.id}`)
}
</script>

<template>
  <div>
    <div class="page-head">
      <h2>插件市场</h2>
      <span class="muted">从市场安装插件，安装后可直接运行采集任务</span>
    </div>
    <el-table :data="list" v-loading="loading" stripe empty-text="暂无已上架插件">
      <el-table-column prop="name" label="名称" min-width="140" />
      <el-table-column prop="developer" label="开发者" width="120" />
      <el-table-column prop="version" label="版本" width="90" />
      <el-table-column prop="updated_at_field" label="更新日期" width="120" />
      <el-table-column prop="description" label="说明" min-width="220" show-overflow-tooltip />
      <el-table-column label="操作" width="130" fixed="right">
        <template #default="{ row }">
          <el-button type="primary" size="small" @click="install(row)">安装并运行</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<style scoped>
.page-head { display: flex; align-items: baseline; gap: 12px; margin-bottom: 16px; }
.muted { color: var(--el-text-color-secondary); font-size: 13px; }
</style>
