import { post, get } from './request'

const platform = document.querySelector('meta[name="platform"]')?.content
  || import.meta.env.VITE_PLATFORM
  || 'sales'

export function platformInfo() {
  return get('/auth/platform-info')
}

export function login(data) {
  return post('/auth/login', {
    ...data,
    platform: data.platform || platform
  })
}

export function logout() {
  return post('/auth/logout')
}

export function refreshToken() {
  return post('/auth/refresh')
}

export function checkAuth() {
  return get('/auth/check')
}
