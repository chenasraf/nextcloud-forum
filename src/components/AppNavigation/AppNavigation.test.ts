import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import {
  createIconMock,
  createComponentMock,
  RouterLinkStub,
  createNcActionButtonMock,
} from '@/test-utils'
import { ref } from 'vue'

// --- Composable mocks ---

const mockFetchCategories = vi.fn().mockResolvedValue([])
const mockGetAllCategoriesFlat = vi.fn(() => [])
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const mockCategoryHeaders = ref<any[]>([])

vi.mock('@/composables/useCategories', () => ({
  useCategories: () => ({
    categoryHeaders: mockCategoryHeaders,
    fetchCategories: mockFetchCategories,
    getAllCategoriesFlat: mockGetAllCategoriesFlat,
  }),
}))

const mockUserId = ref<string | null>(null)
const mockDisplayName = ref('Test User')
const mockFetchCurrentUser = vi.fn().mockResolvedValue(null)

vi.mock('@/composables/useCurrentUser', () => ({
  useCurrentUser: () => ({
    userId: mockUserId,
    displayName: mockDisplayName,
    fetchCurrentUser: mockFetchCurrentUser,
  }),
}))

const mockCanAccessAdmin = ref(false)
const mockCanAccessAdminTools = ref(false)
const mockCanManageUsers = ref(false)
const mockCanEditRoles = ref(false)
const mockCanEditCategories = ref(false)
const mockCanEditBbcodes = ref(false)
const mockCanAccessModeration = ref(false)

vi.mock('@/composables/useUserRole', () => ({
  useUserRole: () => ({
    canAccessAdmin: mockCanAccessAdmin,
    canAccessAdminTools: mockCanAccessAdminTools,
    canManageUsers: mockCanManageUsers,
    canEditRoles: mockCanEditRoles,
    canEditCategories: mockCanEditCategories,
    canEditBbcodes: mockCanEditBbcodes,
    canAccessModeration: mockCanAccessModeration,
  }),
}))

const mockCurrentThreadCategoryId = ref<number | null>(null)
const mockFetchThread = vi.fn()
const mockClearThread = vi.fn()

vi.mock('@/composables/useCurrentThread', () => ({
  useCurrentThread: () => ({
    categoryId: mockCurrentThreadCategoryId,
    fetchThread: mockFetchThread,
    clearThread: mockClearThread,
  }),
}))

const mockIsGuest = ref(false)
const mockGuestDisplayName = ref<string | null>(null)
const mockFetchGuestIdentity = vi.fn().mockResolvedValue(undefined)

vi.mock('@/composables/useGuestSession', () => ({
  useGuestSession: () => ({
    isGuest: mockIsGuest,
    guestDisplayName: mockGuestDisplayName,
    fetchGuestIdentity: mockFetchGuestIdentity,
  }),
}))

// --- Component mocks ---

vi.mock('@nextcloud/vue/components/NcAppNavigation', () =>
  createComponentMock('NcAppNavigation', {
    template: '<div class="nc-app-navigation"><slot name="list" /><slot name="footer" /></div>',
  }),
)

vi.mock('@nextcloud/vue/components/NcAppNavigationItem', () =>
  createComponentMock('NcAppNavigationItem', {
    template:
      '<div class="nav-item" :data-name="name" :data-active="active"><slot name="icon" /><slot name="actions" /><slot /></div>',
    props: ['name', 'to', 'active', 'open'],
  }),
)

vi.mock('@nextcloud/vue/components/NcAppNavigationSearch', () =>
  createComponentMock('NcAppNavigationSearch', {
    template: '<div class="nav-search" />',
    props: ['modelValue', 'label'],
  }),
)

vi.mock('@nextcloud/vue/components/NcActionButton', () => createNcActionButtonMock())

vi.mock('@/components/UserInfo', () =>
  createComponentMock('UserInfo', {
    template:
      '<div class="user-info" :data-user-id="userId" :data-display-name="displayName" :data-is-guest="isGuest"><slot name="meta" /></div>',
    props: ['userId', 'displayName', 'avatarSize', 'isGuest', 'clickable', 'layout'],
  }),
)

vi.mock('./NavCategoryItem.vue', () =>
  createComponentMock('NavCategoryItem', {
    template: '<div class="nav-category-item" :data-category-id="category?.id" />',
    props: ['category', 'active', 'activeCategoryIds'],
  }),
)

