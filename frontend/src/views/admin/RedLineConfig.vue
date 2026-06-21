<template>
  <div class="redline-config">
    <a-card title="三端红线规则配置" :bordered="false">
      <a-alert
        type="info"
        show-icon
        class="info-alert"
        message="红线规则说明"
        description="红线规则是系统安全的重要防线，不同入口端可配置差异化的安全策略。修改配置后立即生效，请谨慎操作。"
      />

      <a-tabs v-model:activeKey="activeTab" type="card" class="config-tabs">
        <a-tab-pane v-for="platform in platforms" :key="platform.value" :tab="platform.label">
          <a-descriptions bordered :column="2" size="small" class="config-summary" v-if="configs[platform.value]">
            <a-descriptions-item label="状态">
              <a-badge
                :status="configs[platform.value].enabled ? 'success' : 'error'"
                :text="configs[platform.value].enabled ? '已启用' : '已禁用'"
              />
            </a-descriptions-item>
            <a-descriptions-item label="IP白名单限制">
              {{ configs[platform.value].ip_whitelist_enforce ? '强制启用' : '未强制' }}
              ({{ configs[platform.value].ip_whitelist?.length || 0 }} 个规则)
            </a-descriptions-item>
            <a-descriptions-item label="访问时段限制">
              {{ configs[platform.value].access_hours_enforce ? '强制启用' : '未强制' }}
            </a-descriptions-item>
            <a-descriptions-item label="访问时段">
              {{ configs[platform.value].access_hours?.start }} - {{ configs[platform.value].access_hours?.end }}
            </a-descriptions-item>
            <a-descriptions-item label="请求限流">
              {{ configs[platform.value].max_requests_per_minute }} 次/分钟
            </a-descriptions-item>
            <a-descriptions-item label="会话超时">
              {{ formatSeconds(configs[platform.value].session_timeout) }}
            </a-descriptions-item>
            <a-descriptions-item label="设备指纹校验">
              {{ configs[platform.value].require_device_fingerprint ? '强制要求' : '非强制' }}
              (阈值 {{ (configs[platform.value].device_fingerprint_threshold * 100).toFixed(0) }}%)
            </a-descriptions-item>
            <a-descriptions-item label="多设备登录">
              {{ configs[platform.value].allow_multi_device_login ? '允许' : '禁止' }}
            </a-descriptions-item>
          </a-descriptions>

          <a-button type="primary" style="margin-top: 16px" @click="openEditModal(platform.value)">
            <EditOutlined />
            修改配置
          </a-button>
        </a-tab-pane>
      </a-tabs>
    </a-card>

    <a-modal
      v-model:open="editVisible"
      :title="'修改' + currentPlatformLabel + '红线规则'"
      :width="900"
      @ok="handleSave"
      :confirm-loading="confirmLoading"
      destroyOnClose
    >
      <template v-if="editForm">
        <a-form layout="vertical">
          <a-row :gutter="24">
            <a-col :span="12">
              <a-form-item label="启用红线规则">
                <a-switch v-model:checked="editForm.enabled" />
              </a-form-item>
            </a-col>
            <a-col :span="12">
              <a-form-item label="强制IP白名单">
                <a-switch v-model:checked="editForm.ip_whitelist_enforce" />
              </a-form-item>
            </a-col>
          </a-row>

          <a-form-item label="IP白名单">
            <a-select
              v-model:value="editForm.ip_whitelist"
              mode="tags"
              style="width: 100%"
              placeholder="输入IP或网段，如 192.168.1.1 或 192.168.0.0/16"
              :tokenSeparators="[',']"
            />
          </a-form-item>

          <a-row :gutter="24">
            <a-col :span="12">
              <a-form-item label="强制访问时段">
                <a-switch v-model:checked="editForm.access_hours_enforce" />
              </a-form-item>
            </a-col>
            <a-col :span="12">
              <a-form-item label="访问时段">
                <a-time-range-picker
                  v-model:value="accessHoursRange"
                  format="HH:mm"
                  style="width: 100%"
                />
              </a-form-item>
            </a-col>
          </a-row>

          <a-row :gutter="24">
            <a-col :span="12">
              <a-form-item label="请求限流（次/分钟）">
                <a-input-number
                  v-model:value="editForm.max_requests_per_minute"
                  :min="1"
                  :max="10000"
                  style="width: 100%"
                />
              </a-form-item>
            </a-col>
            <a-col :span="12">
              <a-form-item label="会话超时（秒）">
                <a-input-number
                  v-model:value="editForm.session_timeout"
                  :min="60"
                  :max="2592000"
                  style="width: 100%"
                  :formatter="formatSecondsInput"
                  :parser="parseSecondsInput"
                />
              </a-form-item>
            </a-col>
          </a-row>

          <a-row :gutter="24">
            <a-col :span="12">
              <a-form-item label="强制设备指纹校验">
                <a-switch v-model:checked="editForm.require_device_fingerprint" />
              </a-form-item>
            </a-col>
            <a-col :span="12">
              <a-form-item label="设备指纹相似度阈值">
                <a-slider
                  v-model:value="editForm.device_fingerprint_threshold"
                  :min="0"
                  :max="1"
                  :step="0.05"
                  :marks="{ 0: '0%', 0.5: '50%', 0.8: '80%', 1: '100%' }"
                />
              </a-form-item>
            </a-col>
          </a-row>

          <a-row :gutter="24">
            <a-col :span="12">
              <a-form-item label="允许多设备登录">
                <a-switch v-model:checked="editForm.allow_multi_device_login" />
              </a-form-item>
            </a-col>
            <a-col :span="12">
              <a-form-item label="敏感操作二次验证">
                <a-switch v-model:checked="editForm.sensitive_operation_2fa" />
              </a-form-item>
            </a-col>
          </a-row>
        </a-form>
      </template>
    </a-modal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { message } from 'ant-design-vue'
