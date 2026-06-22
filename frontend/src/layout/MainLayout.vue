<template>
  <el-container class="main-layout">
    <el-aside width="220px" class="sidebar">
      <div class="logo">
        <el-icon size="28" color="#409eff"><Shield /></el-icon>
        <span class="logo-text">CRM红线守护</span>
      </div>
      <el-menu
        :default-active="activeMenu"
        :router="true"
        background-color="#304156"
        text-color="#bfcbd9"
        active-text-color="#409eff"
      >
        <el-menu-item index="/dashboard">
          <el-icon><Odometer /></el-icon>
          <span>工作台</span>
        </el-menu-item>
        <el-menu-item index="/customer">
          <el-icon><User /></el-icon>
          <span>客户管理</span>
        </el-menu-item>
        <el-menu-item index="/follow">
          <el-icon><ChatDotRound /></el-icon>
          <span>跟进记录</span>
        </el-menu-item>
        <el-sub-menu index="admin">
          <template #title>
            <el-icon><Setting /></el-icon>
            <span>系统管理</span>
          </template>
          <el-menu-item index="/audit">
            <el-icon><Document /></el-icon>
            <span>审计管理</span>
          </el-menu-item>
          <el-menu-item index="/license">
            <el-icon><Key /></el-icon>
            <span>许可证管理</span>
          </el-menu-item>
          <el-menu-item index="/redline">
            <el-icon><Warning /></el-icon>
            <span>红线配置</span>
          </el-menu-item>
          <el-menu-item index="/guard">
            <el-icon><Shield /></el-icon>
            <span>守护信息</span>
          </el-menu-item>
        </el-sub-menu>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header class="header">
        <div class="header-left">
          <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/dashboard' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>{{ currentPageTitle }}</el-breadcrumb-item>
          </el-breadcrumb>
        </div>
        <div class="header-right">
          <div class="guard-status-indicator" :class="guardStatus">
            <el-icon><CircleCheck v-if="guardStatus === 'success'" />
            <CircleClose v-else /></el-icon>
            <span>红线守护{{ guardStatus === 'success' ? '正常' : '异常' }}</span>
          </div>
          <el-dropdown @command="handleCommand">
            <span class="user-info">
              <el-avatar :size="32" icon="UserFilled" />
              <span class="username">{{ username }}</span>
              <el-icon><ArrowDown /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="profile">个人中心</el-dropdown-item>
                <el-dropdown-item command="logout" divided>退出登录</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <el-main class="main-content">
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
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { verifyGuard } from '@/api'
import { ElMessage, ElMessageBox } from 'element-plus'

const route = useRoute()
const router = useRouter()

const username = ref('admin')
const guardStatus = ref('success')

const activeMenu = computed(() => route.path)
const currentPageTitle = computed(() => route.meta.title || '首页')

onMounted(() => {
  const userInfo = localStorage.getItem('user_info')
  if (userInfo) {
    try {
      const user = JSON.parse(userInfo)
      username.value = user.real_name || user.username
    } catch (e) {}
  }

  checkGuardStatus()
})

async function checkGuardStatus() {
  try {
    const res = await verifyGuard()
    if (res.data && res.data.all_passed) {
      guardStatus.value = 'success'
    } else {
      guardStatus.value = 'error'
    }
  } catch (e) {
    guardStatus.value = 'warning'
  }
}

function handleCommand(command) {
  if (command === 'logout') {
    ElMessageBox.confirm('确定要退出登录吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }).then(() => {
      localStorage.removeItem('token')
      localStorage.removeItem('user_info')
      router.push('/login')
      ElMessage.success('退出成功')
    }).catch(() => {})
  }
}
</script>

<style scoped>
.main-layout {
  height: 100vh;
}

.sidebar {
  background-color: #304156;
  overflow: hidden;
}

.logo {
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  background-color: #2b3a4f;
}

.logo-text {
  color: #fff;
  font-size: 16px;
  font-weight: 600;
}

.header {
  background: #fff;
  border-bottom: 1px solid #e4e7ed;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
}

.header-left {
  flex: 1;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 20px;
}

.guard-status-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 16px;
  font-size: 13px;
}

.guard-status-indicator.success {
  background: #f0f9eb;
  color: #67c23a;
}

.guard-status-indicator.error {
  background: #fef0f0;
  color: #f56c6c;
}

.guard-status-indicator.warning {
  background: #fdf6ec;
  color: #e6a23c;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.username {
  font-size: 14px;
  color: #606266;
}

.main-content {
  background-color: #f5f7fa;
  padding: 20px;
  overflow-y: auto;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
