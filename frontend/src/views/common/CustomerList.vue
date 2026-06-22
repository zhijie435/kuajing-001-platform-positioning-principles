<template>
  <div class="page-container">
    <div class="page-header">
      <h2>客户管理</h2>
      <div>
        <el-button type="primary" :disabled="!quota.can_create" @click="showCreateDialog">
          <el-icon><Plus /></el-icon>新增客户
        </el-button>
        <el-tag v-if="!quota.can_create" type="danger" effect="light" style="margin-left:8px">
          客户数已达上限 ({{ quota.used }}/{{ quota.limit }})
        </el-tag>
      </div>
    </div>

    <el-card shadow="never">
      <div class="filter-bar">
        <el-input v-model="filter.keyword" placeholder="搜索客户名称/公司/电话" clearable style="width:260px">
          <template #prefix><el-icon><Search /></el-icon></template>
        </el-input>
        <el-select v-model="filter.level" placeholder="客户等级" clearable style="width:140px">
          <el-option label="A级-重点" value="A" />
          <el-option label="B级-普通" value="B" />
          <el-option label="C级-潜在" value="C" />
        </el-select>
        <el-select v-model="filter.status" placeholder="客户状态" clearable style="width:140px">
          <el-option label="潜在客户" value="potential" />
          <el-option label="跟进中" value="negotiating" />
          <el-option label="活跃客户" value="active" />
        </el-select>
      </div>

      <el-table :data="customerList" v-loading="loading" stripe style="margin-top:16px">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="客户信息" min-width="200">
          <template #default="{ row }">
            <div style="display:flex;align-items:center;gap:10px">
              <el-avatar :size="36" style="background:#409eff">
                {{ row.name.charAt(0) }}
              </el-avatar>
              <div>
                <div style="font-weight:600">{{ row.name }}</div>
                <div style="color:#909399;font-size:12px">{{ row.company }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="phone" label="电话" width="140" />
        <el-table-column prop="level" label="等级" width="90">
          <template #default="{ row }">
            <el-tag v-if="row.level === 'A'" type="danger" effect="dark">A级</el-tag>
            <el-tag v-else-if="row.level === 'B'" type="primary">B级</el-tag>
            <el-tag v-else type="success">C级</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'active'" type="success">活跃</el-tag>
            <el-tag v-else-if="row.status === 'negotiating'" type="warning">跟进中</el-tag>
            <el-tag v-else type="info">潜在</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="amount" label="成交金额" width="120">
          <template #default="{ row }">
            <span style="color:#f56c6c;font-weight:600">¥{{ (row.amount || 0).toLocaleString() }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="owner_name" label="负责人" width="100" />
        <el-table-column prop="created_at" label="创建时间" width="170" />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button size="small" link type="primary" @click="viewDetail(row)">查看</el-button>
            <el-button size="small" link type="primary" @click="editCustomer(row)">编辑</el-button>
            <el-button size="small" link type="danger" @click="deleteCustomer(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        style="margin-top:16px;justify-content:flex-end;display:flex"
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next, jumper"
      />
    </el-card>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="560px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="客户姓名" required>
          <el-input v-model="form.name" placeholder="请输入客户姓名" />
        </el-form-item>
        <el-form-item label="公司名称" required>
          <el-input v-model="form.company" placeholder="请输入公司名称" />
        </el-form-item>
        <el-form-item label="联系电话">
          <el-input v-model="form.phone" placeholder="请输入电话" />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input v-model="form.email" placeholder="请输入邮箱" />
        </el-form-item>
        <el-form-item label="客户等级">
          <el-select v-model="form.level" style="width:100%">
            <el-option label="A级-重点客户" value="A" />
            <el-option label="B级-普通客户" value="B" />
            <el-option label="C级-潜在客户" value="C" />
          </el-select>
        </el-form-item>
        <el-form-item label="客户状态">
          <el-select v-model="form.status" style="width:100%">
            <el-option label="潜在客户" value="potential" />
            <el-option label="跟进中" value="negotiating" />
            <el-option label="活跃客户" value="active" />
          </el-select>
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
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search } from '@element-plus/icons-vue'
import { getCustomerList, createCustomer, updateCustomer, deleteCustomer } from '@/api/customer'

const router = useRouter()
const loading = ref(false)
const customerList = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(10)
const quota = ref({ used: 0, limit: 9999, can_create: true })

const filter = reactive({ keyword: '', level: '', status: '' })

const dialogVisible = ref(false)
const dialogTitle = ref('新增客户')
const form = reactive({
  id: null,
  name: '',
  company: '',
  phone: '',
  email: '',
  level: 'B',
  status: 'potential'
})

async function loadList() {
  loading.value = true
  try {
    const res = await getCustomerList({
      page: page.value,
      page_size: pageSize.value,
      ...filter
    })
    if (res.code === 0) {
      customerList.value = res.data.list
      total.value = res.data.total
      quota.value = res.data.quota
    }
  } finally {
    loading.value = false
  }
}

function showCreateDialog() {
  form.id = null
  form.name = ''
  form.company = ''
  form.phone = ''
  form.email = ''
  form.level = 'B'
  form.status = 'potential'
  dialogTitle.value = '新增客户'
  dialogVisible.value = true
}

function editCustomer(row) {
  Object.assign(form, row)
  dialogTitle.value = '编辑客户'
  dialogVisible.value = true
}

async function submitForm() {
  if (!form.name || !form.company) {
    ElMessage.warning('客户姓名和公司名称不能为空')
    return
  }
  try {
    if (form.id) {
      await updateCustomer(form)
    } else {
      await createCustomer(form)
    }
    ElMessage.success('保存成功')
    dialogVisible.value = false
    loadList()
  } catch (e) {
  }
}

async function deleteCustomer(row) {
  try {
    await ElMessageBox.confirm(`确认删除客户【${row.name}】？`, '删除确认', { type: 'warning' })
    await deleteCustomer({ id: row.id })
    ElMessage.success('已删除')
    loadList()
  } catch (e) {}
}

function viewDetail(row) {
  router.push(`/customer/${row.id}`)
}

onMounted(loadList)
</script>

<style lang="scss" scoped>
.filter-bar {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
</style>
