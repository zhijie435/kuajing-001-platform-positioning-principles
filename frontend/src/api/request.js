import axios from 'axios'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getFingerprint } from '@/utils/fingerprint'
import { useUserStore } from '@/store/user'
import router from '@/router'

const platform = document.querySelector('meta[name="platform"]')?.content
  || import.meta.env.VITE_PLATFORM
  || 'sales'

const service = axios.create({
  baseURL: import.meta.env.VITE_API_BASE || '/api',
  timeout: 15000
})

let isRefreshing = false
let failedQueue = []

function processQueue(error, token = null) {
  failedQueue.forEach(prom => {
    if (error) {
      prom.reject(error)
    } else {
      prom.resolve(token)
    }
  })
  failedQueue = []
}

service.interceptors.request.use(
  config => {
    const userStore = useUserStore()

    config.headers['X-Platform-Type'] = platform
    config.headers['X-Device-Fingerprint'] = getFingerprint()

    if (userStore.token) {
      config.headers['Authorization'] = 'Bearer ' + userStore.token
    }

    return config
  },
  error => {
    console.error('[RedLine] 请求拦截器异常:', error)
    return Promise.reject(error)
  }
)

service.interceptors.response.use(
  response => {
    const res = response.data

    const licenseWarning = response.headers['x-license-warning']
    if (licenseWarning) {
      const daysMatch = licenseWarning.match(/expires-in-(\d+)-days/)
      if (daysMatch) {
        ElMessage.warning({
          message: `⚠️ 系统 License 将在 ${daysMatch[1]} 天后到期，请及时续费`,
          duration: 6000,
          offset: 60
        })
      }
    }

    if (res.code === 0 || res.code === 200) {
      return res
    }

    const code = res.code
    const msg = res.message || '请求异常'

    if (code === 401 || code === 4201 || code === 4206 || code === 4207) {
      const userStore = useUserStore()
      userStore.logout()
      ElMessageBox.alert(msg + '，请重新登录', '身份验证失败', {
        confirmButtonText: '去登录',
        type: 'error',
        callback: () => {
          router.push({ name: 'login', query: { redirect: location.pathname } })
        }
      })
      return Promise.reject(new Error(msg))
    }

    if (code === 4003) {
      ElMessage.error({ message: '⛔ ' + msg, duration: 5000 })
      return Promise.reject(new Error(msg))
    }

    if (code === 403) {
      ElMessage.error({ message: '⛔ 无权限访问: ' + msg, duration: 5000 })
      return Promise.reject(new Error(msg))
    }

    if (code >= 4100 && code < 4200) {
      const violations = Array.isArray(res.data?.detail) ? res.data.detail : []
      const violationHtml = violations.length
        ? violations
            .map(v => {
              const typeLabel = {
                invalid_license: 'License 签名无效',
                license_expired: 'License 已过期',
                feature_out_of_boundary: '功能超出版本边界',
                user_limit_exceeded: '用户数已达上限',
                client_limit_exceeded: '客户数已达上限',
                trial_expired: '试用期已结束'
              }[v.type] || v.type
              const dataStr = v.data && Object.keys(v.data).length
                ? Object.entries(v.data).map(([k, val]) => `${k}: ${val}`).join(' / ')
                : ''
              return `<div style="font-size:12px;color:#909399;margin-top:4px;padding-left:12px">· ${typeLabel}${dataStr ? `（${dataStr}）` : ''}</div>`
            })
            .join('')
        : ''

      ElMessageBox.alert(
        `<div style="line-height:1.8">
          <div style="font-weight:bold;margin-bottom:8px;color:#e6a23c">⚠️ 商用边界限制</div>
          <div>${msg}</div>
          ${violationHtml}
          <div style="margin-top:8px;font-size:12px;color:#909399">如需开通请联系商务部门，错误码：${code}</div>
        </div>`,
        '访问受限',
        {
          confirmButtonText: '我知道了',
          type: 'warning',
          dangerouslyUseHTMLString: true
        }
      )
      return Promise.reject(new Error(msg))
    }

    if (code >= 4500 && code < 4600) {
      ElMessageBox.alert(
        `<div style="line-height:1.8">
          <div style="font-weight:bold;margin-bottom:8px;color:#f56c6c">🚫 安全红线触发</div>
          <div>${msg}</div>
          ${res.data?.detail ? `<div style="margin-top:8px;font-size:12px;color:#909399">详情：${JSON.stringify(res.data.detail)}</div>` : ''}
        </div>`,
        '访问被拦截',
        {
          confirmButtonText: '我知道了',
          type: 'error',
          dangerouslyUseHTMLString: true
        }
      )
      return Promise.reject(new Error(msg))
    }

    if (code >= 4600 && code < 4700) {
      return Promise.reject({
        message: msg,
        code: code,
        data: res.data,
        response: { data: res }
      })
    }

    if (code === 4205) {
      ElMessage.warning(msg)
      return Promise.reject(new Error(msg))
    }

    ElMessage.error(msg)
    return Promise.reject(new Error(msg))
  },
  error => {
    const status = error.response?.status
    const data = error.response?.data
    const code = data?.code

    if (code && code >= 4600 && code < 4700 && data?.data?.rollback) {
      return Promise.reject({
        message: data.message,
        code: code,
        data: data.data,
        response: { data: data }
      })
    }

    if (status === 401 || status === 4201) {
      const userStore = useUserStore()
      userStore.logout()
      router.push({ name: 'login' })
      ElMessage.error('登录已失效，请重新登录')
      return Promise.reject(error)
    }

    if (status === 403) {
      ElMessage.error('无权限访问该资源')
      return Promise.reject(error)
    }

    if (status === 429) {
      ElMessage.warning('请求过于频繁，请稍后再试')
      return Promise.reject(error)
    }

    if (status === 400) {
      ElMessage.error(data?.message || '请求参数错误')
      return Promise.reject(error)
    }

    if (!window.navigator.onLine) {
      ElMessage.error('网络连接已断开，请检查网络')
      return Promise.reject(error)
    }

    ElMessage.error(data?.message || '网络请求失败: ' + error.message)
    return Promise.reject(error)
  }
)

export function get(url, params = {}) {
  return service.get(url, { params })
}

export function post(url, data = {}) {
  return service.post(url, data)
}

export function put(url, data = {}) {
  return service.put(url, data)
}

export function del(url, data = {}) {
  return service.delete(url, { data })
}

export default service
