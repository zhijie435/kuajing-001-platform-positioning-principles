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

const GUARD_BLOCK_CONFIG = {
  platform: {
    range: [4001, 4003],
    title: '平台定位限制',
    icon: '🚫',
    color: '#e6a23c',
    alertType: 'warning',
    footer: '如需访问该功能，请切换至对应入口端登录',
    typeLabels: {
      platform_type_missing: '入口端标识缺失',
      platform_type_invalid: '入口端标识无效',
      platform_boundary_violation: '平台定位越界'
    },
    extractViolations: (resData) => {
      const detail = resData?.detail || {}
      return Array.isArray(detail.violations) ? detail.violations : []
    }
  },
  commercial: {
    range: [4100, 4199],
    title: '商用边界限制',
    icon: '⚠️',
    color: '#e6a23c',
    alertType: 'warning',
    footer: '如需开通请联系商务部门',
    typeLabels: {
      invalid_license: 'License 签名无效',
      license_expired: 'License 已过期',
      feature_out_of_boundary: '功能超出版本边界',
      user_limit_exceeded: '用户数已达上限',
      client_limit_exceeded: '客户数已达上限',
      trial_expired: '试用期已结束'
    },
    extractViolations: (resData) => {
      const detail = resData?.detail
      if (Array.isArray(detail)) return detail
      if (Array.isArray(resData?.detail?.violations)) return resData.detail.violations
      return []
    }
  },
  redline: {
    range: [4500, 4599],
    title: '安全红线触发',
    icon: '🚫',
    color: '#f56c6c',
    alertType: 'error',
    footer: '此为系统安全防线，请遵守红线规则',
    typeLabels: {},
    extractViolations: (resData) => []
  }
}

function formatViolationData(data) {
  if (!data || typeof data !== 'object' || !Object.keys(data).length) return ''
  return Object.entries(data).map(([k, val]) => {
    if (Array.isArray(val)) return `${k}: ${val.join('、')}`
    return `${k}: ${val}`
  }).join(' / ')
}

function buildViolationHtml(violations, typeLabels) {
  if (!violations.length) return ''
  return violations.map(v => {
    const typeLabel = typeLabels[v.type] || v.type
    const dataStr = formatViolationData(v.data)
    return `<div style="font-size:12px;color:#909399;margin-top:4px;padding-left:12px">· ${typeLabel}${dataStr ? `（${dataStr}）` : ''}</div>`
  }).join('')
}

function showGuardBlock(blockType, code, msg, resData) {
  const cfg = GUARD_BLOCK_CONFIG[blockType]
  if (!cfg) return false

  const violations = cfg.extractViolations(resData)
  const violationHtml = buildViolationHtml(violations, cfg.typeLabels)

  const detailLine = blockType === 'redline' && resData?.detail
    ? `<div style="margin-top:8px;font-size:12px;color:#909399">详情：${JSON.stringify(resData.detail)}</div>`
    : ''

  ElMessageBox.alert(
    `<div style="line-height:1.8">
      <div style="font-weight:bold;margin-bottom:8px;color:${cfg.color}">${cfg.icon} ${cfg.title}</div>
      <div>${msg}</div>
      ${violationHtml}
      ${detailLine}
      <div style="margin-top:8px;font-size:12px;color:#909399">${cfg.footer}，错误码：${code}</div>
    </div>`,
    '访问受限',
    {
      confirmButtonText: '我知道了',
      type: cfg.alertType,
      dangerouslyUseHTMLString: true
    }
  )
  return true
}

function matchGuardBlock(code) {
  for (const [blockType, cfg] of Object.entries(GUARD_BLOCK_CONFIG)) {
    if (code >= cfg.range[0] && code <= cfg.range[1]) {
      return blockType
    }
  }
  return null
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

    const guardBlock = matchGuardBlock(code)
    if (guardBlock) {
      showGuardBlock(guardBlock, code, msg, res.data)
      return Promise.reject(new Error(msg))
    }

    if (code === 403) {
      ElMessage.error({ message: '⛔ 无权限访问: ' + msg, duration: 5000 })
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
    const msg = data?.message || '请求异常'

    if (code && code >= 4600 && code < 4700 && data?.data?.rollback) {
      return Promise.reject({
        message: data.message,
        code: code,
        data: data.data,
        response: { data: data }
      })
    }

    const guardBlock = code ? matchGuardBlock(code) : null
    if (guardBlock) {
      showGuardBlock(guardBlock, code, msg, data?.data)
      return Promise.reject(new Error(msg))
    }

    if (status === 401 || code === 401 || code === 4201) {
      const userStore = useUserStore()
      userStore.logout()
      router.push({ name: 'login' })
      ElMessage.error('登录已失效，请重新登录')
      return Promise.reject(error)
    }

    if (status === 403 || code === 403) {
      ElMessage.error(msg || '无权限访问该资源')
      return Promise.reject(error)
    }

    if (status === 429) {
      ElMessage.warning('请求过于频繁，请稍后再试')
      return Promise.reject(error)
    }

    if (status === 400 && !code) {
      ElMessage.error(data?.message || '请求参数错误')
      return Promise.reject(error)
    }

    if (code) {
      ElMessage.error(msg)
      return Promise.reject(new Error(msg))
    }

    if (!window.navigator.onLine) {
      ElMessage.error('网络连接已断开，请检查网络')
      return Promise.reject(error)
    }

    ElMessage.error(msg || '网络请求失败: ' + error.message)
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