// Icon mocks
vi.mock('@icons/Home.vue', () => createIconMock('HomeIcon'))
vi.mock('@icons/Folder.vue', () => createIconMock('FolderIcon'))
vi.mock('@icons/Magnify.vue', () => createIconMock('MagnifyIcon'))
vi.mock('@icons/Bookmark.vue', () => createIconMock('BookmarkIcon'))
vi.mock('@icons/ChevronDown.vue', () => createIconMock('ChevronDownIcon'))
vi.mock('@icons/ChevronRight.vue', () => createIconMock('ChevronRightIcon'))
vi.mock('@icons/ShieldCheck.vue', () => createIconMock('ShieldCheckIcon'))
vi.mock('@icons/ShieldAccount.vue', () => createIconMock('ShieldAccountIcon'))
vi.mock('@icons/ChartLine.vue', () => createIconMock('ChartLineIcon'))
vi.mock('@icons/AccountMultiple.vue', () => createIconMock('AccountMultipleIcon'))
vi.mock('@icons/CodeBrackets.vue', () => createIconMock('CodeBracketsIcon'))
vi.mock('@icons/ShieldAlert.vue', () => createIconMock('ShieldAlertIcon'))
vi.mock('@icons/Cog.vue', () => createIconMock('CogIcon'))
vi.mock('@icons/AccountCog.vue', () => createIconMock('AccountCogIcon'))
vi.mock('@icons/Login.vue', () => createIconMock('LoginIcon'))

import AppNavigation from './AppNavigation.vue'

