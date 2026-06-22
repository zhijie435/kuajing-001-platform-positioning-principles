<template>
  <div class="login-container">
    <div class="login-box">
      <div class="login-header">
        <el-icon size="48" color="#409eff"><Shield /></el-icon>
        <h1 class="title">CRM客户跟进系统</h1>
        <p class="subtitle">红线守护 · 商用边界 · 三端校验</p>
      </div>

      <el-form
        ref="loginForm"
        :model="loginForm"
        :rules="loginRules"
        class="login-form"
        @submit.prevent="handleLogin"
      >
        <el-form-item prop="username">
          <el-input
            v-model="loginForm.username"
            placeholder="请输入用户名"
            size="large"
            :prefix-icon="User"
          />
        </el-form-item>

        <el-form-item prop="password">
          <el-input
            v-model="loginForm.password"
            type="password"
            placeholder="请输入密码"
            size="large"
            :prefix-icon="Lock"
            @keyup.enter="handleLogin"
          />
        </el-form-item>

        <el-form-item>
          <el-button
            type="primary"
            size="large"
            class="login-btn"
            :loading="loading"
            @click="handleLogin"
          >
            登 录
          </el-button>
        </el-form-item>
      </el-form>

      <div class="login-footer">
        <span class="tip">演示账号: admin / admin123</span>
      </div>
    </div>

    <div class="guard-decor">
      <div class="guard-badge">
        <el-icon size="24"><Shield /></el-icon>
        <span>红线守护系统</span>
      </div>
      <div class="platform-tags">
        <el-tag type="primary" size="small">PC端</el-tag>
        <el-tag type="success" size="small">移动端</el-tag>
        <el-tag type="warning" size="small">管理端</el-tag>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { User, Lock } from '@element-plus/icons-vue'
import { login } from '@/api'

const router = useRouter()
const loginForm = ref(null)
const loading = ref(false)

const form = reactive({
  username: 'admin',
  password: 'admin123'
})

const rules = {
  username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }]
}

async function handleLogin() {
  if (!loginForm.value) return

  await loginForm.value.validate(async (valid) => {
    if (!valid) return

    loading.value = true
    try {
      const res = await login(form)
      
      if (res.data && res.data.token) {
        localStorage.setItem('token', res.data.token)
        localStorage.setItem('user_info', JSON.stringify(res.data.user))
        if (res.data.license_key) {
          localStorage.setItem('license_key', res.data.license_key)
        }
        
        ElMessage.success('登录成功')
        router.push('/dashboard')
      }
    } catch (e) {
      console.error('登录失败:', e)
    } finally {
      loading.value = false
    }
  })
}
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  position: relative;
  overflow: hidden;
}

.login-container::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
  animation: rotate 30s linear infinite;
}

@keyframes rotate {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.login-box {
  width: 420px;
  padding: 40px;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  position: relative;
  z-index: 1;
  backdrop-filter: blur(10px);
}

.login-header {
  text-align: center;
  margin-bottom: 30px;
}

.title {
  font-size: 24px;
  color: #303133;
  margin: 16px 0 8px;
  font-weight: 600;
}

.subtitle {
  font-size: 14px;
  color: #909399;
  margin: 0;
}

.login-form {
  margin-top: 20px;
}

.login-btn {
  width: 100%;
  height: 44px;
  font-size: 16px;
}

.login-footer {
  text-align: center;
  margin-top: 20px;
}

.tip {
  font-size: 12px;
  color: #909399;
}

.guard-decor {
  position: absolute;
  top: 40px;
  right: 40px;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 12px;
}

.guard-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 20px;
  color: #fff;
  font-size: 14px;
  backdrop-filter: blur(4px);
}

.platform-tags {
  display: flex;
  gap: 8px;
}
</style>
