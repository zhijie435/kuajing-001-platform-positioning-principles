import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/login/Login.vue'),
    meta: { title: '登录', requiresAuth: false }
  },
  {
    path: '/',
    name: 'Layout',
    component: () => import('@/layout/MainLayout.vue'),
    redirect: '/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/dashboard/Dashboard.vue'),
        meta: { title: '工作台', icon: 'Odometer' }
      },
      {
        path: 'customer',
        name: 'Customer',
        component: () => import('@/views/customer/CustomerList.vue'),
        meta: { title: '客户管理', icon: 'User' }
      },
      {
        path: 'follow',
        name: 'Follow',
        component: () => import('@/views/follow/FollowList.vue'),
        meta: { title: '跟进记录', icon: 'ChatDotRound' }
      },
      {
        path: 'audit',
        name: 'Audit',
        component: () => import('@/views/admin/AuditManage.vue'),
        meta: { title: '审计管理', icon: 'Document', roles: ['admin'] }
      },
      {
        path: 'license',
        name: 'License',
        component: () => import('@/views/admin/LicenseManage.vue'),
        meta: { title: '许可证管理', icon: 'Key', roles: ['admin'] }
      },
      {
        path: 'redline',
        name: 'RedLine',
        component: () => import('@/views/admin/RedLineConfig.vue'),
        meta: { title: '红线配置', icon: 'Warning', roles: ['admin'] }
      },
      {
        path: 'guard',
        name: 'Guard',
        component: () => import('@/views/admin/GuardInfo.vue'),
        meta: { title: '守护信息', icon: 'Shield', roles: ['admin'] }
      }
    ]
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/views/error/NotFound.vue'),
    meta: { title: '页面不存在' }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  document.title = to.meta.title ? `${to.meta.title} - CRM客户跟进系统` : 'CRM客户跟进系统'
  
  const token = localStorage.getItem('token')
  
  if (to.meta.requiresAuth === false) {
    next()
  } else if (!token) {
    next('/login')
  } else {
    next()
  }
})

export default router
