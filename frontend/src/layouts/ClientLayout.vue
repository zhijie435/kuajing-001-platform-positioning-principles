<template>
  <el-container class="client-layout">
    <el-header class="client-header">
      <div class="brand">
        <el-icon size="26" color="#e6a23c"><TrendCharts /></el-icon>
        <span class="brand-text">CRM 客户服务平台</span>
      </div>

      <el-menu mode="horizontal" :default-active="route.path" router class="client-nav">
        <el-menu-item index="/profile">
          <el-icon><UserFilled /></el-icon>我的信息
        </el-menu-item>
        <el-menu-item index="/contracts">
          <el-icon><Document /></el-icon>我的合同
        </el-menu-item>
      </el-menu>

      <div class="header-right">
        <el-tag v-if="licenseInfo && licenseInfo.days_left <= 30" type="warning" size="small" effect="light">
          ⚠️ License 剩余 {{ licenseInfo.days_left }} 天
        </el-tag>
        <el-dropdown trigger="click">
          <div class="user-info">
            <el-avatar :size="32" style="background:#e6a23c">
              {{ userStore.userInfo?.name?.charAt(0) || 'C' }}
            </el-avatar>
            <span class="user-name">{{ userStore.userInfo?.name }}</span>
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

    <el-main class="client-main">
      <div v-if="licenseInfo && licenseInfo.days_left <= 15" class="redline-banner">
        <el-icon><Warning /></el-icon>
        <div class="text">
          您的服务许可将在 <b>{{ licenseInfo.days_left }} 天</b> 后到期，
          请联系您的销售顾问续约。
        </div>
      </div>
      <router-view />
    </el-main>
  </el-container>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/store/user'
import {
  TrendCharts, UserFilled, Document, SwitchButton, Warning
} from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const licenseInfo = computed(() => userStore.licenseInfo)

async function handleLogout() {
  await userStore.doLogout()
  router.push('/login')
}
</script>

<style lang="scss" scoped>
.client-layout {
  height: 100%;
}

.client-header {
  background: linear-gradient(135deg, #fff7e6 0%, #ffe7ba 100%);
  display: flex;
  align-items: center;
  padding: 0 30px;
  gap: 40px;
  border-bottom: 1px solid #ffd591;
  height: 60px;

  .brand {
    display: flex;
    align-items: center;
    gap: 8px;

    .brand-text {
      font-size: 18px;
      font-weight: 700;
      color: #ad6800;
      letter-spacing: 1px;
    }
  }

  .client-nav {
    flex: 1;
    background: transparent;
    border-bottom: none;

    :deep(.el-menu-item) {
      border-bottom: 2px solid transparent;
      height: 58px;
      line-height: 58px;
      color: #874d00;
    }
    :deep(.el-menu-item.is-active) {
      color: #ad6800;
      border-bottom-color: #ad6800;
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

.client-main {
  background: #fffbe6;
  padding: 0;
  overflow: hidden;
}
</style>
