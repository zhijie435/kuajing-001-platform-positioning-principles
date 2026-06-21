<template>
  <div class="page-container">
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px">
        <el-button link type="primary" @click="$router.back()">
          <el-icon><ArrowLeft /></el-icon>返回
        </el-button>
        <h2>客户详情</h2>
      </div>
      <div>
        <el-button type="primary" @click="showFollowupDialog">
          <el-icon><ChatDotRound /></el-icon>新增跟进
        </el-button>
      </div>
    </div>

    <el-row :gutter="20">
      <el-col :span="8">
        <el-card shadow="never">
          <div style="text-align:center;padding:20px 0">
            <el-avatar :size="80" style="background:#409eff;font-size:32px">
              {{ customer.name?.charAt(0) || 'U' }}
            </el-avatar>
            <h3 style="margin:12px 0 4px">{{ customer.name }}</h3>
            <div style="color:#909399;font-size:13px">{{ customer.company }}</div>
            <div style="margin-top:8px">
              <el-tag v-if="customer.level === 'A'" type="danger" effect="dark">A级客户</el-tag>
              <el-tag v-else-if="customer.level === 'B'" type="primary">B级客户</el-tag>
              <el-tag v-else type="success">C级客户</el-tag>
              <el-tag v-if="customer.status === 'active'" type="success" style="margin-left:6px">活跃</el-tag>
              <el-tag v-else-if="customer.status === 'negotiating'" type="warning" style="margin-left:6px">跟进中</el-tag>
              <el-tag v-else type="info" style="margin-left:6px">潜在</el-tag>
            </div>
          </div>

          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="联系电话">
              <el-icon><Phone /></el-icon> {{ customer.phone || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="电子邮箱">
              <el-icon><Message /></el-icon> {{ customer.email || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="客户来源">
              {{ customer.source || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="负责销售">
              {{ customer.owner_name || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="累计成交">
              <span style="color:#f56c6c;font-weight:600">¥{{ (customer.amount || 0).toLocaleString() }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="跟进次数">
              {{ customer.followup_count || 0 }} 次
            </el-descriptions-item>
            <el-descriptions-item label="创建时间">
              {{ customer.created_at }}
            </el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>

      <el-col :span="16">
        <el-card shadow="never">
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <b>跟进记录</b>
              <span style="color:#909399;font-size:13px">共 {{ followupList.length }} 条</span>
            </div>
          </template>
          <el-timeline>
            <el-timeline-item
              v-for="item in followupList"
              :key="item.id"
              :timestamp="item.followup_time"
              placement="top"
              :type="typeColor(item.type)"
            >
              <div style="line-height:1.7">
                <el-tag size="small" :type="typeColor(item.type)" effect="light" style="margin-right:8px">
                  {{ item.type_label }}
                </el-tag>
                <span>{{ item.content }}</span>
              </div>
              <div v-if="item.next_followup" style="font-size:12px;color:#e6a23c;margin-top:6px">
                <el-icon><Clock /></el-icon> 下次跟进：{{ item.next_followup }}
              </div>
            </el-timeline-item>
          </el-timeline>
        </el-card>
      </el-col>
    </el-row>

    <el-dialog v-model="followupVisible" title="新增跟进" width="480px">
      <el-form :model="followupForm" label-width="90px">
        <el-form-item label="跟进类型" required>
          <el-select v-model="followupForm.type" style="width:100%">
            <el-option label="电话沟通" value="call" />
            <el-option label="上门拜访" value="meeting" />
            <el-option label="微信沟通" value="wechat" />
            <el-option label="邮件往来" value="email" />
            <el-option label="合同签约" value="sign" />
          </el-select>
        </el-form-item>
        <el-form-item label="跟进内容" required>
          <el-input v-model="followupForm.content" type="textarea" :rows="4" placeholder="请输入跟进内容" />
        </el-form-item>
        <el-form-item label="下次跟进">
          <el-date-picker v-model="followupForm.next_followup" type="datetime" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="followupVisible = false">取消</el-button>
        <el-button type="primary" @click="submitFollowup">提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft, ChatDotRound, Phone, Message, Clock } from '@element-plus/icons-vue'
import { getCustomerDetail, getFollowupList, createFollowup } from '@/api'

const route = useRoute()
const customerId = Number(route.params.id) || 0

const customer = ref({})
const followupList = ref([])

const followupVisible = ref(false)
const followupForm = reactive({
  type: 'call',
  content: '',
  next_followup: null
})

function typeColor(type) {
  const map = { call: 'primary', meeting: 'danger', wechat: 'success', email: 'warning', sign: 'success' }
  return map[type] || 'info'
}

async function loadDetail() {
  try {
    const res = await getCustomerDetail({ id: customerId })
    if (res.code === 0) customer.value = res.data
  } catch (e) {}

  try {
    const res = await getFollowupList({ customer_id: customerId, page_size: 50 })
    if (res.code === 0) followupList.value = res.data.list
  } catch (e) {}
}

function showFollowupDialog() {
  Object.assign(followupForm, { type: 'call', content: '', next_followup: null })
  followupVisible.value = true
}

async function submitFollowup() {
  if (!followupForm.content) {
    ElMessage.warning('请输入跟进内容')
    return
  }
  try {
    await createFollowup({
      customer_id: customerId,
      customer_name: customer.value.name,
      ...followupForm
    })
    ElMessage.success('跟进已保存')
    followupVisible.value = false
    loadDetail()
  } catch (e) {
  }
}

onMounted(loadDetail)
</script>
