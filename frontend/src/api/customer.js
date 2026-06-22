import { get, post, put, del } from './request'

export function getCustomerList(params) {
  return get('/customer/list', params)
}

export function getCustomerDetail(params) {
  return get('/customer/detail', params)
}

export function createCustomer(data) {
  return post('/customer/create', data)
}

export function updateCustomer(data) {
  return put('/customer/update', data)
}

export function deleteCustomer(data) {
  return del('/customer/delete', data)
}
