import axios from 'axios'
import { ElMessage, ElMessageBox } from 'element-plus'
import router from '@/router'

const GUARD_BLOCK_CONFIG = {
  4001: {
    type: 'platform',
    title: '平台校验失败',
    message: '缺少平台标识，请检查您的访问来源',
    action: 'redirect_login'
  },
  4002: {
    type: 'platform',
    title: '平台不支持',
    message: '当前平台不被支持，请联系系统管理员',
    action: 'show_message'
  },
  4003: {
    type: 'platform',
    title: '签名验证失败',
    message: '请求签名验证失败，请刷新页面重试',
    action: 'refresh'
  },
  4004: {
    type: 'platform',
    title: '请求已过期',
    message: '请求时间戳已过期，请刷新页面重试',
    action: 'refresh'
  },
  4101: {
    type: 'commercial',
    title: '许可证缺失',
    message: '缺少有效的许可证信息，请激活许可证后继续使用',
    action: 'show_activate'
  },
  4102: {
    type: 'commercial',
    title: '许可证无效',
    message: '许可证无效，请检查许可证密钥是否正确',
    action: 'show_activate'
  },
  4103: {
    type: 'commercial',
    title: '许可证已过期',
    message: '您的许可证已过期，请续期后继续使用',
    action: 'show_activate'
  },
  4104: {
    type: 'commercial',
    title: '许可证已停用',
    message: '您的许可证已被停用，请联系系统管理员',
    action: 'show_message'
  },
  4105: {
    type: 'commercial',
    title: '用户数超限',
    message: '用户数量已超出许可证限制，请升级许可证套餐',
    action: 'show_message'
  },
  4106: {
    type: 'commercial',
    title: '客户数超限',
    message: '客户数量已超出许可证限制，请升级许可证套餐',
    action: 'show_message'
  },
  4107: {
    type: 'commercial',
    title: '功能受限',
    message: '当前许可证不支持该功能，请升级许可证套餐',
    action: 'show_message'
  },
  4108: {
    type: 'commercial',
    title: '调用频率超限',
    message: 'API调用次数已达今日上限，请明日再试',
    action: 'show_message'
  },
  4201: {
    type: 'redline',
    title: '每日调用上限',
    message: '每日API调用次数已达红线，请合理安排使用',
    action: 'show_message'
  },
  4202: {
    type: 'redline',
    title: '敏感操作拦截',
    message: '该操作属于敏感操作，已被红线守护拦截',
    action: 'show_confirm'
  },
  4203: {
    type: 'redline',
    title: '数据量超限',
    message: '操作数据量已超出红线限制，请减少数据量后重试',
    action: 'show_message'
  },
  4204: {
    type: 'redline',
    title: '风险用户拦截',
    message: '检测到风险行为，已被红线守护临时拦截',
    action: 'logout'
  },
  4205: {
    type: 'redline',
    title: '异常行为检测',
    message: '检测到异常操作行为，请规范操作',
    action: 'show_message'
  },
  4206: {
    type: 'redline',
    title: '批量操作超限',
    message: '批量操作数量已超出红线限制',
    action: 'show_message'
  }
}

const service = axios.create({
  baseURL: '/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json'
  }
})

