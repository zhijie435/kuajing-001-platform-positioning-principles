<template>
  <div class="audit-manage">
    <a-card title="审核管理" :bordered="false">
      <a-row :gutter="16" class="stats-row">
        <a-col :span="4" v-for="(stat, key) in statCards" :key="key">
          <a-card class="stat-card" :bodyStyle="{ padding: '16px' }">
            <div class="stat-value" :style="{ color: stat.color }">{{ stats[key] || 0 }}</div>
            <div class="stat-label">{{ stat.label }}</div>
          </a-card>
        </a-col>
      </a-row>

      <a-form layout="inline" class="filter-form">
        <a-form-item label="状态">
          <a-select
            v-model:value="filter.status"
            placeholder="全部"
            style="width: 150px"
            allowClear
            @change="fetchList"
          >
            <a-select-option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </a-select-option>
          </a-select>
        </a-form-item>
        <a-form-item label="目标类型">
          <a-select
            v-model:value="filter.target_type"
            placeholder="全部"
            style="width: 120px"
            allowClear
            @change="fetchList"
          >
            <a-select-option v-for="opt in targetTypeOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </a-select-option>
          </a-select>
        </a-form-item>
        <a-form-item label="提交端">
          <a-select
            v-model:value="filter.platform"
            placeholder="全部"
            style="width: 120px"
            allowClear
            @change="fetchList"
          >
            <a-select-option v-for="opt in platformOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </a-select-option>
          </a-select>
        </a-form-item>
        <a-form-item label="关键词">
          <a-input-search
            v-model:value="filter.keyword"
            placeholder="审核单号/摘要/提交人"
            style="width: 250px"
            @search="fetchList"
          />
        </a-form-item>
      </a-form>

      <a-table
        :columns="columns"
        :data-source="list"
        :loading="loading"
        :pagination="pagination"
        @change="handleTableChange"
        :row-key="record => record.id"
      >
        <template #bodyCell="{ column, record }">
          <template v-if="column.key === 'audit_no'">
            <a @click="viewDetail(record)">{{ record.audit_no }}</a>
          </template>
          <template v-else-if="column.key === 'target_type'">
            <a-tag :color="targetTypeMap[record.target_type]?.color || 'blue'">
              {{ targetTypeMap[record.target_type]?.label || record.target_type }}
            </a-tag>
          </template>
          <template v-else-if="column.key === 'operation_type'">
            {{ operationTypeMap[record.operation_type] || record.operation_type }}
          </template>
          <template v-else-if="column.key === 'status'">
            <a-tag :color="auditStatusMap[record.status]?.color || 'default'">
              {{ auditStatusMap[record.status]?.label || record.status }}
            </a-tag>
          </template>
          <template v-else-if="column.key === 'submitter_platform'">
            <a-tag :color="platformMap[record.submitter_platform]?.color || 'default'">
              {{ platformMap[record.submitter_platform]?.label || record.submitter_platform }}
            </a-tag>
          </template>
          <template v-else-if="column.key === 'action'">
            <template v-if="record.status === 'pending' && isAdmin">
              <a-button type="link" size="small" @click="handleApprove(record)">
                通过
              </a-button>
              <a-button type="link" size="small" danger @click="handleReject(record)">
                驳回
              </a-button>
            </template>
            <template v-else-if="record.status === 'writeback_failed' && isAdmin">
              <a-button type="link" size="small" @click="handleRetryWriteback(record)">
                重试回写
              </a-button>
            </template>
            <template v-else>
              <a-button type="link" size="small" @click="viewDetail(record)">
                查看
              </a-button>
            </template>
          </template>
        </template>
      </a-table>
    </a-card>

    <a-modal
      v-model:open="detailVisible"
      title="审核详情"
      :width="800"
      :footer="null"
      destroyOnClose
    >
      <template v-if="currentRecord">
        <a-descriptions bordered :column="2" size="small">
          <a-descriptions-item label="审核单号">{{ currentRecord.audit_no }}</a-descriptions-item>
          <a-descriptions-item label="状态">
            <a-tag :color="auditStatusMap[currentRecord.status]?.color">
              {{ auditStatusMap[currentRecord.status]?.label }}
            </a-tag>
          </a-descriptions-item>
          <a-descriptions-item label="目标类型">
            {{ targetTypeMap[currentRecord.target_type]?.label }}
          </a-descriptions-item>
          <a-descriptions-item label="操作类型">
            {{ operationTypeMap[currentRecord.operation_type] }}
          </a-descriptions-item>
          <a-descriptions-item label="目标ID">{{ currentRecord.target_id || '新建' }}</a-descriptions-item>
          <a-descriptions-item label="提交端">
            {{ platformMap[currentRecord.submitter_platform]?.label }}
          </a-descriptions-item>
          <a-descriptions-item label="提交人">{{ currentRecord.submitter_name }}</a-descriptions-item>
          <a-descriptions-item label="提交时间">{{ currentRecord.submitted_at }}</a-descriptions-item>
          <a-descriptions-item label="变更摘要" :span="2">
            {{ currentRecord.change_summary }}
          </a-descriptions-item>
        </a-descriptions>

        <a-divider>变更内容</a-divider>
        <a-row :gutter="16">
          <a-col :span="12">
            <a-card title="变更前" size="small">
              <pre class="json-preview">{{ formatJson(currentRecord.data_before) }}</pre>
            </a-card>
          </a-col>
          <a-col :span="12">
            <a-card title="变更后" size="small">
              <pre class="json-preview">{{ formatJson(currentRecord.data_after) }}</pre>
            </a-card>
          </a-col>
        </a-row>

        <a-divider>审核意见</a-divider>
        <template v-if="currentRecord.auditor_name">
          <a-descriptions bordered :column="2" size="small">
            <a-descriptions-item label="审核人">{{ currentRecord.auditor_name }}</a-descriptions-item>
            <a-descriptions-item label="审核时间">{{ currentRecord.audited_at }}</a-descriptions-item>
            <a-descriptions-item label="审核意见" :span="2">
              {{ currentRecord.audit_remark || '无' }}
            </a-descriptions-item>
          </a-descriptions>
        </template>
        <template v-else>
          <a-empty description="暂无审核意见" />
        </template>

        <a-divider>操作日志</a-divider>
        <a-timeline>
          <a-timeline-item v-for="log in auditLogs" :key="log.id">
            <template #dot>
              <component :is="getLogIcon(log.action)" :size="16" />
            </template>
            <div class="log-item">
              <div class="log-header">
                <span class="log-action">{{ log.action_label }}</span>
                <span class="log-time">{{ log.created_at }}</span>
              </div>
              <div class="log-operator">
                {{ log.operator_name }} ({{ platformMap[log.operator_platform]?.label || log.operator_platform }})
              </div>
              <div class="log-remark" v-if="log.remark">{{ log.remark }}</div>
            </div>
          </a-timeline-item>
        </a-timeline>

        <template v-if="currentRecord.status === 'pending' && isAdmin">
          <a-divider />
          <a-space>
            <a-button type="primary" @click="handleApprove(currentRecord)">
              审核通过
            </a-button>
            <a-button danger @click="handleReject(currentRecord)">
              审核驳回
            </a-button>
          </a-space>
        </template>
        <template v-else-if="currentRecord.status === 'writeback_failed' && isAdmin">
          <a-divider />
          <a-alert
            type="warning"
            :message="'回写失败：' + (currentRecord.writeback_error || '未知错误')"
            :description="'已尝试 ' + currentRecord.writeback_attempts + ' 次回写'"
            show-icon
          >
            <template #action>
              <a-button size="small" type="primary" @click="handleRetryWriteback(currentRecord)">
                重试回写
              </a-button>
            </template>
          </a-alert>
        </template>
      </template>
    </a-modal>

    <a-modal
      v-model:open="rejectVisible"
      title="审核驳回"
      @ok="confirmReject"
      :confirm-loading="confirmLoading"
    >
      <a-form>
        <a-form-item label="驳回理由" required>
          <a-textarea
            v-model:value="rejectRemark"
            :rows="4"
            placeholder="请输入驳回理由"
          />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { message, Modal } from 'ant-design-vue'
