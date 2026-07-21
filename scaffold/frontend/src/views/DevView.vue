<script setup>
import { ref } from 'vue'
import http from '../api/client'

// 插件开发入口（PRD §4.8）：下载 PHP 模板 + 上传插件包
const file = ref(null)
const msg = ref('')

async function upload() {
  if (! file.value) return
  const fd = new FormData()
  fd.append('package', file.value)
  fd.append('developer', 'me')
  try {
    const { data } = await http.post('/plugins/upload', fd)
    msg.value = JSON.stringify(data)
  } catch (e) {
    msg.value = JSON.stringify(e.response?.data || e.message)
  }
}
</script>

<template>
  <div class="dev">
    <h2>插件开发</h2>
    <section>
      <h3>1. 下载 PHP 开发模板</h3>
      <!-- TODO: 后端提供 /dev/template.zip，含 manifest.json + 钩子示例 + 解析规则示例 -->
      <a href="/dev/template.zip" download>下载插件脚手架（manifest + 钩子 + 解析示例）</a>
    </section>
    <section>
      <h3>2. 上传插件包（.zip）</h3>
      <input type="file" accept=".zip" @change="e => file = e.target.files[0]" />
      <button @click="upload">上传（触发安全扫描）</button>
      <pre v-if="msg">{{ msg }}</pre>
    </section>
  </div>
</template>

<style scoped>
.dev { padding: 16px; }
section { margin: 16px 0; }
</style>
