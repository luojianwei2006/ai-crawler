import axios from 'axios'

const http = axios.create({
  baseURL: '/api',
  withCredentials: true,
})

// 统一错误：401 跳登录
http.interceptors.response.use(
  (r) => r,
  (err) => {
    if (err.response?.status === 401) {
      location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default http
