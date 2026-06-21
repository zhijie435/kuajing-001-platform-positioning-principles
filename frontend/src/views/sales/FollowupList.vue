<template>
  <div class="page-container">
    <div class="page-header">
      <h2>跟进记录</h2>
      <el-button type="primary" @click="showCreateDialog">
        <el-icon><Plus /></el-icon>新增跟进
      </el-button>
    </div>

    <el-card shadow="never">
      <div class="filter-bar">
        <el-select v-model="filter.type" placeholder="跟进类型" clearable style="width:160px">
          <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
        </el-select>
      </div>

      <el-timeline style="margin-top: 20px">
        <el-timeline-item
          v-for="item in list"
          :key="item.id"
          :timestamp="item.followup_time"
          placement="top"
          :type="typeColor(item.type)"
        >
          <el-card shadow="hover" class="followup-card">
            <div class="card-header">
              <el-tag :type="typeColor(item.type)" effect="light">{{ item.type_label }}</el-tag>
              <span class="customer-name">客户：{{ item.customer_name }}</span>
              <span class="operator">跟进人：{{ item.operator_name }}</span>
            </div>
            <div class="card-content">{{ item.content }}</div>
            <div v-if="item.next_followup" class="card-footer">
              <el-icon><Clock /></el-icon>
              下次跟进：{{ item.next_followup }}
            </div>
          </el-card>
        </el-timeline-item>
      </el-timeline>

      <el-pagination
        style="margin-top:16px;justify-content:center;display:flex"
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        layout="total, prev, pager, next"
      />
    </el-card>

    <el-dialog v-model="dialogVisible" title="新增跟进记录" width="520px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="客户" required>
          <el-input v-model="form.customer_name" placeholder="请输入客户姓名" />
        </el-form-item>
        <el-form-item label="跟进类型" required>
          <el-select v-model="form.type" style="width:100%">
            <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="跟进内容" required>
          <el-input v-model="form.content" type="textarea" :rows="4" placeholder="请输入跟进内容" />
        </el-form-item>
        <el-form-item label="下次跟进">
          <el-date-picker v-model="form.next_followup" type="datetime" style="width:100%" placeholder="选择下次跟进时间" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitForm">提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Clock } from '@element-plus/icons-vue'
import { getFollowupList, createFollowup } from '@/api'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)

const filter = reactive({ type: '' })
const typeOptions = [
  { value: 'call', label: '电话沟通' },
  { value: 'meeting', label: '上门拜访' },
  { value: 'wechat', label: '微信沟通' },
  { value: 'email', label: '邮件往来' },
  { value: 'sign', label: '合同签约' }
]

const dialogVisible = ref(false)
const form = reactive({
  customer_name: '',
  type: 'call',
  content: '',
  next_followup: null
})

function typeColor(type) {
  const map = { call: 'primary', meeting: 'danger', wechat: 'success', email: 'warning', sign: 'success' }
  return map[type] || 'info'
}

async function loadList() {
  loading.value = true
  try {
    const res = await getFollowupList({ page: page.value, page_size: pageSize.value, ...filter })
    if (res.code === 0) {
      list.value = res.data.list
      total.value = res.data.total
    }
  } finally {
    loading.value = false
  }
}

function showCreateDialog() {
  form.customer_name = ''
  form.type = 'call'
  form.content = ''
  form.next_followup = null
  dialogVisible.value = true
}

async function submitForm() {
  if (!form.customer_name || !form.content) {
    ElMessage.warning('请填写完整信息')
    return
  }
  await createFollowup(form)
  ElMessage.success('跟进记录已保存')
  dialogVisible.value = false
  loadList()
}

onMounted(loadList)
</script>

<style lang="scss" scoped>
.filter-bar {
  display: flex;
  gap: 12px;
}

.followup-card {
  .card-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 10px;
    font-size: 13px;
    .customer-name { color: #409eff; }
    .operator { margin-left: auto; color: #909399; font-size: 12px; }
  }
  .card-content {
    line-height: 1.7;
    color: #303133;
  }
  .card-footer {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #ebeef5;
    font-size: 12px;
    color: #e6a23c;
    display: flex;
    align-items: center;
    gap: 4px;
  }
}
</style>
