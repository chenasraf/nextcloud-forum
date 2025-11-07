import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { generateUrl } from '@nextcloud/router'

const routes: RouteRecordRaw[] = [
  { path: '/', component: () => import('@/views/CategoriesView.vue') },
  { path: '/category/:id', component: () => import('@/views/CategoryView.vue') },
  { path: '/c/:slug', component: () => import('@/views/CategoryView.vue') },
  { path: '/thread/:id', component: () => import('@/views/ThreadView.vue') },
  { path: '/t/:slug', component: () => import('@/views/ThreadView.vue') },
  // Catch-all route - must be last
  { path: '/:pathMatch(.*)*', component: () => import('@/views/CategoriesView.vue') },
]

const router = createRouter({
  history: createWebHistory(generateUrl('/apps/forum')),
  routes,
})

export default router
