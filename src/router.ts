import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import { useUserRole } from '@/composables/useUserRole'
import { useCurrentUser } from '@/composables/useCurrentUser'
import { usePublicSettings } from '@/composables/usePublicSettings'

const routes: RouteRecordRaw[] = [
  { path: '/', component: () => import('@/views/CategoriesView.vue') },
  { path: '/category/:id', component: () => import('@/views/CategoryView.vue') },
  { path: '/c/:slug', component: () => import('@/views/CategoryView.vue') },
  { path: '/category/:categoryId/new', component: () => import('@/views/CreateThreadView.vue') },
  { path: '/c/:categorySlug/new', component: () => import('@/views/CreateThreadView.vue') },
  { path: '/thread/:id', component: () => import('@/views/ThreadView.vue') },
  { path: '/t/:slug', component: () => import('@/views/ThreadView.vue') },
  { path: '/u/:userId', component: () => import('@/views/ProfileView.vue') },
  { path: '/preferences', component: () => import('@/views/UserPreferencesView.vue') },
  { path: '/search', component: () => import('@/views/SearchView.vue') },
  { path: '/bookmarks', component: () => import('@/views/BookmarksView.vue') },
  { path: '/admin', component: () => import('@/views/admin/AdminDashboard.vue') },
  { path: '/admin/settings', component: () => import('@/views/admin/AdminGeneralSettings.vue') },
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
  { path: '/:pathMatch(.*)*', component: () => import('@/views/GenericNotFound.vue') },
]

const router = createRouter({
  history: createWebHistory(generateUrl('/apps/forum')),
  routes,
  scrollBehavior(to, from, savedPosition) {
    // If there's a hash, don't auto-scroll - let the component handle it
    if (to.hash) {
      return false
    }
    // If there's a saved position (browser back/forward), use it
    if (savedPosition) {
      return savedPosition
    }
    // Otherwise scroll to top
    return { top: 0 }
  },
})

// Route guard to protect admin routes and enforce guest access settings
router.beforeEach(async (to, from, next) => {
  const { userId, fetchCurrentUser } = useCurrentUser()
  const { fetchPublicSettings, allowGuestAccess } = usePublicSettings()

  // Fetch public settings and current user in parallel for better performance
  await Promise.all([fetchPublicSettings(), fetchCurrentUser()])

  // If user is not signed in and guest access is disabled, redirect to login
  if (!userId.value && !allowGuestAccess.value) {
    const redirectUrl = encodeURIComponent(to.fullPath)
    const loginUrl = generateUrl(`/login?redirect_url=${redirectUrl}`)
    console.warn('Guest access disabled and user not signed in - redirecting to login')
    window.location.href = loginUrl
    next(false) // Explicitly cancel navigation
    return
  }

  // Check if the route is an admin route
  if (to.path.startsWith('/admin')) {
    const { isAdmin, fetchUserRoles, loaded } = useUserRole()

    // Fetch user and roles if not already loaded
    if (!loaded.value && userId.value) {
      await fetchUserRoles(userId.value)
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
