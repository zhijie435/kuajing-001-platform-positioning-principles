<template>
  <div class="page-container">
    <div class="page-header">
      <h2>我的信息</h2>
    </div>

    <el-row :gutter="20">
      <el-col :span="8">
        <el-card shadow="never" class="profile-card">
          <div style="text-align:center;padding:20px 0">
            <el-avatar :size="84" style="background:#e6a23c;font-size:32px">
              {{ profile.name?.charAt(0) || 'C' }}
            </el-avatar>
            <h3 style="margin:14px 0 4px">{{ profile.name }}</h3>
            <div style="color:#874d00;font-size:13px">{{ profile.company }}</div>
            <el-tag type="warning" effect="dark" style="margin-top:10px">
              {{ profile.level }}
            </el-tag>
          </div>
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="合作时间">
              {{ profile.join_date }}
            </el-descriptions-item>
            <el-descriptions-item label="我的销售">
              <el-icon><UserFilled /></el-icon> {{ profile.contact }}
            </el-descriptions-item>
            <el-descriptions-item label="联系电话">
              <el-icon><Phone /></el-icon> {{ profile.contact_phone }}
            </el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>

      <el-col :span="16">
        <el-card shadow="never">
          <template #header><b>公司信息</b></template>
          <el-descriptions :column="2" border>
            <el-descriptions-item label="公司名称">
              {{ profile.company }}
            </el-descriptions-item>
            <el-descriptions-item label="所属行业">
              {{ profile.company_info?.industry || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="公司规模">
              {{ profile.company_info?.scale || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="公司地址">
              {{ profile.company_info?.address || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="联系电话" :span="2">
              {{ profile.phone }}
            </el-descriptions-item>
            <el-descriptions-item label="电子邮箱" :span="2">
              {{ profile.email }}
            </el-descriptions-item>
          </el-descriptions>
        </el-card>

        <el-card shadow="never" style="margin-top:20px">
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <b>合同概览</b>
              <router-link to="/contracts" style="color:#e6a23c;font-size:13px">查看全部 →</router-link>
            </div>
          </template>
          <el-row :gutter="16">
            <el-col :span="8">
              <div class="contract-stat">
                <div class="stat-label">合同总数</div>
                <div class="stat-value">{{ contractSummary?.total || 0 }} 份</div>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="contract-stat">
                <div class="stat-label">执行中</div>
                <div class="stat-value" style="color:#67c23a">{{ contractSummary?.active_count || 0 }} 份</div>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="contract-stat">
                <div class="stat-label">累计金额</div>
                <div class="stat-value" style="color:#e6a23c">¥{{ formatNumber(contractSummary?.total_amount || 0) }}</div>
              </div>
            </el-col>
          </el-row>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getClientProfile, getClientContracts } from '@/api'
import { UserFilled, Phone } from '@element-plus/icons-vue'

const profile = ref({
  name: '',
  company: '',
  level: '',
  contact: '',
  contact_phone: '',
  company_info: {}
})

const contractSummary = ref({ total: 0, active_count: 0, total_amount: 0 })

function formatNumber(n) {
  if (!n) return '0'
  if (n >= 10000) return (n / 10000).toFixed(1) + '万'
  return n.toLocaleString()
}

async function loadData() {
  try {
    const res = await getClientProfile()
    if (res.code === 0) profile.value = res.data
  } catch (e) {}

  try {
    const res = await getClientContracts()
    if (res.code === 0) contractSummary.value = res.data.summary
  } catch (e) {}
}

onMounted(loadData)
</script>

<style lang="scss" scoped>
.profile-card {
  background: linear-gradient(180deg, #fff7e6 0%, #ffffff 60%);
}

.contract-stat {
  background: #fffbe6;
  border: 1px solid #ffe58f;
  border-radius: 8px;
  padding: 16px;
  text-align: center;

  .stat-label {
    font-size: 12px;
    color: #874d00;
    margin-bottom: 6px;
  }

  .stat-value {
    font-size: 20px;
    font-weight: 600;
    color: #ad6800;
  }
}
</style>