service.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`
    }

    const platform = getPlatform()
    config.headers['X-Platform'] = platform

    const licenseKey = localStorage.getItem('license_key')
    if (licenseKey) {
      config.headers['X-License'] = licenseKey
    }

    const timestamp = Math.floor(Date.now() / 1000).toString()
    config.headers['X-Timestamp'] = timestamp

    const signature = generateSignature(config.method, config.url, timestamp)
    config.headers['X-Signature'] = signature

    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

service.interceptors.response.use(
  (response) => {
    const guardResult = response.headers['x-guard-result']
    if (guardResult) {
      try {
        const decoded = atob(guardResult)
        const guardData = JSON.parse(decoded)
        response.guardResult = guardData
      } catch (e) {
        // ignore
      }
    }

    const res = response.data

    if (res.code !== undefined && res.code !== 0) {
      const blockConfig = GUARD_BLOCK_CONFIG[res.code]

      if (blockConfig) {
        handleGuardBlock(res.code, blockConfig, res)
        return Promise.reject(new Error(res.message || '守护拦截'))
      }

      if (res.code === 401) {
        ElMessage.error('登录已过期，请重新登录')
        localStorage.removeItem('token')
        router.push('/login')
        return Promise.reject(new Error(res.message || '未授权'))
      }

      if (res.code === 403) {
        ElMessage.error('没有访问权限')
        return Promise.reject(new Error(res.message || '权限不足'))
      }

      ElMessage.error(res.message || '请求失败')
      return Promise.reject(new Error(res.message || '请求失败'))
    }

    return res
  },
  (error) => {
    if (error.response) {
      const status = error.response.status
      const data = error.response.data

      if (data && data.code) {
        const blockConfig = GUARD_BLOCK_CONFIG[data.code]
        if (blockConfig) {
          handleGuardBlock(data.code, blockConfig, data)
          return Promise.reject(error)
        }
      }

      switch (status) {
        case 401:
          ElMessage.error('登录已过期，请重新登录')
          localStorage.removeItem('token')
          router.push('/login')
          break
        case 403:
          ElMessage.error('没有访问权限')
          break
        case 404:
          ElMessage.error('请求的资源不存在')
          break
        case 500:
          ElMessage.error('服务器内部错误')
          break
        default:
          ElMessage.error(error.message || '网络错误')
      }
    } else {
      ElMessage.error(error.message || '网络连接失败')
    }

    return Promise.reject(error)
  }
)

function handleGuardBlock(code, config, responseData) {
  const { type, title, message, action } = config

  switch (action) {
    case 'redirect_login':
      ElMessageBox.alert(message, title, {
        confirmButtonText: '去登录',
        type: 'warning',
        callback: () => {
          router.push('/login')
        }
      })
      break

    case 'show_message':
      ElMessage({
        message: `${title}: ${message}`,
        type: type === 'redline' ? 'error' : 'warning',
        duration: 5000
      })
      break

    case 'refresh':
      ElMessageBox.confirm(`${message}，是否刷新页面？`, title, {
        confirmButtonText: '刷新',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        window.location.reload()
      }).catch(() => {})
      break

    case 'show_activate':
      ElMessageBox.alert(message, title, {
        confirmButtonText: '去激活',
        type: 'warning',
        callback: () => {
          router.push('/license')
        }
      })
      break

    case 'show_confirm':
      ElMessageBox.confirm(message, title, {
        confirmButtonText: '确认继续',
        cancelButtonText: '取消',
        type: 'warning',
        confirmButtonClass: 'el-button--danger'
      }).then(() => {
        // TODO: 可加入二次确认后的放行逻辑
      }).catch(() => {})
      break

    case 'logout':
      ElMessageBox.alert(message, title, {
        confirmButtonText: '重新登录',
        type: 'error',
        callback: () => {
          localStorage.removeItem('token')
          router.push('/login')
        }
      })
      break

    default:
      ElMessage.warning(`${title}: ${message}`)
  }
}

function getPlatform() {
  const ua = navigator.userAgent.toLowerCase()
  
  if (ua.includes('miniprogram') || ua.includes('micromessenger')) {
    return 'miniapp'
  }
  
  if (ua.includes('mobile') || ua.includes('android') || ua.includes('iphone')) {
    return 'mobile'
  }
  
  const path = window.location.pathname
  if (path.includes('/admin')) {
    return 'admin'
  }
  
  return 'pc'
}

function generateSignature(method, url, timestamp) {
  const platformSecrets = {
    pc: 'pc-secret-key',
    mobile: 'mobile-secret-key',
    admin: 'admin-secret-key',
    miniapp: 'miniapp-secret-key'
  }
  
  const platform = getPlatform()
  const secret = platformSecrets[platform] || 'pc-secret-key'
  
  const path = url.startsWith('/api') ? url : '/api' + url
  const payload = method.toUpperCase() + '\n' + path + '\n' + timestamp
  
  return simpleHmacSha256(secret, payload)
}

function simpleHmacSha256(key, data) {
  let hash = 0
  for (let i = 0; i < key.length; i++) {
    hash = ((hash << 5) - hash) + key.charCodeAt(i)
    hash = hash & hash
  }
  for (let i = 0; i < data.length; i++) {
    hash = ((hash << 5) - hash) + data.charCodeAt(i)
    hash = hash & hash
  }
  return Math.abs(hash).toString(16).padStart(32, '0') + hash.toString(16).slice(-32)
}

export default service
export { GUARD_BLOCK_CONFIG }
