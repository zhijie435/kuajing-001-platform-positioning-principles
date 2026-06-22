<template>
  <div class="page-container">
    <div class="page-header">
      <h2>审计日志</h2>
      <el-tag type="info" effect="plain">记录所有系统操作和安全事件</el-tag>
    </div>

    <el-card shadow="never">
      <el-table :data="logs" v-loading="loading" stripe>
        <el-table-column prop="id" label="日志ID" width="90" />
        <el-table-column prop="time" label="时间" width="180" />
        <el-table-column prop="user" label="操作人" width="100" />
        <el-table-column label="平台" width="90">
          <template #default="{ row }">
            <el-tag v-if="row.platform === 'admin'" type="danger" size="small">管理端</el-tag>
            <el-tag v-else-if="row.platform === 'sales'" type="success" size="small">销售端</el-tag>
            <el-tag v-else type="warning" size="small">客户端</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180">
          <template #default="{ row }">
            <span>{{ row.action_label }}</span>
            <el-tag size="small" style="margin-left:6px">{{ row.action }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'success'" type="success" size="small">成功</el-tag>
            <el-tag v-else-if="row.status === 'blocked'" type="danger" effect="dark" size="small">拦截</el-tag>
            <el-tag v-else type="warning" size="small">失败</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="ip" label="IP地址" width="140" />
        <el-table-column prop="device" label="设备" width="160" />
      </el-table>

      <el-pagination
        style="margin-top:16px;justify-content:flex-end;display:flex"
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        layout="total, prev, pager, next"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getAuditLogs } from '@/api'

const loading = ref(false)
const logs = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)

async function loadLogs() {
  loading.value = true
  try {
    const res = await getAuditLogs({ page: page.value, page_size: pageSize.value })
    if (res.code === 0) {
      logs.value = res.data.list
      total.value = res.data.total
    }
  } finally {
    loading.value = false
  }
}

onMounted(loadLogs)
</script>
