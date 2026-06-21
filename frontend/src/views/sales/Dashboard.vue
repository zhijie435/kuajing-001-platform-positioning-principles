<template>
  <div class="page-container">
    <div class="page-header">
      <h2>工作台</h2>
      <el-tag size="large" effect="plain" type="primary">
        {{ userName }}，欢迎回来！
      </el-tag>
    </div>

    <div class="stat-row">
      <div v-for="s in statCards" :key="s.label" class="stat-card">
        <div class="stat-label">{{ s.label }}</div>
        <div class="stat-value" :class="s.type">{{ formatNumber(s.value) }}</div>
        <div class="stat-trend">{{ s.trend }}</div>
      </div>
    </div>

    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :span="14">
        <el-card shadow="never">
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <b>近7日业务趋势</b>
              <el-tag type="info" size="small">单位：元 / 个</el-tag>
            </div>
          </template>
          <div ref="trendChartRef" style="height: 300px"></div>
        </el-card>
      </el-col>
      <el-col :span="10">
        <el-card shadow="never">
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <b>商机漏斗</b>
              <el-tag type="warning" size="small">加权金额：¥{{ formatNumber(weightedAmount) }}</el-tag>
            </div>
          </template>
          <div ref="funnelChartRef" style="height: 300px"></div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><b>今日待办</b></template>
          <el-timeline>
            <el-timeline-item
              v-for="item in todoList"
              :key="item.id"
              :timestamp="item.time"
              :color="item.color"
            >
              {{ item.title }}
            </el-timeline-item>
          </el-timeline>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><b>销售排行榜</b></template>
          <div v-for="s in salesRanking" :key="s.rank" class="rank-item">
            <span class="rank-num" :class="'rank-' + s.rank">{{ s.rank }}</span>
            <span class="rank-name">{{ s.name }}</span>
            <span class="rank-amount">¥{{ formatNumber(s.amount) }}</span>
            <el-tag size="small" type="success">{{ s.count }}单</el-tag>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useUserStore } from '@/store/user'
import { getDashboardStats } from '@/api'
import * as echarts from 'echarts'

const userStore = useUserStore()
const userName = computed(() => userStore.userName)
const weightedAmount = ref(0)

const statCards = ref([
  { label: '客户总数', value: 0, type: 'primary', trend: '' },
  { label: '本月新增', value: 0, type: 'success', trend: '' },
  { label: '商机总数', value: 0, type: 'warning', trend: '' },
  { label: '本月成交', value: 0, type: 'danger', trend: '' }
])

const trendChartRef = ref(null)
const funnelChartRef = ref(null)

const todoList = [
  { id: 1, title: '联系张伟确认合同细节', time: '10:00', color: '#f56c6c' },
  { id: 2, title: '跟进李娜的报价反馈', time: '14:30', color: '#e6a23c' },
  { id: 3, title: '提交本月工作报告', time: '17:00', color: '#409eff' },
  { id: 4, title: '整理下周拜访客户清单', time: '18:00', color: '#67c23a' }
]

const salesRanking = ref([])

function formatNumber(n) {
  if (!n) return '0'
  if (n >= 10000) return (n / 10000).toFixed(1) + '万'
  return n.toLocaleString()
}

async function loadData() {
  try {
    const res = await getDashboardStats()
    if (res.code === 0) {
      const d = res.data
      statCards.value = [
        { label: '客户总数', value: d.overview.total_customers, type: 'primary', trend: `本月新增 ${d.overview.new_customers_month} 位` },
        { label: '商机总数', value: d.overview.total_opportunities, type: 'warning', trend: `预估总额 ¥${formatNumber(d.overview.total_amount)}` },
        { label: '加权成交', value: d.overview.weighted_amount, type: 'success', trend: '按赢单概率折算' },
        { label: '本月成交', value: d.overview.won_amount_month, type: 'danger', trend: '合同已签署金额' }
      ]
      weightedAmount.value = d.overview.weighted_amount
      salesRanking.value = d.sales_ranking
      nextTick(() => {
        renderTrendChart(d.trend_last_7_days)
        renderFunnelChart(d.conversion_funnel)
      })
    }
  } catch (e) {
    console.warn(e)
  }
}

function renderTrendChart(data) {
  if (!trendChartRef.value) return
  const chart = echarts.init(trendChartRef.value)
  chart.setOption({
    tooltip: { trigger: 'axis' },
    legend: { data: ['新增客户', '跟进次数', '成交金额'], right: 10 },
    grid: { left: 50, right: 20, top: 40, bottom: 30 },
    xAxis: { type: 'category', data: data.map(d => d.date) },
    yAxis: [
      { type: 'value', name: '数量' },
      { type: 'value', name: '金额(万)', axisLabel: { formatter: '{value}万' } }
    ],
    series: [
      {
        name: '新增客户',
        type: 'bar',
        data: data.map(d => d.new_customers),
        itemStyle: { color: '#409eff' }
      },
      {
        name: '跟进次数',
        type: 'bar',
        data: data.map(d => d.followups),
        itemStyle: { color: '#67c23a' }
      },
      {
        name: '成交金额',
        type: 'line',
        yAxisIndex: 1,
        smooth: true,
        data: data.map(d => (d.amount / 10000).toFixed(1)),
        itemStyle: { color: '#f56c6c' },
        lineStyle: { width: 3 }
      }
    ]
  })
}

function renderFunnelChart(data) {
  if (!funnelChartRef.value) return
  const chart = echarts.init(funnelChartRef.value)
  chart.setOption({
    tooltip: { trigger: 'item', formatter: '{b}: {c}个 ({d}%)' },
    series: [{
      type: 'funnel',
      left: '10%',
      width: '80%',
      label: { show: true, position: 'inside', formatter: '{b} {c}' },
      data: data.map((d, i) => ({
        value: d.count,
        name: d.stage,
        itemStyle: {
          color: ['#f56c6c', '#e6a23c', '#409eff', '#909399', '#67c23a'][i]
        }
      }))
    }]
  })
}

onMounted(loadData)
</script>

<style lang="scss" scoped>
.stat-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}

.rank-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px dashed #ebeef5;

  &:last-child { border-bottom: none; }

  .rank-num {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #dcdfe6;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;

    &.rank-1 { background: #f5222d; }
    &.rank-2 { background: #fa8c16; }
    &.rank-3 { background: #fadb14; color: #333; }
  }

  .rank-name { flex: 1; font-size: 14px; }
  .rank-amount { color: #f56c6c; font-weight: 600; min-width: 100px; text-align: right; }
}
</style>