describe('AppNavigation', () => {
  let mockRoute: { path: string; params: Record<string, string>; query: Record<string, string> }
  let mockRouter: { push: ReturnType<typeof vi.fn> }

  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()

    mockRoute = { path: '/', params: {}, query: {} }
    mockRouter = { push: vi.fn() }

    mockUserId.value = null
    mockDisplayName.value = 'Test User'
    mockIsGuest.value = false
    mockGuestDisplayName.value = null
    mockFetchCurrentUser.mockResolvedValue(null)
    mockFetchGuestIdentity.mockResolvedValue(undefined)
    mockCategoryHeaders.value = []
    mockCanAccessAdmin.value = false
    mockCanAccessAdminTools.value = false
    mockCanManageUsers.value = false
    mockCanEditRoles.value = false
    mockCanEditCategories.value = false
    mockCanEditBbcodes.value = false
    mockCanAccessModeration.value = false
    mockCurrentThreadCategoryId.value = null
  })

  async function mountAndWait() {
    const wrapper = mount(AppNavigation, {
      global: {
        stubs: { RouterLink: RouterLinkStub },
        mocks: { $route: mockRoute, $router: mockRouter },
      },
    })
    await flushPromises()
    return wrapper
  }

  describe('loading state', () => {
    it('should show loading indicator initially', () => {
      // Don't await — check synchronously before created() resolves
      const wrapper = mount(AppNavigation, {
        global: {
          stubs: { RouterLink: RouterLinkStub },
          mocks: { $route: mockRoute, $router: mockRouter },
        },
      })
      expect(wrapper.find('.nav-loading').exists()).toBe(true)
      expect(wrapper.find('.nav-loading__text').text()).toBe('Loading …')
    })

    it('should hide loading indicator after data is fetched', async () => {
      const wrapper = await mountAndWait()
      expect(wrapper.find('.nav-loading').exists()).toBe(false)
    })

    it('should fetch categories and current user on creation', async () => {
      await mountAndWait()
      expect(mockFetchCategories).toHaveBeenCalled()
      expect(mockFetchCurrentUser).toHaveBeenCalled()
    })
  })

  describe('navigation items', () => {
    it('should render Home navigation item', async () => {
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Home"]').exists()).toBe(true)
    })

    it('should render Search navigation item', async () => {
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Search"]').exists()).toBe(true)
    })

    it('should show Bookmarks item for logged-in users', async () => {
      mockUserId.value = 'user1'
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Bookmarks"]').exists()).toBe(true)
    })

    it('should hide Bookmarks item for guests', async () => {
      mockUserId.value = null
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Bookmarks"]').exists()).toBe(false)
    })

    it('should show Preferences item for logged-in users', async () => {
      mockUserId.value = 'user1'
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Preferences"]').exists()).toBe(true)
    })

    it('should hide Preferences item for guests', async () => {
      mockUserId.value = null
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Preferences"]').exists()).toBe(false)
    })
  })

  describe('category headers', () => {
    it('should render category headers', async () => {
      mockCategoryHeaders.value = [
        { id: 1, name: 'General', categories: [] },
        { id: 2, name: 'Support', categories: [] },
      ]
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="General"]').exists()).toBe(true)
      expect(wrapper.find('[data-name="Support"]').exists()).toBe(true)
    })

    it('should render categories under open headers', async () => {
      mockCategoryHeaders.value = [
        {
          id: 1,
          name: 'General',
          categories: [
            { id: 10, name: 'Discussion', slug: 'discussion' },
            { id: 11, name: 'Announcements', slug: 'announcements' },
          ],
        },
      ]
      const wrapper = await mountAndWait()
      const categoryItems = wrapper.findAll('.nav-category-item')
      expect(categoryItems).toHaveLength(2)
    })

    it('should navigate to first category when clicking a header', async () => {
      mockCategoryHeaders.value = [
        {
          id: 1,
          name: 'General',
          categories: [{ id: 10, name: 'Discussion', slug: 'discussion' }],
        },
      ]
      const wrapper = await mountAndWait()
      const headerItem = wrapper.find('[data-name="General"]')
      await headerItem.trigger('click')
      expect(mockRouter.push).toHaveBeenCalledWith({ path: '/c/discussion' })
    })
  })

  describe('admin section', () => {
    it('should show Management item when user has admin access', async () => {
      mockUserId.value = 'admin1'
      mockCanAccessAdmin.value = true
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Management"]').exists()).toBe(true)
    })

    it('should hide Management item when user lacks admin access', async () => {
      mockUserId.value = 'user1'
      mockCanAccessAdmin.value = false
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Management"]').exists()).toBe(false)
    })

    it('should show Dashboard sub-item when user has admin tools access', async () => {
      mockUserId.value = 'admin1'
      mockCanAccessAdmin.value = true
      mockCanAccessAdminTools.value = true
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Dashboard"]').exists()).toBe(true)
    })

    it('should show Forum settings sub-item when user has admin tools access', async () => {
      mockUserId.value = 'admin1'
      mockCanAccessAdmin.value = true
      mockCanAccessAdminTools.value = true
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Forum settings"]').exists()).toBe(true)
    })

    it('should show Accounts sub-item when user can manage users', async () => {
      mockUserId.value = 'admin1'
      mockCanAccessAdmin.value = true
      mockCanManageUsers.value = true
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Accounts"]').exists()).toBe(true)
    })

    it('should show Roles & Teams sub-item when user can edit roles', async () => {
      mockUserId.value = 'admin1'
      mockCanAccessAdmin.value = true
      mockCanEditRoles.value = true
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Roles & Teams"]').exists()).toBe(true)
    })

    it('should show Categories sub-item when user can edit categories', async () => {
      mockUserId.value = 'admin1'
      mockCanAccessAdmin.value = true
      mockCanEditCategories.value = true
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Categories"]').exists()).toBe(true)
    })

    it('should show BBCodes sub-item when user can edit bbcodes', async () => {
      mockUserId.value = 'admin1'
      mockCanAccessAdmin.value = true
      mockCanEditBbcodes.value = true
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="BBCodes"]').exists()).toBe(true)
    })

    it('should show Moderation sub-item when user can access moderation', async () => {
      mockUserId.value = 'admin1'
      mockCanAccessAdmin.value = true
      mockCanAccessModeration.value = true
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Moderation"]').exists()).toBe(true)
    })

    it('should hide admin sub-items user does not have access to', async () => {
      mockUserId.value = 'mod1'
      mockCanAccessAdmin.value = true
      mockCanAccessModeration.value = true
      // Only moderation, nothing else
      const wrapper = await mountAndWait()
      expect(wrapper.find('[data-name="Moderation"]').exists()).toBe(true)
      expect(wrapper.find('[data-name="Dashboard"]').exists()).toBe(false)
      expect(wrapper.find('[data-name="Forum settings"]').exists()).toBe(false)
      expect(wrapper.find('[data-name="Accounts"]').exists()).toBe(false)
      expect(wrapper.find('[data-name="Roles & Teams"]').exists()).toBe(false)
      expect(wrapper.find('[data-name="Categories"]').exists()).toBe(false)
      expect(wrapper.find('[data-name="BBCodes"]').exists()).toBe(false)
    })
  })

  describe('footer - logged-in user', () => {
    it('should show UserInfo with user data for logged-in users', async () => {
      mockUserId.value = 'user1'
      mockDisplayName.value = 'Alice'
      const wrapper = await mountAndWait()
      const userInfo = wrapper.find('.sidebar-footer .user-info')
      expect(userInfo.exists()).toBe(true)
      expect(userInfo.attributes('data-user-id')).toBe('user1')
      expect(userInfo.attributes('data-display-name')).toBe('Alice')
    })

    it('should not show guest footer for logged-in users', async () => {
      mockUserId.value = 'user1'
      const wrapper = await mountAndWait()
      expect(wrapper.find('.guest-login-link').exists()).toBe(false)
      expect(wrapper.find('.guest-label').exists()).toBe(false)
    })
  })

  describe('footer - guest', () => {
    it('should call fetchGuestIdentity when user is a guest', async () => {
      mockIsGuest.value = true
      await mountAndWait()
      expect(mockFetchGuestIdentity).toHaveBeenCalled()
    })

    it('should not call fetchGuestIdentity when user is logged in', async () => {
      mockUserId.value = 'user1'
      mockIsGuest.value = false
      await mountAndWait()
      expect(mockFetchGuestIdentity).not.toHaveBeenCalled()
    })

    it('should show guest display name when available', async () => {
      mockIsGuest.value = true
      mockGuestDisplayName.value = 'BrightMountain42'
      const wrapper = await mountAndWait()
      const userInfo = wrapper.find('.user-info[data-is-guest="true"]')
      expect(userInfo.exists()).toBe(true)
      expect(userInfo.attributes('data-display-name')).toBe('BrightMountain42')
    })

    it('should show guest label in meta slot', async () => {
      mockIsGuest.value = true
      mockGuestDisplayName.value = 'BrightMountain42'
      const wrapper = await mountAndWait()
      expect(wrapper.find('.guest-label').text()).toBe('(Guest)')
    })

    it('should show login link for guests', async () => {
      mockIsGuest.value = true
      const wrapper = await mountAndWait()
      const loginLink = wrapper.find('.guest-login-link')
      expect(loginLink.exists()).toBe(true)
      expect(loginLink.text()).toContain('Log in')
    })

    it('should set login link href with redirect URL', async () => {
      mockIsGuest.value = true
      const wrapper = await mountAndWait()
      const loginLink = wrapper.find('.guest-login-link')
      expect(loginLink.attributes('href')).toContain('/login')
    })

    it('should show login link even without guest display name', async () => {
      mockIsGuest.value = true
      mockGuestDisplayName.value = null
      const wrapper = await mountAndWait()
      expect(wrapper.find('.guest-login-link').exists()).toBe(true)
      expect(wrapper.find('.user-info[data-is-guest="true"]').exists()).toBe(false)
    })

    it('should render login icon in the login link', async () => {
      mockIsGuest.value = true
      const wrapper = await mountAndWait()
      const loginLink = wrapper.find('.guest-login-link')
      expect(loginLink.find('[data-icon="LoginIcon"]').exists()).toBe(true)
    })
  })

  describe('navigation state persistence', () => {
    it('should save header toggle state to localStorage', async () => {
      mockCategoryHeaders.value = [{ id: 1, name: 'General', categories: [] }]
      const wrapper = await mountAndWait()

      // Toggle the header
      const actionButton = wrapper.find('[data-name="General"]').find('.nc-action-button')
      await actionButton.trigger('click')

      const saved = JSON.parse(localStorage.getItem('forum_navigation_state')!)
      expect(saved.openHeaders).toBeDefined()
      expect(saved.openHeaders[1]).toBe(false)
    })

    it('should restore header toggle state from localStorage', async () => {
      localStorage.setItem(
        'forum_navigation_state',
        JSON.stringify({ isAdminOpen: false, openHeaders: { 1: false } }),
      )
      mockCategoryHeaders.value = [
        {
          id: 1,
          name: 'General',
          categories: [{ id: 10, name: 'Discussion', slug: 'discussion' }],
        },
      ]
      const wrapper = await mountAndWait()
      // Header is closed, so its categories should not render
      expect(wrapper.findAll('.nav-category-item')).toHaveLength(0)
    })

    it('should default headers to open when no saved state', async () => {
      mockCategoryHeaders.value = [
        {
          id: 1,
          name: 'General',
          categories: [{ id: 10, name: 'Discussion', slug: 'discussion' }],
        },
      ]
      const wrapper = await mountAndWait()
      expect(wrapper.findAll('.nav-category-item')).toHaveLength(1)
    })
  })

  describe('route-based active state', () => {
    it('should clear thread when not on a thread page', async () => {
      mockRoute.path = '/bookmarks'
      await mountAndWait()
      expect(mockClearThread).toHaveBeenCalled()
    })

    it('should fetch thread by slug when on a thread slug page', async () => {
      mockRoute.path = '/t/my-thread'
      mockRoute.params = { slug: 'my-thread' }
      await mountAndWait()
      expect(mockFetchThread).toHaveBeenCalledWith('my-thread', true, false)
    })

    it('should fetch thread by id when on a thread id page', async () => {
      mockRoute.path = '/thread/42'
      mockRoute.params = { id: '42' }
      await mountAndWait()
      expect(mockFetchThread).toHaveBeenCalledWith('42', false, false)
    })
  })
})
