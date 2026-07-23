import axios from 'axios'

const http = axios.create({
  baseURL: '/api',
})

// 注入 Sanctum Bearer Token
http.interceptors.request.use((cfg) => {
  const t = localStorage.getItem('token')
  if (t) cfg.headers.Authorization = `Bearer ${t}`
  console.log(`[HTTP→] ${cfg.method?.toUpperCase() || 'GET'} ${cfg.url}`, {
    token_sent: !!t,
    token_preview: t ? t.substring(0, 25) + '…' : 'null',
  })
  return cfg
})

// 统一错误：401 跳登录
http.interceptors.response.use(
  (r) => {
    console.log(`[HTTP←] ${r.config.method?.toUpperCase() || 'GET'} ${r.config.url} → ${r.status}`)
    return r
  },
  (err) => {
    const status = err.response?.status || 0
    console.warn(`[HTTP←] ${err.config?.method?.toUpperCase() || '?'} ${err.config?.url || '?'} → ${status}`, {
      data: err.response?.data,
    })
    if (status === 401) {
      localStorage.removeItem('token')
      location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default http
