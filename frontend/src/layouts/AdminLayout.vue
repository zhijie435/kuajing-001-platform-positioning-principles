<template>
  <el-container class="admin-layout">
    <el-aside :width="sidebarWidth" class="admin-sidebar">
      <div class="logo-area">
        <el-icon size="28"><TrendCharts /></el-icon>
        <span v-if="!appStore.sidebarCollapsed" class="logo-text">CRM 管理端</span>
      </div>
      <el-menu
        :default-active="route.path"
        :collapse="appStore.sidebarCollapsed"
        :collapse-transition="false"
        router
        background-color="#001529"
        text-color="rgba(255,255,255,0.75)"
        active-text-color="#409eff"
      >
        <el-menu-item v-for="m in menus" :key="m.path" :index="m.path">
          <el-icon><component :is="m.icon" /></el-icon>
          <template #title>{{ m.title }}</template>
        </el-menu-item>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header class="admin-header">
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
              <el-avatar :size="32" style="background:#409eff">
                {{ userStore.userInfo?.name?.charAt(0) || 'U' }}
              </el-avatar>
              <span class="user-name">{{ userStore.userInfo?.name }}</span>
              <el-tag type="danger" size="small" effect="plain">管理员</el-tag>
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

      <el-main class="admin-main">
        <div v-if="licenseInfo && licenseInfo.days_left <= 15" class="redline-banner">
          <el-icon><Warning /></el-icon>
          <div class="text">
            系统 License 将在 <b>{{ licenseInfo.days_left }} 天</b> 后到期 ({{ licenseInfo.expire }})，
            请及时联系商务部门续费，避免影响系统正常使用。
          </div>
        </div>
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
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
  TrendCharts, DataBoard, User, UserFilled, Key, Document,
  Fold, Expand, SwitchButton, Warning
} from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const appStore = useAppStore()

const sidebarWidth = computed(() => appStore.sidebarCollapsed ? '64px' : '210px')

const menus = [
  { path: '/dashboard', title: '运营总览', icon: 'DataBoard' },
  { path: '/user', title: '用户管理', icon: 'User' },
  { path: '/customer', title: '客户管理', icon: 'UserFilled' },
  { path: '/license', title: 'License 管理', icon: 'Key' },
  { path: '/audit', title: '审计日志', icon: 'Document' }
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
.admin-layout {
  height: 100%;
}

.admin-sidebar {
  background: #001529;
  transition: width 0.2s;
  overflow: hidden;

  :deep(.el-menu) {
    border-right: none;
  }

  .logo-area {
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #fff;
    border-bottom: 1px solid #1f3a5f;

    .logo-text {
      font-size: 16px;
      font-weight: 600;
      letter-spacing: 1px;
    }
  }
}

.admin-header {
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

      .user-name {
        font-size: 14px;
        color: #303133;
      }
    }
  }
}

.admin-main {
  background: var(--crm-content-bg);
  padding: 0;
  overflow: hidden;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
