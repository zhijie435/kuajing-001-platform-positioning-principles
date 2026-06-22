import { defineStore } from 'pinia'
import { login, logout, checkAuth, platformInfo } from '@/api/auth'
import { ElMessage } from 'element-plus'

const platform = document.querySelector('meta[name="platform"]')?.content
  || import.meta.env.VITE_PLATFORM
  || 'sales'

export const useUserStore = defineStore('user', {
  state: () => ({
    token: localStorage.getItem('crm_token') || '',
    userInfo: JSON.parse(localStorage.getItem('crm_user_info') || 'null'),
    platform: platform,
    platformData: null,
    licenseInfo: null
  }),

  getters: {
    isLoggedIn: (state) => !!state.token,
    userName: (state) => state.userInfo?.name || '',
    userRole: (state) => state.userInfo?.role || '',
    userPlatform: (state) => state.userInfo?.platform || platform
  },

  actions: {
    async doLogin(loginData) {
      const res = await login({ ...loginData, platform: this.platform })
      if (res.code === 0) {
        this.token = res.data.token
        this.userInfo = res.data.user
        localStorage.setItem('crm_token', res.data.token)
        localStorage.setItem('crm_user_info', JSON.stringify(res.data.user))
        ElMessage.success(`欢迎回来，${res.data.user.name}！`)
      }
      return res
    },

    async doLogout() {
      try {
        await logout()
      } catch (e) {
      }
      this.logout()
    },

    logout() {
      this.token = ''
      this.userInfo = null
      localStorage.removeItem('crm_token')
      localStorage.removeItem('crm_user_info')
      localStorage.removeItem('crm_redirect')
    },

    async checkLogin() {
      if (!this.token) return false
      try {
        const res = await checkAuth()
        return res.code === 0
      } catch (e) {
        return false
      }
    },

    async fetchPlatformInfo() {
      try {
        const res = await platformInfo()
        if (res.code === 0) {
          this.platformData = res.data.platform
          this.licenseInfo = res.data.license
          if (res.data.license && res.data.license.days_left <= 15) {
            setTimeout(() => {
              ElMessage.warning({
                message: `⚠️ License 剩余 ${res.data.license.days_left} 天到期，请联系商务续费`,
                duration: 8000
              })
            }, 1000)
          }
        }
      } catch (e) {
        console.warn('获取平台信息失败:', e)
      }
    }
  }
})