import {
  CheckCircleOutlined,
  CloseCircleOutlined,
  ClockCircleOutlined,
  SyncOutlined,
  UserOutlined
} from '@ant-design/icons-vue'
import {
  getAuditList,
  getAuditDetail,
  approveAudit,
  rejectAudit,
  retryWriteback,
  auditStatusMap,
  targetTypeMap,
  operationTypeMap,
  platformMap
} from '@/api/audit'
import { useUserStore } from '@/store/user'

const userStore = useUserStore()
const isAdmin = computed(() => {
  const role = userStore.userInfo?.role || ''
  return ['super_admin', 'admin', 'sales_manager'].includes(role)
})

const loading = ref(false)
const list = ref([])
const stats = ref({})
const filter = reactive({
  status: '',
  target_type: '',
  platform: '',
  keyword: ''
})
const pagination = reactive({
  current: 1,
  pageSize: 10,
  total: 0,
  showSizeChanger: true,
  showQuickJumper: true,
  showTotal: (total) => `共 ${total} 条记录`
})

const statusOptions = ref([])
const targetTypeOptions = ref([])
const platformOptions = ref([])

const detailVisible = ref(false)
const currentRecord = ref(null)
const auditLogs = ref([])

const rejectVisible = ref(false)
const rejectRecord = ref(null)
const rejectRemark = ref('')
const confirmLoading = ref(false)

const statCards = {
  total: { label: '总记录数', color: '#1890ff' },
  pending: { label: '待审核', color: '#faad14' },
  writeback_success: { label: '回写成功', color: '#52c41a' },
  writeback_failed: { label: '回写失败', color: '#ff4d4f' },
  rejected: { label: '已驳回', color: '#ff4d4f' },
  today_submitted: { label: '今日提交', color: '#722ed1' }
}

