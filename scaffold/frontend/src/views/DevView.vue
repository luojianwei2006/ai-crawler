<script setup>
import { ref } from 'vue'
import { ElMessage } from 'element-plus'
import http from '../api/client'

// 插件开发入口（PRD §4.8）：下载 PHP 模板 + 上传插件包
const msg = ref('')
const uploading = ref(false)

async function upload(file) {
  const fd = new FormData()
  fd.append('package', file.raw)
  fd.append('developer', 'me')
  uploading.value = true
  try {
    const { data } = await http.post('/plugins/upload', fd)
    msg.value = JSON.stringify(data, null, 2)
    ElMessage.success('上传成功，已触发安全扫描')
  } catch (e) {
    msg.value = JSON.stringify(e.response?.data || e.message, null, 2)
    ElMessage.error('上传失败')
  } finally {
    uploading.value = false
  }
  return false // 阻止 el-upload 自动上传（已在 on-change 中手动上传）
}
</script>

<template>
  <div>
    <div class="page-head">
      <h2>插件开发</h2>
      <span class="muted">下载脚手架编写插件，打包上传后由管理员审核上架</span>
    </div>
    <el-row :gutter="16">
      <el-col :span="12">
        <el-card shadow="never" header="1. 下载 PHP 开发模板">
          <p class="muted">模板含 manifest.json + 钩子示例 + 解析规则示例。</p>
          <el-button type="primary" tag="a" href="/dev/template.zip" target="_blank">
            下载插件脚手架
          </el-button>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="never" header="2. 上传插件包（.zip）">
          <el-upload
            drag
            accept=".zip"
            :auto-upload="false"
            :show-file-list="true"
            :limit="1"
            :on-change="upload"
          >
            <el-icon :size="36" color="var(--el-color-primary)"><UploadFilled /></el-icon>
            <div class="el-upload__text">将 .zip 拖到此处，或<em>点击选择</em></div>
          </el-upload>
        </el-card>
      </el-col>
    </el-row>

    <el-alert
      v-if="msg"
      class="result"
      title="返回结果"
      type="info"
      :closable="false"
    >
      <pre>{{ msg }}</pre>
    </el-alert>
  </div>
</template>

<style scoped>
.page-head { display: flex; align-items: baseline; gap: 12px; margin-bottom: 16px; }
.muted { color: var(--el-text-color-secondary); font-size: 13px; margin: 0 0 12px; }
.result { margin-top: 16px; white-space: pre-wrap; }
.result pre { margin: 0; font-size: 13px; }
</style>
