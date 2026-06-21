import request from './request'

export const getAuditList = (params) => {
  return request({
    url: '/audit/list',
    method: 'get',
    params
  })
}

export const getAuditDetail = (params) => {
  return request({
    url: '/audit/detail',
    method: 'get',
    params
  })
}

export const submitAudit = (data) => {
  return request({
    url: '/audit/submit',
    method: 'post',
    data
  })
}

export const approveAudit = (data) => {
  return request({
    url: '/audit/approve',
    method: 'post',
    data
  })
}

export const rejectAudit = (data) => {
  return request({
    url: '/audit/reject',
    method: 'post',
    data
  })
}

export const retryWriteback = (data) => {
  return request({
    url: '/audit/retry-writeback',
    method: 'post',
    data
  })
}

export const getMyPendingAudit = (params) => {
  return request({
    url: '/audit/my-pending',
    method: 'get',
    params
  })
}

export const getAuditStats = (params) => {
  return request({
    url: '/audit/stats',
    method: 'get',
    params
  })
}

export const getRedlineConfig = (params) => {
  return request({
    url: '/admin/redline/config',
    method: 'get',
    params
  })
}

export const updateRedlineConfig = (data) => {
  return request({
    url: '/admin/redline/update',
    method: 'post',
    data
  })
}

export const auditStatusMap = {
  pending: { label: '待审核', type: 'warning', color: '#faad14' },
  approved: { label: '已通过', type: 'success', color: '#52c41a' },
  rejected: { label: '已驳回', type: 'error', color: '#ff4d4f' },
  writeback_success: { label: '回写成功', type: 'success', color: '#1890ff' },
  writeback_failed: { label: '回写失败', type: 'error', color: '#ff4d4f' }
}

export const targetTypeMap = {
  customer: { label: '客户', icon: 'UserFilled' },
  opportunity: { label: '商机', icon: 'Money' },
  contract: { label: '合同', icon: 'Document' }
}

export const operationTypeMap = {
  create: '新增',
  update: '修改',
  delete: '删除',
  level_upgrade: '等级升级',
  stage_change: '阶段变更',
  won: '赢单',
  lost: '输单',
  sign: '签署',
  cancel: '取消'
}

export const platformMap = {
  admin: { label: '管理端', color: '#1890ff' },
  sales: { label: '销售端', color: '#52c41a' },
  client: { label: '客户端', color: '#722ed1' }
}
