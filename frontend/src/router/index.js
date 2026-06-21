import { createRouter, createWebHashHistory } from 'vue-router'
import { useUserStore } from '@/store/user'

const platform = document.querySelector('meta[name="platform"]')?.content
  || import.meta.env.VITE_PLATFORM
  || 'sales'

function getRoutes() {
  const baseRoutes = [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/Login.vue'),
      meta: { title: '登录', requiresAuth: false }
    },
    { path: '/', redirect: '/dashboard' },
    { path: '/:pathMatch(.*)*', redirect: '/dashboard' }
  ]

  if (platform === 'admin') {
    return [
      baseRoutes[0],
      {
        path: '/',
        component: () => import('@/layouts/AdminLayout.vue'),
        children: [
          { path: '', redirect: '/dashboard' },
          {
            path: 'dashboard',
            name: 'dashboard',
            component: () => import('@/views/admin/Dashboard.vue'),
            meta: { title: '运营总览', icon: 'DataBoard' }
          },
          {
            path: 'user',
            name: 'user',
            component: () => import('@/views/admin/UserManage.vue'),
            meta: { title: '用户管理', icon: 'User' }
          },
          {
            path: 'customer',
            name: 'customer',
            component: () => import('@/views/common/CustomerList.vue'),
            meta: { title: '客户管理', icon: 'UserFilled' }
          },
          {
            path: 'license',
            name: 'license',
            component: () => import('@/views/admin/LicenseManage.vue'),
            meta: { title: 'License管理', icon: 'Key' }
          },
          {
            path: 'audit',
            name: 'audit',
            component: () => import('@/views/admin/AuditLog.vue'),
            meta: { title: '审计日志', icon: 'Document' }
          }
        ]
      },
      baseRoutes[2]
    ]
  }

  if (platform === 'client') {
    return [
      baseRoutes[0],
      {
        path: '/',
        component: () => import('@/layouts/ClientLayout.vue'),
        children: [
          { path: '', redirect: '/profile' },
          {
            path: 'profile',
            name: 'profile',
            component: () => import('@/views/client/Profile.vue'),
            meta: { title: '我的信息' }
          },
          {
            path: 'contracts',
            name: 'contracts',
            component: () => import('@/views/client/Contracts.vue'),
            meta: { title: '我的合同' }
          }
        ]
      },
      baseRoutes[2]
    ]
  }

  return [
    baseRoutes[0],
    {
      path: '/',
      component: () => import('@/layouts/SalesLayout.vue'),
      children: [
        { path: '', redirect: '/dashboard' },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: () => import('@/views/sales/Dashboard.vue'),
          meta: { title: '工作台', icon: 'DataBoard' }
        },
        {
          path: 'customer',
          name: 'customer',
          component: () => import('@/views/common/CustomerList.vue'),
          meta: { title: '客户管理', icon: 'UserFilled' }
        },
        {
          path: 'customer/:id',
          name: 'customerDetail',
          component: () => import('@/views/common/CustomerDetail.vue'),
          meta: { title: '客户详情', hidden: true }
        },
        {
          path: 'followup',
          name: 'followup',
          component: () => import('@/views/sales/FollowupList.vue'),
          meta: { title: '跟进记录', icon: 'ChatDotRound' }
        },
        {
          path: 'opportunity',
          name: 'opportunity',
          component: () => import('@/views/sales/OpportunityList.vue'),
          meta: { title: '商机管理', icon: 'Money' }
        }
      ]
    },
    baseRoutes[2]
  ]
}

const router = createRouter({
  history: createWebHashHistory(),
  routes: getRoutes()
})

router.beforeEach(async (to, from, next) => {
  const userStore = useUserStore()
  document.title = (to.meta?.title ? to.meta.title + ' - ' : '') + import.meta.env.VITE_APP_TITLE

  if (to.path === '/login') {
    if (userStore.isLoggedIn) {
      next('/')
    } else {
      next()
    }
    return
  }

  if (to.meta?.requiresAuth === false) {
    next()
    return
  }

  if (!userStore.isLoggedIn) {
    localStorage.setItem('crm_redirect', to.fullPath)
    next({ path: '/login', query: { redirect: to.fullPath } })
    return
  }

  if (userStore.userInfo && userStore.userInfo.platform && userStore.userInfo.platform !== platform) {
    await userStore.doLogout()
    next({
      path: '/login',
      query: { error: `当前账号只允许访问${userStore.userInfo.platform}端` }
    })
    return
  }

  next()
})

export default router
