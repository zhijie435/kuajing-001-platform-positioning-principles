<template>
  <div class="page-container">
    <div class="page-header">
      <h2>我的合同</h2>
      <div>
        累计金额：<b style="color:#e6a23c;font-size:18px">¥{{ formatNumber(summary?.total_amount || 0) }}</b>
      </div>
    </div>

    <el-card shadow="never">
      <el-table :data="contractList" stripe>
        <el-table-column label="合同编号" width="180">
          <template #default="{ row }">
            <el-link type="primary" @click="viewDetail(row)">
              {{ row.id }}
            </el-link>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="合同名称" min-width="220" />
        <el-table-column prop="type" label="类型" width="120">
          <template #default="{ row }">
            <el-tag size="small">{{ row.type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="合同金额" width="140">
          <template #default="{ row }">
            <b style="color:#e6a23c">¥{{ formatNumber(row.amount) }}</b>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'active'" type="success" effect="dark">执行中</el-tag>
            <el-tag v-else-if="row.status === 'won'" type="success">已完成</el-tag>
            <el-tag v-else-if="row.status === 'pending'" type="warning">待签署</el-tag>
            <el-tag v-else type="info">已结束</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="合同周期" width="220">
          <template #default="{ row }">
            <div style="font-size:13px">
              <div>开始：{{ row.start_date }}</div>
              <div>结束：{{ row.end_date }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="owner" label="对接销售" width="100" />
        <el-table-column label="操作" width="120">
          <template #default="{ row }">
            <el-button size="small" link type="primary" @click="viewDetail(row)">查看</el-button>
            <el-button v-if="row.status === 'pending'" size="small" link type="warning" @click="sign(row)">签署</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getClientContracts } from '@/api'

const contractList = ref([])
const summary = ref({ total: 0, active_count: 0, total_amount: 0 })

function formatNumber(n) {
  if (!n) return '0'
  if (n >= 10000) return (n / 10000).toFixed(1) + '万'
  return n.toLocaleString()
}

function viewDetail(row) {
  ElMessage.info(`查看合同：${row.id}`)
}

function sign(row) {
  ElMessage.success(`合同 ${row.id} 签署成功`)
}

async function loadData() {
  try {
    const res = await getClientContracts()
    if (res.code === 0) {
      contractList.value = res.data.list
      summary.value = res.data.summary
    }
  } catch (e) {}
}

onMounted(loadData)
</script>