import { EditOutlined } from '@ant-design/icons-vue'
import dayjs from 'dayjs'
import { getRedlineConfig, updateRedlineConfig } from '@/api/audit'

const platforms = [
  { value: 'admin', label: '管理端', color: '#1890ff' },
  { value: 'sales', label: '销售端', color: '#52c41a' },
  { value: 'client', label: '客户端', color: '#722ed1' }
]

const activeTab = ref('admin')
const configs = ref({})
const loading = ref(false)

const editVisible = ref(false)
const currentPlatform = ref('')
const editForm = ref(null)
const accessHoursRange = ref(null)
const confirmLoading = ref(false)

const currentPlatformLabel = computed(() => {
  const p = platforms.find(p => p.value === currentPlatform.value)
  return p ? p.label : ''
})

const formatSeconds = (seconds) => {
  if (!seconds) return '-'
  if (seconds < 60) return seconds + ' 秒'
  if (seconds < 3600) return Math.floor(seconds / 60) + ' 分钟'
  if (seconds < 86400) return Math.floor(seconds / 3600) + ' 小时'
  return Math.floor(seconds / 86400) + ' 天'
}

const formatSecondsInput = (value) => {
  if (!value) return ''
  return `${value} 秒 (${formatSeconds(value)})`
}

const parseSecondsInput = (value) => {
  if (!value) return 0
  const match = String(value).match(/(\d+)/)
  return match ? parseInt(match[1]) : 0
}

const fetchConfig = async () => {
  loading.value = true
  try {
    const res = await getRedlineConfig()
    configs.value = res.all_platforms || {}
  } catch (e) {
    message.error('获取配置失败')
  } finally {
    loading.value = false
  }
}

const openEditModal = (platform) => {
  currentPlatform.value = platform
  const config = { ...(configs.value[platform] || {}) }
  editForm.value = {
    platform,
    enabled: config.enabled ?? true,
    ip_whitelist: config.ip_whitelist || [],
    ip_whitelist_enforce: config.ip_whitelist_enforce ?? false,
    access_hours: config.access_hours || { start: '00:00', end: '23:59' },
    access_hours_enforce: config.access_hours_enforce ?? false,
    max_requests_per_minute: config.max_requests_per_minute || 300,
    session_timeout: config.session_timeout || 7200,
    require_device_fingerprint: config.require_device_fingerprint ?? false,
    device_fingerprint_threshold: config.device_fingerprint_threshold ?? 0.6,
    allow_multi_device_login: config.allow_multi_device_login ?? true,
    sensitive_operation_2fa: config.sensitive_operation_2fa ?? false
  }
  accessHoursRange.value = [
    dayjs(editForm.value.access_hours.start, 'HH:mm'),
    dayjs(editForm.value.access_hours.end, 'HH:mm')
  ]
  editVisible.value = true
}

const handleSave = async () => {
  if (!editForm.value) return

  const formData = { ...editForm.value }
  if (accessHoursRange.value && accessHoursRange.value.length === 2) {
    formData.access_hours = {
      start: accessHoursRange.value[0].format('HH:mm'),
      end: accessHoursRange.value[1].format('HH:mm')
    }
  }

  confirmLoading.value = true
  try {
    await updateRedlineConfig(formData)
    message.success('配置更新成功')
    editVisible.value = false
    fetchConfig()
  } catch (e) {
    message.error('保存失败')
  } finally {
    confirmLoading.value = false
  }
}

onMounted(() => {
  fetchConfig()
})
</script>

<style scoped lang="scss">
.redline-config {
  .info-alert {
    margin-bottom: 24px;
  }

  .config-tabs {
    margin-top: 16px;
  }

  .config-summary {
    margin-top: 16px;
  }
}
</style>
