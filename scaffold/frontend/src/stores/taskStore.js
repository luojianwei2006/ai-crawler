import { defineStore } from 'pinia'

/**
 * 任务日志 store：SSE 实时日志（PRD §4.9 / tasks M6）
 * - 原生 EventSource（零依赖、自动重连）
 * - 仅当需前端反向控制（暂停/中止）再升级 WebSocket（评审议题 T4）
 */
export const useTaskStore = defineStore('task', {
  state: () => ({
    taskId: null,
    status: 'idle',     // idle | running | done | error
    logs: [],          // {level, message, t}
    es: null,
    autoscroll: true,
  }),
  actions: {
    startSSE(taskId) {
      this.taskId = taskId
      this.logs = []
      this.status = 'running'

      const es = new EventSource(`/api/tasks/${taskId}/stream`)
      es.onmessage = (e) => {
        try {
          const d = JSON.parse(e.data)
          this.logs.push({ level: d.level || 'info', message: d.message, t: Date.now() })
        } catch (_) { /* 忽略心跳 */ }
      }
      es.addEventListener('done', () => {
        this.status = 'done'
        es.close()
        this.es = null
      })
      es.onerror = () => { /* SSE 自动重连，无需手动 */ }
      this.es = es
    },
    stop() {
      this.es?.close()
      this.es = null
    },
  },
})
