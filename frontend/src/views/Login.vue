<template>
  <div class="login-wrapper">
    <div class="login-bg"></div>
    <div class="login-card">
      <div class="login-header">
        <div class="logo">
          <el-icon size="36"><TrendCharts /></el-icon>
          <span class="title">{{ platformTitle }}</span>
        </div>
        <p class="subtitle">CRM 客户跟进系统</p>
        <p class="platform-tag" :class="platform">
          <el-icon><Platform /></el-icon>
          {{ platformLabel }}入口
        </p>
      </div>

      <el-form
        ref="loginForm"
        :model="form"
        :rules="rules"
        label-position="top"
        @keyup.enter="handleLogin"
      >
        <el-form-item prop="username" label="账号">
          <el-input
            v-model="form.username"
            placeholder="请输入登录账号"
            :prefix-icon="User"
            size="large"
          />
        </el-form-item>

        <el-form-item prop="password" label="密码">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="请输入登录密码"
            show-password
            :prefix-icon="Lock"
            size="large"
            @keyup.enter="handleLogin"
          />
        </el-form-item>

        <el-form-item>
          <el-button
            type="primary"
            size="large"
            :loading="loading"
            style="width: 100%"
            @click="handleLogin"
          >
            登 录
          </el-button>
        </el-form-item>
      </el-form>

      <div class="account-tips">
        <div class="tip-title">演示账号：</div>
        <div class="tip-row" v-for="acc in demoAccounts">
          <span class="acc-platform">{{ acc.label }}</span>
          <span class="acc-value">{{ acc.user }} / {{ acc.pass }}</span>
        </div>
      </div>

      <div class="security-indicator">
        <el-icon size="14"><Warning /></el-icon>
        <span>所有连接已启用设备指纹+身份校验</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { User, Lock, TrendCharts, Platform, Warning } from '@element-plus/icons-vue'
import { useUserStore } from '@/store/user'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

const platform = computed(() =>
  document.querySelector('meta[name="platform"]')?.content || 'sales'
)

const platformTitle = computed(() => {
  const map = { admin: '管理端', sales: '销售端', client: '客户端' }
  return map[platform.value] || '销售端'
})

const platformLabel = computed(() => {
  const map = { admin: '管理', sales: '销售', client: '客户' }
  return map[platform.value] || '销售'
})

const demoAccounts = computed(() => {
  const map = {
    admin: [{ label: '管理员', user: 'admin', pass: 'admin123' }],
    sales: [
      { label: '销售主管', user: 'sales01', pass: 'sales123' },
      { label: '销售代表', user: 'sales02', pass: 'sales123' }
    ],
    client: [{ label: '客户用户', user: 'client01', pass: 'client123' }]
  }
  return map[platform.value] || []
})

const loginForm = ref(null)
const loading = ref(false)

const form = reactive({
  username: '',
  password: ''
})

const rules = {
  username: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }]
}

async function handleLogin() {
  loginForm.value?.validate(async valid => {
    if (!valid) return
    loading.value = true
    try {
      await userStore.doLogin(form)
      const redirect = route.query.redirect || '/'
      router.push(redirect)
    } catch (e) {
    } finally {
      loading.value = false
    }
  })
}

onMounted(() => {
  if (route.query.error) {
    ElMessage.error(route.query.error)
  }
})
</script>

<style lang="scss" scoped>
.login-wrapper {
  width: 100%;
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.login-bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 20% 30%, #1890ff22 0%, transparent 50%),
    radial-gradient(circle at 80% 70%, #722ed122 0%, transparent 50%),
    linear-gradient(135deg, #001529 0%, #0a2a4a 100%);
}

.login-card {
  width: 420px;
  background: #fff;
  border-radius: 12px;
  padding: 40px 36px 32px;
  box-shadow: 0 20px 60px rgba(0, 21, 41, 0.3);
  position: relative;
  z-index: 1;
}

.login-header {
  text-align: center;
  margin-bottom: 32px;

  .logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: #1890ff;
    margin-bottom: 8px;

    .title {
      font-size: 24px;
      font-weight: 700;
      letter-spacing: 2px;
    }
  }

  .subtitle {
    font-size: 13px;
    color: #909399;
    margin-bottom: 12px;
  }

  .platform-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    background: #ecf5ff;
    color: #409eff;

    &.admin { background: #fef0f0; color: #f56c6c; }
    &.sales { background: #f0f9eb; color: #67c23a; }
    &.client { background: #fdf6ec; color: #e6a23c; }
  }
}

.account-tips {
  background: #f5f7fa;
  border-radius: 6px;
  padding: 12px;
  margin-top: 8px 0;

  .tip-title {
    font-size: 12px;
    color: #909399;
    margin-bottom: 8px;
  }

  .tip-row {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    padding: 2px 0;
    color: #606266;

    .acc-platform {
      color: #909399;
    }
    .acc-value {
      font-family: monospace;
    }
  }
}

.security-indicator {
  margin-top: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
  color: #909399;
}
</style>
