<template>
  <div class="page-container">
    <div class="page-header">
      <h2>用户管理</h2>
      <div>
        <el-button type="primary" :disabled="!quota.can_create" @click="showCreateDialog">
          <el-icon><Plus /></el-icon>新增用户
        </el-button>
        <el-tag v-if="!quota.can_create" type="danger" effect="light" style="margin-left:8px">
          用户数已达上限 ({{ quota.used }}/{{ quota.limit }})
        </el-tag>
      </div>
    </div>

    <el-card shadow="never">
      <el-table :data="userList" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="用户信息" min-width="180">
          <template #default="{ row }">
            <div style="display:flex;align-items:center;gap:10px">
              <el-avatar :size="36" style="background:#409eff">
                {{ row.name.charAt(0) }}
              </el-avatar>
              <div>
                <div style="font-weight:600">{{ row.name }}</div>
                <div style="color:#909399;font-size:12px">{{ row.username }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="role_label" label="角色" width="110">
          <template #default="{ row }">
            <el-tag v-if="row.role === 'super_admin'" type="danger" effect="dark">超级管理员</el-tag>
            <el-tag v-else-if="row.role === 'admin'" type="danger">管理员</el-tag>
            <el-tag v-else-if="row.role === 'sales_manager'" type="warning">销售主管</el-tag>
            <el-tag v-else-if="row.role === 'sales'" type="primary">销售代表</el-tag>
            <el-tag v-else type="info">客户用户</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="平台" width="90">
          <template #default="{ row }">
            <el-tag v-if="row.platform === 'admin'" type="danger" size="small">管理端</el-tag>
            <el-tag v-else-if="row.platform === 'sales'" type="success" size="small">销售端</el-tag>
            <el-tag v-else type="warning" size="small">客户端</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
              {{ row.status === 'active' ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="last_login" label="最后登录" width="170" />
        <el-table-column prop="created_at" label="创建时间" width="170" />
      </el-table>

      <el-pagination
        style="margin-top:16px;justify-content:flex-end;display:flex"
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        layout="total, prev, pager, next"
      />
    </el-card>

    <el-dialog v-model="dialogVisible" title="新增用户" width="480px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="登录账号" required>
          <el-input v-model="form.username" placeholder="请输入登录账号" />
        </el-form-item>
        <el-form-item label="姓名" required>
          <el-input v-model="form.name" placeholder="请输入姓名" />
        </el-form-item>
        <el-form-item label="初始密码" required>
          <el-input v-model="form.password" type="password" show-password placeholder="请输入初始密码" />
        </el-form-item>
        <el-form-item label="角色" required>
          <el-select v-model="form.role" style="width:100%">
            <el-option v-for="opt in roleOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
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
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getAdminUserList, createAdminUser } from '@/api'

const loading = ref(false)
const userList = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(10)
const quota = ref({ used: 0, limit: 9999, can_create: true })

const roleOptions = [
  { value: 'super_admin', label: '超级管理员' },
  { value: 'admin', label: '系统管理员' },
  { value: 'sales_manager', label: '销售主管' },
  { value: 'sales', label: '销售代表' },
  { value: 'client', label: '客户用户' }
]

const dialogVisible = ref(false)
const form = reactive({ username: '', name: '', password: '', role: 'sales' })

async function loadList() {
  loading.value = true
  try {
    const res = await getAdminUserList({ page: page.value, page_size: pageSize.value })
    if (res.code === 0) {
      userList.value = res.data.list
      total.value = res.data.total
      quota.value = res.data.quota
    }
  } finally {
    loading.value = false
  }
}

function showCreateDialog() {
  Object.assign(form, { username: '', name: '', password: '', role: 'sales' })
  dialogVisible.value = true
}

async function submitForm() {
  if (!form.username || !form.name || !form.password) {
    ElMessage.warning('请填写完整信息')
    return
  }
  try {
    await createAdminUser(form)
    ElMessage.success('用户创建成功')
    dialogVisible.value = false
    loadList()
  } catch (e) {
  }
}

onMounted(loadList)
</script>
