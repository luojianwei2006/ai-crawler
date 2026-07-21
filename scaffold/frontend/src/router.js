import { createRouter, createWebHistory } from 'vue-router'

import Login from './views/Login.vue'
import Market from './views/Market.vue'
import MyPlugins from './views/MyPlugins.vue'
import RunView from './views/RunView.vue'
import ModelsView from './views/ModelsView.vue'
import DevView from './views/DevView.vue'

const routes = [
  { path: '/login', name: 'login', component: Login },
  { path: '/', redirect: '/market' },
  { path: '/market', name: 'market', component: Market },
  { path: '/my', name: 'my', component: MyPlugins },
  { path: '/run/:pluginId', name: 'run', component: RunView },
  { path: '/models', name: 'models', component: ModelsView },
  { path: '/dev', name: 'dev', component: DevView },
]

export default createRouter({
  history: createWebHistory(),
  routes,
})
