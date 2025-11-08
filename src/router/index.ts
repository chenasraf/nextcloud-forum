import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { generateUrl } from '@nextcloud/router'

const routes: RouteRecordRaw[] = [
  { path: '/', component: () => import('@/views/CategoriesView.vue') },
  { path: '/category/:id', component: () => import('@/views/CategoryView.vue') },
  { path: '/c/:slug', component: () => import('@/views/CategoryView.vue') },
  { path: '/thread/:id', component: () => import('@/views/ThreadView.vue') },
  { path: '/t/:slug', component: () => import('@/views/ThreadView.vue') },
  { path: '/admin', component: () => import('@/views/admin/AdminDashboard.vue') },
  { path: '/admin/users', component: () => import('@/views/admin/AdminUserList.vue') },
  { path: '/admin/roles', component: () => import('@/views/admin/AdminRoleList.vue') },
  { path: '/admin/roles/create', component: () => import('@/views/admin/AdminRoleEdit.vue') },
  { path: '/admin/roles/:id/edit', component: () => import('@/views/admin/AdminRoleEdit.vue') },
  // Catch-all route - must be last
  { path: '/:pathMatch(.*)*', component: () => import('@/views/CategoriesView.vue') },
]

const router = createRouter({
  history: createWebHistory(generateUrl('/apps/forum')),
  routes,
})

export default router
