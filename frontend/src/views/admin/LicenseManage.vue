<template>
  <div class="page-container">
    <div class="page-header">
      <h2>License 管理</h2>
      <el-button type="primary" @click="showVerifyDialog">
        <el-icon><Key /></el-icon>验证并保存 License
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
              {{ license.edition || '加载中' }}
            </el-tag>
            <span class="key-text">{{ license.key }}</span>
            <el-tag v-if="license.updated_at" type="info" size="small" effect="plain">
              更新于 {{ license.updated_at }}
            </el-tag>
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
              <b style="color:#67c23a">{{ (license.max_clients || 0).toLocaleString() }}</b> / 上限
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

    <el-card shadow="never" style="margin-top: 20px">
      <template #header>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <b>License 记录列表</b>
          <el-tag type="info" size="small">共 {{ licenseList.length }} 条（按 Key 去重，激活态置顶）</el-tag>
        </div>
      </template>
      <el-table :data="licenseList" v-loading="listLoading" stripe>
        <el-table-column label="License Key" min-width="220">
          <template #default="{ row }">
            <span style="font-family:monospace">{{ row.license_key }}</span>
          </template>
        </el-table-column>
        <el-table-column label="版本" width="100">
          <template #default="{ row }">
            <el-tag :type="editionCodeTagType(row.edition_code)" size="small">{{ row.edition_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="expire" label="到期日期" width="130" />
        <el-table-column label="剩余天数" width="110">
          <template #default="{ row }">
            <span :style="row.days_left <= 30 ? 'color:#f56c6c;font-weight:600' : ''">{{ row.days_left }} 天</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'active'" type="success" effect="dark" size="small">激活中</el-tag>
            <el-tag v-else-if="row.status === 'expired'" type="danger" size="small">已过期</el-tag>
            <el-tag v-else type="info" size="small">备用</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="updated_at" label="更新时间" width="170" />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button size="small" link type="primary" @click="viewDetail(row)">查看详情</el-button>
            <el-button
              v-if="row.status !== 'active' && row.status !== 'expired'"
              size="small"
              link
              type="warning"
              @click="activate(row)"
            >设为激活</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" title="验证并保存 License Key" width="480px">
      <el-form label-width="100px">
        <el-form-item label="License Key" required>
          <el-input
            v-model="verifyForm.key"
            placeholder="CRM-LICENSE-YYYY-STD/PRO/ENT"
            size="large"
          />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="verifyForm.remark" placeholder="可选，如：续费采购" />
        </el-form-item>
        <el-form-item>
          <el-alert
            title="验证通过后将覆盖同 Key 记录并设为激活，列表与详情会立即刷新"
            type="info"
            :closable="false"
            show-icon
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="doVerify" :loading="verifying">验证并保存</el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="rollbackVisible"
      title="⚠️ License 操作失败，已自动回滚"
      width="560px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      :show-close="false"
    >
      <el-alert
        :title="rollbackError?.error_detail || rollbackError?.message || '操作失败'"
        type="error"
        :description="rollbackError?.suggestion || '系统已自动回滚到之前的 License 配置'"
        show-icon
        style="margin-bottom: 16px"
      />

      <el-descriptions v-if="rollbackError?.rollback_license" :column="1" border size="small">
        <el-descriptions-item label="回滚后版本">
          <el-tag :type="editionCodeTagType(rollbackError.rollback_license.edition_code)" size="small">
            {{ rollbackError.rollback_license.edition }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="License Key">
          <span style="font-family: monospace">{{ rollbackError.rollback_license.key }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="到期日期">
          <span :style="rollbackError.rollback_license.days_left <= 30 ? 'color:#f56c6c' : ''">
            {{ rollbackError.rollback_license.expire }}
            <span v-if="rollbackError.rollback_license.days_left !== undefined">
              (剩余 {{ rollbackError.rollback_license.days_left }} 天)
            </span>
          </span>
        </el-descriptions-item>
        <el-descriptions-item label="用户配额">
          {{ rollbackError.rollback_license.max_users }} 人
        </el-descriptions-item>
        <el-descriptions-item label="客户配额">
          {{ (rollbackError.rollback_license.max_clients || 0).toLocaleString() }} 人
        </el-descriptions-item>
      </el-descriptions>

      <template #footer>
        <el-button @click="closeRollback">关闭</el-button>
        <el-button
          v-if="rollbackError?.retry_available"
          type="primary"
          @click="retryOperation"
          :loading="retrying"
        >
          <el-icon><Refresh /></el-icon>
          重新提交
        </el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="detailVisible" title="License 详情" width="560px">
      <el-descriptions v-if="detail" :column="1" border>
        <el-descriptions-item label="License Key">
          <span style="font-family:monospace">{{ detail.license_key }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="版本">
          <el-tag :type="editionCodeTagType(detail.edition_code)">{{ detail.edition_label }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="到期日期">{{ detail.expire }}</el-descriptions-item>
        <el-descriptions-item label="剩余天数">{{ detail.days_left }} 天</el-descriptions-item>
        <el-descriptions-item label="用户上限">{{ detail.max_users }}</el-descriptions-item>
        <el-descriptions-item label="客户上限">{{ (detail.max_clients || 0).toLocaleString() }}</el-descriptions-item>
        <el-descriptions-item label="签发日期">{{ detail.issued_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="更新时间">{{ detail.updated_at }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag v-if="detail.status === 'active'" type="success" effect="dark">激活中</el-tag>
          <el-tag v-else-if="detail.status === 'expired'" type="danger">已过期</el-tag>
          <el-tag v-else type="info">备用</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="备注">{{ detail.remark || '-' }}</el-descriptions-item>
        <el-descriptions-item label="可用功能">
          <el-tag v-for="f in (detail.features || [])" :key="f" size="small" style="margin:2px">{{ f }}</el-tag>
        </el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Key, Medal, Refresh } from '@element-plus/icons-vue'
import {
  getLicenseInfo, verifyLicense, getLicenseList, getLicenseDetail, activateLicense
} from '@/api'

const license = ref({
  key: '',
  full_key: '',
  edition: '',
  edition_code: '',
  expire: '',
  issued_at: '',
  updated_at: '',
  days_left: 0,
  max_users: 0,
  max_clients: 0,
  features: []
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
  { feature: '高级分析', standard: false, professional: false, enterprise: true, desc: 'BI多维分析、自定义报表' },
  { feature: '系统定制', standard: false, professional: false, enterprise: true, desc: '工作流、字段自定义' },
  { feature: 'API 访问', standard: false, professional: false, enterprise: true, desc: '开放 REST API 接口' }
])

const licenseProgress = computed(() => {
  const total = 365
  return Math.min(100, Math.round(((total - license.value.days_left) / total) * 100))
})

const editionTagType = computed(() => editionCodeTagType(license.value.edition_code))

function editionCodeTagType(code) {
  if (code === 'enterprise') return 'danger'
  if (code === 'professional') return 'warning'
  return 'primary'
}

const licenseList = ref([])
const listLoading = ref(false)

const dialogVisible = ref(false)
const verifying = ref(false)
const verifyForm = reactive({ key: '', remark: '' })

const detailVisible = ref(false)
const detail = ref(null)

const rollbackVisible = ref(false)
const rollbackError = ref(null)
const retrying = ref(false)
const pendingOperation = ref(null)

function showVerifyDialog() {
  verifyForm.key = ''
  verifyForm.remark = ''
  dialogVisible.value = true
}

async function doVerify() {
  if (!verifyForm.key) {
    ElMessage.warning('请输入 License Key')
    return
  }
  pendingOperation.value = {
    type: 'verify',
    data: { license_key: verifyForm.key, remark: verifyForm.remark }
  }
  verifying.value = true
  try {
    const res = await verifyLicense({ license_key: verifyForm.key, remark: verifyForm.remark })
    if (res.code === 0) {
      ElMessage.success('License 验证通过并已保存激活，版本：' + res.data.edition)
      dialogVisible.value = false
      pendingOperation.value = null
      await Promise.all([loadLicense(), loadList()])
    }
  } catch (e) {
    const errorData = e?.response?.data || e?.data || null
    if (errorData && errorData.data && errorData.data.rollback) {
      rollbackError.value = errorData.data
      dialogVisible.value = false
      rollbackVisible.value = true
    } else {
      ElMessage.error(errorData?.message || '验证失败')
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

async function loadList() {
  listLoading.value = true
  try {
    const res = await getLicenseList()
    if (res.code === 0) {
      licenseList.value = res.data.list
    }
  } catch (e) {
    console.warn(e)
  } finally {
    listLoading.value = false
  }
}

async function viewDetail(row) {
  try {
    const res = await getLicenseDetail({ license_key: row.license_key })
    if (res.code === 0) {
      detail.value = res.data.detail
      detailVisible.value = true
    }
  } catch (e) {
    console.warn(e)
  }
}

async function activate(row) {
  try {
    await ElMessageBox.confirm(
      `确认将 License【${row.license_key}】设为激活？切换后商用边界将按此版本生效。`,
      '切换激活确认',
      { type: 'warning' }
    )
    pendingOperation.value = {
      type: 'activate',
      data: { license_key: row.license_key }
    }
    const res = await activateLicense({ license_key: row.license_key })
    if (res.code === 0) {
      ElMessage.success('已切换激活')
      pendingOperation.value = null
      await Promise.all([loadLicense(), loadList()])
    }
  } catch (e) {
    if (e === 'cancel') return
    const errorData = e?.response?.data || e?.data || null
    if (errorData && errorData.data && errorData.data.rollback) {
      rollbackError.value = errorData.data
      rollbackVisible.value = true
    } else {
      ElMessage.error(errorData?.message || '激活失败')
    }
  }
}

function closeRollback() {
  rollbackVisible.value = false
  rollbackError.value = null
  pendingOperation.value = null
  Promise.all([loadLicense(), loadList()])
}

async function retryOperation() {
  if (!pendingOperation.value) return

  retrying.value = true
  rollbackVisible.value = false
  try {
    let res
    if (pendingOperation.value.type === 'verify') {
      res = await verifyLicense(pendingOperation.value.data)
    } else if (pendingOperation.value.type === 'activate') {
      res = await activateLicense(pendingOperation.value.data)
    }

    if (res && res.code === 0) {
      ElMessage.success('操作成功')
      rollbackError.value = null
      pendingOperation.value = null
      await Promise.all([loadLicense(), loadList()])
    }
  } catch (e) {
    const errorData = e?.response?.data || e?.data || null
    if (errorData && errorData.data && errorData.data.rollback) {
      rollbackError.value = errorData.data
      rollbackVisible.value = true
    } else {
      ElMessage.error(errorData?.message || '操作失败')
    }
  } finally {
    retrying.value = false
  }
}

onMounted(() => {
  loadLicense()
  loadList()
})
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
