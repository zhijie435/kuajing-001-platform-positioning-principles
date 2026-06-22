import { defineStore } from 'pinia'

export const useAppStore = defineStore('app', {
  state: () => ({
    sidebarCollapsed: false,
    currentPage: '',
    redlineAlerts: [],
    globalLoading: false
  }),

  actions: {
    toggleSidebar() {
      this.sidebarCollapsed = !this.sidebarCollapsed
    },
    addRedlineAlert(alert) {
      this.redlineAlerts.push({
        id: Date.now(),
        time: new Date().toLocaleString(),
        ...alert
      })
    },
    setGlobalLoading(val) {
      this.globalLoading = val
    }
  }
})