const columns = [
  { title: '审核单号', dataIndex: 'audit_no', key: 'audit_no', width: 180 },
  { title: '目标类型', dataIndex: 'target_type', key: 'target_type', width: 100 },
  { title: '操作类型', dataIndex: 'operation_type', key: 'operation_type', width: 100 },
  { title: '变更摘要', dataIndex: 'change_summary', key: 'change_summary', ellipsis: true },
  { title: '提交人', dataIndex: 'submitter_name', key: 'submitter_name', width: 100 },
  { title: '提交端', dataIndex: 'submitter_platform', key: 'submitter_platform', width: 100 },
  { title: '状态', dataIndex: 'status', key: 'status', width: 100 },
  { title: '提交时间', dataIndex: 'submitted_at', key: 'submitted_at', width: 170 },
  { title: '操作', key: 'action', width: 150, fixed: 'right' }
]

const getLogIcon = (action) => {
  const iconMap = {
    submit: ClockCircleOutlined,
    approve: CheckCircleOutlined,
    reject: CloseCircleOutlined,
    writeback_success: CheckCircleOutlined,
    writeback_failed: CloseCircleOutlined,
    retry: SyncOutlined
  }
  return iconMap[action] || UserOutlined
}

const formatJson = (data) => {
  try {
    if (typeof data === 'string') {
      data = JSON.parse(data)
    }
    return JSON.stringify(data, null, 2)
  } catch (e) {
    return data || '{}'
  }
}

const fetchList = async () => {
  loading.value = true
  try {
    const res = await getAuditList({
      page: pagination.current,
      page_size: pagination.pageSize,
      ...filter
    })
    list.value = res.list
    stats.value = res.stats
    pagination.total = res.total
    statusOptions.value = res.status_options || []
    targetTypeOptions.value = res.target_type_options || []
    platformOptions.value = res.platform_options || []
  } catch (e) {
    message.error('获取列表失败')
  } finally {
    loading.value = false
  }
}

const handleTableChange = (pag) => {
  pagination.current = pag.current
  pagination.pageSize = pag.pageSize
  fetchList()
}

const viewDetail = async (record) => {
  try {
    const res = await getAuditDetail({ id: record.id })
    currentRecord.value = res.record
    auditLogs.value = res.logs || []
    detailVisible.value = true
  } catch (e) {
    message.error('获取详情失败')
  }
}

const handleApprove = (record) => {
  Modal.confirm({
    title: '确认审核通过？',
    content: '审核通过后将自动执行数据回写操作',
    okText: '确认通过',
    okType: 'primary',
    cancelText: '取消',
    onOk: async () => {
      try {
        await approveAudit({ id: record.id, remark: '审核通过' })
        message.success('审核通过，数据已回写')
        detailVisible.value = false
        fetchList()
      } catch (e) {
        message.error('操作失败')
      }
    }
  })
}

const handleReject = (record) => {
  rejectRecord.value = record
  rejectRemark.value = ''
  rejectVisible.value = true
}

const confirmReject = async () => {
  if (!rejectRemark.value.trim()) {
    message.warning('请输入驳回理由')
    return
  }
  confirmLoading.value = true
  try {
    await rejectAudit({
      id: rejectRecord.value.id,
      remark: rejectRemark.value
    })
    message.success('已驳回')
    rejectVisible.value = false
    detailVisible.value = false
    fetchList()
  } catch (e) {
    message.error('操作失败')
  } finally {
    confirmLoading.value = false
  }
}

const handleRetryWriteback = async (record) => {
  Modal.confirm({
    title: '重试数据回写？',
    content: '将再次尝试将审核通过的数据写回业务表',
    okText: '确认重试',
    okType: 'primary',
    cancelText: '取消',
    onOk: async () => {
      try {
        await retryWriteback({ id: record.id })
        message.success('数据回写成功')
        detailVisible.value = false
        fetchList()
      } catch (e) {
        message.error('回写失败')
      }
    }
  })
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped lang="scss">
.audit-manage {
  .stats-row {
    margin-bottom: 20px;

    .stat-card {
      text-align: center;

      .stat-value {
        font-size: 28px;
        font-weight: 600;
        line-height: 1.2;
        margin-bottom: 8px;
      }

      .stat-label {
        color: #666;
        font-size: 14px;
      }
    }
  }

  .filter-form {
    margin-bottom: 16px;
  }

  .json-preview {
    background: #f5f5f5;
    padding: 12px;
    border-radius: 4px;
    max-height: 300px;
    overflow: auto;
    font-size: 12px;
    margin: 0;
  }

  .log-item {
    .log-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 4px;

      .log-action {
        font-weight: 500;
        color: #333;
      }

      .log-time {
        color: #999;
        font-size: 12px;
      }
    }

    .log-operator {
      color: #666;
      font-size: 13px;
      margin-bottom: 4px;
    }

    .log-remark {
      color: #888;
      font-size: 13px;
      background: #f5f5f5;
      padding: 8px 12px;
      border-radius: 4px;
    }
  }
}
</style>
