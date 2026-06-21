import { get, post, put } from './request'

export function getFollowupList(params) {
  return get('/followup/list', params)
}

export function createFollowup(data) {
  return post('/followup/create', data)
}

export function getOpportunityList(params) {
  return get('/opportunity/list', params)
}

export function createOpportunity(data) {
  return post('/opportunity/create', data)
}

export function updateOpportunity(data) {
  return put('/opportunity/update', data)
}

export function getDashboardStats() {
  return get('/dashboard/stats')
}

export function getAdminUserList(params) {
  return get('/admin/user/list', params)
}

export function createAdminUser(data) {
  return post('/admin/user/create', data)
}

export function getLicenseInfo() {
  return get('/admin/license/info')
}

export function verifyLicense(data) {
  return post('/admin/license/verify', data)
}

export function getLicenseList(params) {
  return get('/admin/license/list', params)
}

export function getLicenseDetail(params) {
  return get('/admin/license/detail', params)
}

export function activateLicense(data) {
  return post('/admin/license/activate', data)
}

export function getAuditLogs(params) {
  return get('/admin/audit/logs', params)
}

export function getClientProfile() {
  return get('/client/profile')
}

export function getClientContracts() {
  return get('/client/contracts')
}

export function getAuditList(params) {
  return get('/audit/list', params)
}

export function getAuditDetail(params) {
  return get('/audit/detail', params)
}

export function submitAudit(data) {
  return post('/audit/submit', data)
}

export function approveAudit(data) {
  return post('/audit/approve', data)
}

export function rejectAudit(data) {
  return post('/audit/reject', data)
}

export function retryWriteback(data) {
  return post('/audit/retry-writeback', data)
}

export function getMyPendingAudit(params) {
  return get('/audit/my-pending', params)
}

export function getAuditStats(params) {
  return get('/audit/stats', params)
}

export function getRedlineConfig(params) {
  return get('/admin/redline/config', params)
}

export function updateRedlineConfig(data) {
  return post('/admin/redline/update', data)
}
