<template>
  <div class="page-container">
    <div class="page-header">
      <h2>运营总览</h2>
    </div>

    <div class="stat-row">
      <div v-for="s in statCards" :key="s.label" class="stat-card">
        <div class="stat-label">{{ s.label }}</div>
        <div class="stat-value" :class="s.type">{{ s.value }}</div>
        <div class="stat-trend">{{ s.trend }}</div>
      </div>
    </div>

    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><b>系统运行状态</b></template>
          <el-table :data="systemStatus" stripe>
            <el-table-column prop="name" label="监控项" width="160" />
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="row.ok ? 'success' : 'danger'" size="small">
                  {{ row.ok ? '正常' : '异常' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="value" label="详情" />
          </el-table>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><b>安全红线事件</b></template>
          <el-table :data="redlineEvents" stripe>
            <el-table-column prop="time" label="时间" width="170" />
            <el-table-column prop="type" label="类型" width="140">
              <template #default="{ row }">
                <el-tag type="danger" size="small">{{ row.type }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="message" label="详情" />
          </el-table>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :span="10">
        <el-card shadow="never">
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <b>客户等级分布</b>
              <el-tag type="info" size="small">共 156 位</el-tag>
            </div>
          </template>
          <div ref="pieChartRef" style="height:280px"></div>
        </el-card>
      </el-col>
      <el-col :span="14">
        <el-card shadow="never">
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <b>近30日业务趋势</b>
              <el-tag type="primary" size="small">增长趋势良好</el-tag>
            </div>
          </template>
          <div ref="lineChartRef" style="height:280px"></div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import * as echarts from 'echarts'

const statCards = ref([
  { label: '注册用户数', value: '156', type: 'primary', trend: '较上月 +12.5%' },
  { label: '客户总数', value: '1,248', type: 'success', trend: '本月新增 86' },
  { label: '商机总额', value: '¥876.5万', type: 'warning', trend: '加权 ¥412.3万' },
  { label: '本月成交', value: '¥285.6万', type: 'danger', trend: '达成率 95.2%' }
])

const systemStatus = ref([
  { name: '数据库连接', ok: true, value: '响应 12ms' },
  { name: 'License 状态', ok: true, value: '剩余 365 天' },
  { name: '用户配额', ok: true, value: '6 / 100 (6%)' },
  { name: '客户配额', ok: true, value: '1,248 / 10,000 (12.5%)' },
  { name: 'API 请求频率', ok: true, value: '45次/分钟 (正常)' },
  { name: 'IP 白名单', ok: true, value: '已启用 4 条规则' }
])

const redlineEvents = ref([
  { time: '2026-06-21 08:55:12', type: '设备指纹异常', message: 'IP 45.33.32.156 疑似凭证劫持' },
  { time: '2026-06-21 08:42:03', type: '越权访问', message: 'sales02 尝试访问管理端接口' },
  { time: '2026-06-20 22:15:48', type: '非工作时段', message: 'admin 非工作时段登录 (22:15)' },
  { time: '2026-06-20 14:30:22', type: '功能越界', message: '标准版用户尝试访问 API 接口' }
])

const pieChartRef = ref(null)
const lineChartRef = ref(null)

onMounted(() => {
  nextTick(() => {
    if (pieChartRef.value) {
      echarts.init(pieChartRef.value).setOption({
        tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
        legend: { bottom: 5 },
        series: [{
          type: 'pie',
          radius: ['45%', '70%'],
          label: { formatter: '{b}\n{d}%' },
          data: [
            { value: 28, name: 'A级-重点', itemStyle: { color: '#f5222d' } },
            { value: 65, name: 'B级-普通', itemStyle: { color: '#1890ff' } },
            { value: 63, name: 'C级-潜在', itemStyle: { color: '#52c41a' } }
          ]
        }]
      })
    }
    if (lineChartRef.value) {
      const dates = Array.from({ length: 30 }, (_, i) => `${i + 1}日`)
      echarts.init(lineChartRef.value).setOption({
        tooltip: { trigger: 'axis' },
        legend: { data: ['新增客户', '跟进次数'], right: 10 },
        grid: { left: 40, right: 20, top: 40, bottom: 30 },
        xAxis: { type: 'category', data: dates, axisLabel: { interval: 3 } },
        yAxis: { type: 'value' },
        series: [
          {
            name: '新增客户', type: 'line', smooth: true,
            data: dates.map(() => Math.floor(Math.random() * 10) + 2),
            itemStyle: { color: '#409eff' },
            areaStyle: { opacity: 0.1 }
          },
          {
            name: '跟进次数', type: 'line', smooth: true,
            data: dates.map(() => Math.floor(Math.random() * 30) + 10),
            itemStyle: { color: '#67c23a' },
            areaStyle: { opacity: 0.1 }
          }
        ]
      })
    }
  })
})
</script>

<style lang="scss" scoped>
.stat-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}
</style>
