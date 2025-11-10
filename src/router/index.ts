import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import { useUserRole } from '@/composables/useUserRole'
import { useCurrentUser } from '@/composables/useCurrentUser'

const routes: RouteRecordRaw[] = [
  { path: '/', component: () => import('@/views/CategoriesView.vue') },
  { path: '/category/:id', component: () => import('@/views/CategoryView.vue') },
  { path: '/c/:slug', component: () => import('@/views/CategoryView.vue') },
  { path: '/category/:categoryId/new', component: () => import('@/views/CreateThreadView.vue') },
  { path: '/c/:categorySlug/new', component: () => import('@/views/CreateThreadView.vue') },
  { path: '/thread/:id', component: () => import('@/views/ThreadView.vue') },
  { path: '/t/:slug', component: () => import('@/views/ThreadView.vue') },
  { path: '/u/:userId', component: () => import('@/views/ProfileView.vue') },
  { path: '/search', component: () => import('@/views/SearchView.vue') },
  { path: '/admin', component: () => import('@/views/admin/AdminDashboard.vue') },
  { path: '/admin/users', component: () => import('@/views/admin/AdminUserList.vue') },
  { path: '/admin/roles', component: () => import('@/views/admin/AdminRoleList.vue') },
  { path: '/admin/roles/create', component: () => import('@/views/admin/AdminRoleEdit.vue') },
  { path: '/admin/roles/:id/edit', component: () => import('@/views/admin/AdminRoleEdit.vue') },
  { path: '/admin/categories', component: () => import('@/views/admin/AdminCategoryList.vue') },
  {
    path: '/admin/categories/create',
    component: () => import('@/views/admin/AdminCategoryEdit.vue'),
  },
  {
    path: '/admin/categories/:id/edit',
    component: () => import('@/views/admin/AdminCategoryEdit.vue'),
  },
  { path: '/admin/bbcodes', component: () => import('@/views/admin/AdminBBCodeList.vue') },
  // Catch-all route - must be last
  { path: '/:pathMatch(.*)*', component: () => import('@/views/CategoriesView.vue') },
]

const router = createRouter({
  history: createWebHistory(generateUrl('/apps/forum')),
  routes,
})

// Route guard to protect admin routes
router.beforeEach(async (to, from, next) => {
  // Check if the route is an admin route
  if (to.path.startsWith('/admin')) {
    const { isAdmin, fetchUserRoles, loaded } = useUserRole()
    const { fetchCurrentUser } = useCurrentUser()

    // Fetch user and roles if not already loaded
    if (!loaded.value) {
      const user = await fetchCurrentUser()
      if (user) {
        await fetchUserRoles(user.userId)
      }
    }

    // Redirect non-admin users to home
    if (!isAdmin.value) {
      console.warn('Access denied to admin area - redirecting to home')
      next('/')
      return
    }
  }

  next()
})

export default router
