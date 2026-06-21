<template>
  <el-container class="sales-layout">
    <el-aside :width="sidebarWidth" class="sales-sidebar">
      <div class="logo-area">
        <el-icon size="28" color="#67c23a"><TrendCharts /></el-icon>
        <span v-if="!appStore.sidebarCollapsed" class="logo-text">CRM 销售端</span>
      </div>
      <el-menu
        :default-active="route.path"
        :collapse="appStore.sidebarCollapsed"
        :collapse-transition="false"
        router
        background-color="#002e1a"
        text-color="rgba(255,255,255,0.75)"
        active-text-color="#67c23a"
      >
        <el-menu-item v-for="m in menus" :key="m.path" :index="m.path">
          <el-icon><component :is="m.icon" /></el-icon>
          <template #title>{{ m.title }}</template>
        </el-menu-item>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header class="sales-header">
        <div class="header-left">
          <el-icon class="toggle-btn" @click="appStore.toggleSidebar()">
            <Fold v-if="!appStore.sidebarCollapsed" />
            <Expand v-else />
          </el-icon>
          <el-breadcrumb separator="/">
            <el-breadcrumb-item>首页</el-breadcrumb-item>
            <el-breadcrumb-item>{{ currentPage }}</el-breadcrumb-item>
          </el-breadcrumb>
        </div>

        <div class="header-right">
          <el-tag v-if="licenseInfo && licenseInfo.days_left <= 30" type="warning" size="small" effect="light">
            ⚠️ License 剩余 {{ licenseInfo.days_left }} 天
          </el-tag>
          <el-dropdown trigger="click">
            <div class="user-info">
              <el-avatar :size="32" style="background:#67c23a">
                {{ userStore.userInfo?.name?.charAt(0) || 'S' }}
              </el-avatar>
              <span class="user-name">{{ userStore.userInfo?.name }}</span>
              <el-tag type="success" size="small" effect="plain">销售</el-tag>
            </div>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item @click="handleLogout">
                  <el-icon><SwitchButton /></el-icon>退出登录
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <el-main class="sales-main">
        <div v-if="licenseInfo && licenseInfo.days_left <= 15" class="redline-banner">
          <el-icon><Warning /></el-icon>
          <div class="text">
            系统 License 将在 <b>{{ licenseInfo.days_left }} 天</b> 后到期，
            请及时联系管理员续费。
          </div>
        </div>
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/store/user'
import { useAppStore } from '@/store/app'
import {
  TrendCharts, DataBoard, UserFilled, ChatDotRound, Money,
  Fold, Expand, SwitchButton, Warning
} from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const appStore = useAppStore()

const sidebarWidth = computed(() => appStore.sidebarCollapsed ? '64px' : '210px')

const menus = [
  { path: '/dashboard', title: '工作台', icon: 'DataBoard' },
  { path: '/customer', title: '客户管理', icon: 'UserFilled' },
  { path: '/followup', title: '跟进记录', icon: 'ChatDotRound' },
  { path: '/opportunity', title: '商机管理', icon: 'Money' }
]

const currentPage = computed(() => {
  const menu = menus.find(m => m.path === route.path)
  return menu?.title || ''
})

const licenseInfo = computed(() => userStore.licenseInfo)

async function handleLogout() {
  await userStore.doLogout()
  router.push('/login')
}
</script>

<style lang="scss" scoped>
.sales-layout { height: 100%; }
.sales-sidebar {
  background: #002e1a;
  transition: width 0.2s;
  overflow: hidden;

  :deep(.el-menu) { border-right: none; }

  .logo-area {
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #fff;
    border-bottom: 1px solid #0d4a2a;

    .logo-text {
      font-size: 16px;
      font-weight: 600;
    }
  }
}

.sales-header {
  background: #fff;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 20px;
  border-bottom: 1px solid #ebeef5;

  .header-left {
    display: flex;
    align-items: center;
    gap: 16px;
    .toggle-btn {
      font-size: 20px;
      cursor: pointer;
      color: #606266;
    }
  }
  .header-right {
    display: flex;
    align-items: center;
    gap: 16px;
    .user-info {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      .user-name { font-size: 14px; color: #303133; }
    }
  }
}

.sales-main {
  background: var(--crm-content-bg);
  padding: 0;
  overflow: hidden;
}
</style>
