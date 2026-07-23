import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import * as ElementPlusIconsVue from '@element-plus/icons-vue'
import App from './App.vue'
import router from './router'

const app = createApp(App)

// 全局注册 Element Plus 图标，模板中可直接使用 <UploadFilled /> 等
for (const [name, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(name, component)
}

app.use(createPinia()).use(router).use(ElementPlus).mount('#app')
