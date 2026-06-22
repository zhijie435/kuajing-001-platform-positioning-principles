import request from './request'

export function login(data) {
  return request({
    url: '/auth/login',
    method: 'post',
    data
  })
}

export function logout() {
  return request({
    url: '/auth/logout',
    method: 'post'
  })
}

export function getGuardInfo() {
  return request({
    url: '/guard/info',
    method: 'get'
  })
}

export function verifyGuard() {
  return request({
    url: '/guard/verify',
    method: 'post'
  })
}

export function getAuditList(params) {
  return request({
    url: '/admin/audit/list',
    method: 'get',
    params
  })
}

export function getAuditDetail(id) {
  return request({
    url: `/admin/audit/detail/${id}`,
    method: 'get'
  })
}

export function getLicenseList(params) {
  return request({
    url: '/admin/license/list',
    method: 'get',
    params
  })
}

export function createLicense(data) {
  return request({
    url: '/admin/license/create',
    method: 'post',
    data
  })
}

export function updateLicense(id, data) {
  return request({
    url: `/admin/license/update/${id}`,
    method: 'put',
    data
  })
}

export function deleteLicense(id) {
  return request({
    url: `/admin/license/delete/${id}`,
    method: 'delete'
  })
}

export function activateLicense(data) {
  return request({
    url: '/admin/license/activate',
    method: 'post',
    data
  })
}

export function getRedLineConfig(params) {
  return request({
    url: '/admin/redline/config',
    method: 'get',
    params
  })
}

export function updateRedLineConfig(data) {
  return request({
    url: '/admin/redline/config',
    method: 'put',
    data
  })
}

export function getCustomerList(params) {
  return request({
    url: '/customer/list',
    method: 'get',
    params
  })
}

export function getCustomerDetail(id) {
  return request({
    url: `/customer/detail/${id}`,
    method: 'get'
  })
}

export function createCustomer(data) {
  return request({
    url: '/customer/create',
    method: 'post',
    data
  })
}

export function updateCustomer(id, data) {
  return request({
    url: `/customer/update/${id}`,
    method: 'put',
    data
  })
}

export function getFollowList(params) {
  return request({
    url: '/follow/list',
    method: 'get',
    params
  })
}

export function createFollow(data) {
  return request({
    url: '/follow/create',
    method: 'post',
    data
  })
}
