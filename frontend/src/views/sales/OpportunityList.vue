<template>
  <div class="page-container">
    <div class="page-header">
      <h2>商机管理</h2>
      <div style="display:flex;align-items:center;gap:16px">
        <div>
          商机总额：<b style="color:#f56c6c;font-size:16px">¥{{ formatNumber(totalAmount) }}</b>
        </div>
        <div>
          加权金额：<b style="color:#67c23a;font-size:16px">¥{{ formatNumber(weightedAmount) }}</b>
        </div>
        <el-button type="primary" @click="showCreateDialog">
          <el-icon><Plus /></el-icon>新增商机
        </el-button>
      </div>
    </div>

    <el-row :gutter="16" style="margin-bottom: 20px">
      <el-col v-for="s in stageSummary" :key="s.stage" :span="4">
        <div class="stage-card" :class="'stage-' + s.stage">
          <div class="stage-label">{{ s.label }}</div>
          <div class="stage-count">{{ s.count }} 个</div>
          <div class="stage-amount">¥{{ formatNumber(s.amount) }}</div>
        </div>
      </el-col>
    </el-row>

    <el-card shadow="never">
      <div class="filter-bar">
        <el-select v-model="filter.stage" placeholder="商机阶段" clearable style="width:160px">
          <el-option v-for="s in stageSummary" :key="s.stage" :label="s.label" :value="s.stage" />
        </el-select>
      </div>

      <el-table :data="list" v-loading="loading" stripe style="margin-top:16px">
        <el-table-column prop="name" label="商机名称" min-width="220">
          <template #default="{ row }">
            <div style="font-weight:600">{{ row.name }}</div>
            <div style="color:#909399;font-size:12px">{{ row.customer_name }}</div>
          </template>
        </el-table-column>
        <el-table-column label="预估金额" width="140">
          <template #default="{ row }">
            <span style="color:#f56c6c;font-weight:600;font-size:15px">¥{{ formatNumber(row.amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="阶段" width="120">
          <template #default="{ row }">
            <el-tag :type="stageTagType(row.stage)">{{ row.stage_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="赢单率" width="120">
          <template #default="{ row }">
            <el-progress :percentage="row.probability" :stroke-width="10" />
          </template>
        </el-table-column>
        <el-table-column prop="expected_close" label="预计成交" width="130" />
        <el-table-column prop="owner_name" label="负责人" width="100" />
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button size="small" link type="primary" @click="editOpportunity(row)">编辑</el-button>
            <el-button size="small" link type="warning" @click="advanceStage(row)">推进</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        style="margin-top:16px;justify-content:flex-end;display:flex"
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        layout="total, prev, pager, next"
      />
    </el-card>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="560px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="商机名称" required>
          <el-input v-model="form.name" placeholder="请输入商机名称" />
        </el-form-item>
        <el-form-item label="关联客户" required>
          <el-input v-model="form.customer_name" placeholder="请输入客户名称" />
        </el-form-item>
        <el-form-item label="预估金额" required>
          <el-input-number v-model="form.amount" :min="0" :step="10000" style="width:100%" />
        </el-form-item>
        <el-form-item label="当前阶段">
          <el-select v-model="form.stage" style="width:100%">
            <el-option label="初步接触" value="initial" />
            <el-option label="需求确认" value="qualified" />
            <el-option label="方案提交" value="proposal" />
            <el-option label="商务谈判" value="negotiation" />
            <el-option label="赢单" value="won" />
          </el-select>
        </el-form-item>
        <el-form-item label="赢单概率">
          <el-slider v-model="form.probability" :min="0" :max="100" />
        </el-form-item>
        <el-form-item label="预计成交">
          <el-date-picker v-model="form.expected_close" type="date" style="width:100%" />
        </el-form-item>
        <el-form-item label="商机描述">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitForm">确认提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getOpportunityList, createOpportunity, updateOpportunity } from '@/api'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const totalAmount = ref(0)
const weightedAmount = ref(0)
const stageSummary = ref([])

const filter = reactive({ stage: '' })

const dialogVisible = ref(false)
const dialogTitle = ref('新增商机')
const form = reactive({
  id: null,
  name: '',
  customer_name: '',
  amount: 0,
  stage: 'initial',
  probability: 10,
  expected_close: '',
  description: ''
})

function formatNumber(n) {
  if (!n) return '0'
  if (n >= 10000) return (n / 10000).toFixed(1) + '万'
  return n.toLocaleString()
}

function stageTagType(stage) {
  const map = { initial: 'info', qualified: '', proposal: 'warning', negotiation: 'primary', won: 'success', lost: 'danger' }
  return map[stage] || ''
}

async function loadList() {
  loading.value = true
  try {
    const res = await getOpportunityList({ page: page.value, page_size: pageSize.value, ...filter })
    if (res.code === 0) {
      list.value = res.data.list
      total.value = res.data.total
      totalAmount.value = res.data.total_amount
      weightedAmount.value = res.data.weighted_amount
      stageSummary.value = res.data.stage_summary
    }
  } finally {
    loading.value = false
  }
}

function showCreateDialog() {
  Object.assign(form, { id: null, name: '', customer_name: '', amount: 0, stage: 'initial', probability: 10, expected_close: '', description: '' })
  dialogTitle.value = '新增商机'
  dialogVisible.value = true
}

function editOpportunity(row) {
  Object.assign(form, row)
  dialogTitle.value = '编辑商机'
  dialogVisible.value = true
}

function advanceStage(row) {
  const stages = ['initial', 'qualified', 'proposal', 'negotiation', 'won']
  const idx = stages.indexOf(row.stage)
  if (idx < stages.length - 1) {
    row.stage = stages[idx + 1]
    ElMessage.success('商机阶段已推进')
  } else {
    ElMessage.info('已到最终阶段')
  }
}

async function submitForm() {
  if (!form.name || !form.customer_name || form.amount <= 0) {
    ElMessage.warning('请填写完整商机信息')
    return
  }
  try {
    if (form.id) {
      await updateOpportunity(form)
    } else {
      await createOpportunity(form)
    }
    ElMessage.success('保存成功')
    dialogVisible.value = false
    loadList()
  } catch (e) {
  }
}

onMounted(loadList)
</script>

<style lang="scss" scoped>
.filter-bar {
  display: flex;
  gap: 12px;
}

.stage-card {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  border-left: 4px solid #dcdfe6;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);

  .stage-label { font-size: 13px; color: #909399; margin-bottom: 6px; }
  .stage-count { font-size: 20px; font-weight: 600; color: #303133; }
  .stage-amount { font-size: 13px; color: #f56c6c; margin-top: 4px; }

  &.stage-initial { border-left-color: #909399; }
  &.stage-qualified { border-left-color: #409eff; }
  &.stage-proposal { border-left-color: #e6a23c; }
  &.stage-negotiation { border-left-color: #8c50ff; }
  &.stage-won { border-left-color: #67c23a; }
  &.stage-lost { border-left-color: #f56c6c; }
}
</style>
