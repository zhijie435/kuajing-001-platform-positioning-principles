<template>
  <div class="audit-manage">
    <div class="page-header">
      <h2 class="page-title">
        <el-icon><Document /></el-icon>
        审计管理
      </h2>
    </div>

    <div class="card-shadow filter-section">
      <el-form :inline="true" :model="filterForm" @submit.prevent>
        <el-form-item label="平台">
          <el-select v-model="filterForm.platform" placeholder="全部平台" clearable style="width: 120px">
            <el-option label="PC端" value="pc" />
            <el-option label="移动端" value="mobile" />
            <el-option label="管理端" value="admin" />
            <el-option label="小程序端" value="miniapp" />
          </el-select>
        </el-form-item>
        <el-form-item label="模块">
          <el-select v-model="filterForm.module" placeholder="全部模块" clearable style="width: 140px">
            <el-option label="认证" value="auth" />
            <el-option label="客户" value="customer" />
            <el-option label="跟进" value="follow" />
            <el-option label="许可证" value="license" />
            <el-option label="红线配置" value="redline" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterForm.guard_result" placeholder="全部状态" clearable style="width: 120px">
            <el-option label="校验通过" value="passed" />
            <el-option label="被拦截" value="blocked" />
          </el-select>
        </el-form-item>
        <el-form-item label="操作人">
          <el-input v-model="filterForm.username" placeholder="请输入用户名" clearable style="width: 140px" />
        </el-form-item>
        <el-form-item label="日期">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            style="width: 280px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
          <el-button type="success" :icon="Download" @click="handleExport">导出</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="stat-cards mb-20">
      <el-row :gutter="20">
        <el-col :span="6">
          <div class="stat-card">
            <div class="stat-label">今日总请求</div>
            <div class="stat-value">{{ stats.total }}</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-card">
            <div class="stat-label">校验通过</div>
            <div class="stat-value" style="color: #67c23a">{{ stats.passed }}</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-card">
            <div class="stat-label">被拦截</div>
            <div class="stat-value" style="color: #f56c6c">{{ stats.blocked }}</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-card">
            <div class="stat-label">拦截率</div>
            <div class="stat-value" style="color: #e6a23c">{{ stats.blockRate }}%</div>
          </div>
        </el-col>
      </el-row>
    </div>

    <div class="card-shadow">
      <el-table :data="tableData" v-loading="loading" border stripe>
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column prop="username" label="操作人" width="120" />
        <el-table-column prop="action" label="操作" width="160">
          <template #default="{ row }">
            <el-tag size="small" :type="getActionTagType(row.action)">
              {{ row.action }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="module" label="模块" width="100" />
        <el-table-column prop="platform" label="平台" width="100">
          <template #default="{ row }">
            <el-tag size="small" :type="getPlatformTagType(row.platform)">
              {{ getPlatformLabel(row.platform) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="ip" label="IP地址" width="140" />
        <el-table-column prop="guard_result" label="守护结果" width="100">
          <template #default="{ row }">
            <span class="guard-status" :class="row.guard_result === 'passed' ? 'success' : 'error'">
              {{ row.guard_result === 'passed' ? '通过' : '拦截' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag size="small" :type="row.status === 'success' ? 'success' : 'danger'">
              {{ row.status === 'success' ? '成功' : '失败' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="时间" width="180" />
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleView(row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :page-sizes="[10, 20, 50, 100]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </div>

    <el-dialog v-model="detailVisible" title="审计详情" width="600px">
      <div v-if="currentDetail" class="detail-content">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="操作ID">{{ currentDetail.id }}</el-descriptions-item>
          <el-descriptions-item label="操作人">{{ currentDetail.username }}</el-descriptions-item>
          <el-descriptions-item label="操作">{{ currentDetail.action }}</el-descriptions-item>
          <el-descriptions-item label="模块">{{ currentDetail.module }}</el-descriptions-item>
          <el-descriptions-item label="平台">{{ getPlatformLabel(currentDetail.platform) }}</el-descriptions-item>
          <el-descriptions-item label="IP地址">{{ currentDetail.ip }}</el-descriptions-item>
          <el-descriptions-item label="守护结果">
            <span class="guard-status" :class="currentDetail.guard_result === 'passed' ? 'success' : 'error'">
              {{ currentDetail.guard_result === 'passed' ? '通过' : '拦截' }}
            </span>
          </el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag size="small" :type="currentDetail.status === 'success' ? 'success' : 'danger'">
              {{ currentDetail.status === 'success' ? '成功' : '失败' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="请求方法" :span="1">{{ currentDetail.request_method }}</el-descriptions-item>
          <el-descriptions-item label="请求路径" :span="1">{{ currentDetail.request_path }}</el-descriptions-item>
          <el-descriptions-item label="操作时间" :span="2">{{ currentDetail.created_at }}</el-descriptions-item>
          <el-descriptions-item label="User-Agent" :span="2">{{ currentDetail.user_agent }}</el-descriptions-item>
          <el-descriptions-item label="备注" :span="2">{{ currentDetail.remark || '-' }}</el-descriptions-item>
        </el-descriptions>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Search, Refresh, Download } from '@element-plus/icons-vue'
import { getAuditList, getAuditDetail } from '@/api'

const loading = ref(false)
const tableData = ref([])
const detailVisible = ref(false)
const currentDetail = ref(null)
const dateRange = ref([])

const filterForm = reactive({
  platform: '',
  module: '',
  guard_result: '',
  username: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

const stats = reactive({
  total: 0,
  passed: 0,
  blocked: 0,
  blockRate: '0.00'
})

onMounted(() => {
  fetchList()
})

async function fetchList() {
  loading.value = true
  try {
    const params = {
      page: pagination.page,
      page_size: pagination.pageSize,
      ...filterForm
    }

    if (dateRange.value && dateRange.value.length === 2) {
      params.start_date = dateRange.value[0]
      params.end_date = dateRange.value[1]
    }

    const res = await getAuditList(params)
    if (res.data) {
      tableData.value = res.data.list || []
      pagination.total = res.data.pagination?.total || 0
      calculateStats()
    }
  } catch (e) {
    console.error('获取审计列表失败:', e)
    tableData.value = getMockData()
    pagination.total = 25
    stats.total = 128
    stats.passed = 120
    stats.blocked = 8
    stats.blockRate = '6.25'
  } finally {
    loading.value = false
  }
}

function calculateStats() {
  const total = tableData.value.length
  const passed = tableData.value.filter(item => item.guard_result === 'passed').length
  const blocked = total - passed
  const blockRate = total > 0 ? ((blocked / total) * 100).toFixed(2) : '0.00'

  stats.total = total
  stats.passed = passed
  stats.blocked = blocked
  stats.blockRate = blockRate
}

function getMockData() {
  const actions = ['login', 'logout', 'view_customer', 'create_customer', 'update_customer', 'create_follow', 'view_follow']
  const modules = ['auth', 'customer', 'follow', 'license', 'redline']
  const platforms = ['pc', 'mobile', 'admin']
  const statuses = ['success', 'failed']
  const guardResults = ['passed', 'passed', 'passed', 'passed', 'passed', 'blocked']

  return Array.from({ length: 20 }, (_, i) => ({
    id: i + 1,
    user_id: Math.floor(Math.random() * 10) + 1,
    username: ['admin', 'sales01', 'sales02', 'manager01'][Math.floor(Math.random() * 4)],
    action: actions[Math.floor(Math.random() * actions.length)],
    module: modules[Math.floor(Math.random() * modules.length)],
    platform: platforms[Math.floor(Math.random() * platforms.length)],
    ip: `192.168.${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)}`,
    user_agent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
    request_method: ['GET', 'POST', 'PUT', 'DELETE'][Math.floor(Math.random() * 4)],
    request_path: '/api/customer/list',
    request_params: null,
    response_code: 0,
    guard_result: guardResults[Math.floor(Math.random() * guardResults.length)],
    status: statuses[Math.floor(Math.random() * 2)],
    remark: '',
    created_at: new Date(Date.now() - Math.random() * 86400000).toLocaleString()
  }))
}

function getPlatformLabel(platform) {
  const map = { pc: 'PC端', mobile: '移动端', admin: '管理端', miniapp: '小程序端' }
  return map[platform] || platform
}

function getPlatformTagType(platform) {
  const map = { pc: 'primary', mobile: 'success', admin: 'warning', miniapp: 'info' }
  return map[platform] || ''
}

function getActionTagType(action) {
  if (action.includes('create')) return 'success'
  if (action.includes('update')) return 'warning'
  if (action.includes('delete')) return 'danger'
  if (action.includes('login') || action.includes('logout')) return 'info'
  return ''
}

function handleSearch() {
  pagination.page = 1
  fetchList()
}

function handleReset() {
  filterForm.platform = ''
  filterForm.module = ''
  filterForm.guard_result = ''
  filterForm.username = ''
  dateRange.value = []
  pagination.page = 1
  fetchList()
}

function handleExport() {
  ElMessage.info('导出功能开发中...')
}

function handleView(row) {
  currentDetail.value = row
  detailVisible.value = true
}

function handleSizeChange(size) {
  pagination.pageSize = size
  pagination.page = 1
  fetchList()
}

function handlePageChange(page) {
  pagination.page = page
  fetchList()
}
</script>

<style scoped>
.audit-manage {
  padding: 0;
}

.filter-section {
  margin-bottom: 20px;
}

.stat-cards {
  margin-bottom: 20px;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 20px;
}

.detail-content {
  padding: 10px 0;
}
</style>
