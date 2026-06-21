<template>
  <div class="page-container">
    <div class="page-header">
      <h2>License 管理</h2>
      <el-button type="primary" @click="showVerifyDialog">
        <el-icon><Key /></el-icon>验证 License
      </el-button>
    </div>

    <el-card shadow="never">
      <div class="license-header">
        <div class="license-icon">
          <el-icon size="64" color="#409eff"><Medal /></el-icon>
        </div>
        <div class="license-info">
          <div class="license-key">
            <el-tag :type="editionTagType" effect="dark" size="large">
              {{ license.edition }}
            </el-tag>
            <span class="key-text">{{ license.key }}</span>
          </div>
          <el-progress
            :percentage="licenseProgress"
            :status="license.days_left <= 30 ? 'exception' : 'success'"
            :stroke-width="14"
            style="width: 400px; margin-top: 12px"
          >
            <template #default="{ percentage }">
              <span style="font-size: 14px; color: #606266">
                剩余 <b>{{ license.days_left }}</b> 天 ({{ 100 - percentage }}%)
              </span>
            </template>
          </el-progress>
        </div>
      </div>

      <el-row :gutter="16" style="margin-top: 24px">
        <el-col :span="6">
          <div class="quota-item">
            <div class="quota-label">用户配额</div>
            <div class="quota-value">
              <b style="color:#409eff">{{ license.max_users }}</b> / 上限
            </div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="quota-item">
            <div class="quota-label">客户配额</div>
            <div class="quota-value">
              <b style="color:#67c23a">{{ license.max_clients.toLocaleString() }}</b> / 上限
            </div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="quota-item">
            <div class="quota-label">签发日期</div>
            <div class="quota-value" style="color:#909399">
              {{ license.issued_at || '-' }}
            </div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="quota-item">
            <div class="quota-label">到期日期</div>
            <div class="quota-value" :style="license.days_left <= 30 ? 'color:#f56c6c' : 'color:#606266'">
              {{ license.expire }}
            </div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never" style="margin-top: 20px">
      <template #header><b>商用功能边界</b></template>
      <el-table :data="featureTable" stripe>
        <el-table-column prop="feature" label="功能模块" width="200" />
        <el-table-column v-for="ed in editions" :key="ed.code" :label="ed.label" width="120" align="center">
          <template #default="{ row }">
            <el-icon :size="18" :color="row[ed.code] ? '#67c23a' : '#dcdfe6'">
              <component :is="row[ed.code] ? 'CircleCheck' : 'CircleClose'" />
            </el-icon>
          </template>
        </el-table-column>
        <el-table-column prop="desc" label="说明" />
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" title="验证 License Key" width="480px">
      <el-form label-width="100px">
        <el-form-item label="License Key" required>
          <el-input
            v-model="verifyForm.key"
            placeholder="CRM-LICENSE-YYYY-STD/PRO/ENT"
            size="large"
          />
        </el-form-item>
        <el-form-item>
          <el-alert
            title="格式示例：CRM-LICENSE-2026-STD / CRM-LICENSE-2026-PRO / CRM-LICENSE-2026-ENT"
            type="info"
            :closable="false"
            show-icon
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="doVerify" :loading="verifying">验证</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Key, Medal } from '@element-plus/icons-vue'
import { getLicenseInfo, verifyLicense } from '@/api'

const license = ref({
  key: '',
  edition: '',
  expire: '',
  days_left: 0,
  max_users: 0,
  max_clients: 0,
  features: [],
  issued_at: ''
})

const editions = [
  { code: 'standard', label: '标准版' },
  { code: 'professional', label: '专业版' },
  { code: 'enterprise', label: '企业版' }
]

const featureTable = ref([
  { feature: '客户管理', standard: true, professional: true, enterprise: true, desc: '客户信息录入、分级、跟进管理' },
  { feature: '跟进记录', standard: true, professional: true, enterprise: true, desc: '电话/拜访/微信/邮件记录' },
  { feature: '商机管理', standard: false, professional: true, enterprise: true, desc: '商机阶段、赢单率、漏斗分析' },
  { feature: '数据报表', standard: false, professional: true, enterprise: true, desc: '业绩报表、销售排行' },
  { feature: '高级分析', standard: false, false: false, enterprise: true, professional: false, desc: 'BI多维分析、自定义报表' },
  { feature: '系统定制', standard: false, professional: false, enterprise: true, desc: '工作流、字段自定义' },
  { feature: 'API 访问', standard: false, professional: false, enterprise: true, desc: '开放 REST API 接口' }
])

const licenseProgress = computed(() => {
  const total = 365
  return Math.min(100, Math.round(((total - license.value.days_left) / total) * 100))
})

const editionTagType = computed(() => {
  if (license.value.edition?.includes('企业')) return 'danger'
  if (license.value.edition?.includes('专业')) return 'warning'
  return 'primary'
})

const dialogVisible = ref(false)
const verifying = ref(false)
const verifyForm = reactive({ key: '' })

function showVerifyDialog() {
  verifyForm.key = ''
  dialogVisible.value = true
}

async function doVerify() {
  if (!verifyForm.key) {
    ElMessage.warning('请输入 License Key')
    return
  }
  verifying.value = true
  try {
    const res = await verifyLicense({ license_key: verifyForm.key })
    if (res.code === 0) {
      ElMessage.success('License 验证通过！版本：' + res.data.edition)
      dialogVisible.value = false
      loadLicense()
    }
  } finally {
    verifying.value = false
  }
}

async function loadLicense() {
  try {
    const res = await getLicenseInfo()
    if (res.code === 0) {
      license.value = res.data.license
    }
  } catch (e) {
    console.warn(e)
  }
}

onMounted(loadLicense)
</script>

<style lang="scss" scoped>
.license-header {
  display: flex;
  align-items: center;
  gap: 24px;

  .license-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #ecf5ff, #d9ecff);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .license-key {
    display: flex;
    align-items: center;
    gap: 12px;

    .key-text {
      font-size: 18px;
      font-family: monospace;
      color: #303133;
      letter-spacing: 1px;
    }
  }
}

.quota-item {
  background: #f5f7fa;
  border-radius: 8px;
  padding: 16px;

  .quota-label {
    font-size: 12px;
    color: #909399;
    margin-bottom: 6px;
  }

  .quota-value {
    font-size: 15px;
    color: #303133;
  }
}
</style>
