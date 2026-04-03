import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockRole } from '@/test-mocks'

// Uses global mock for @/axios from test-setup.ts

// Mock icons
vi.mock('@icons/AccountMultiple.vue', () => createIconMock('AccountMultipleIcon'))
vi.mock('@icons/AccountPlus.vue', () => createIconMock('AccountPlusIcon'))
vi.mock('@icons/Forum.vue', () => createIconMock('ForumIcon'))
vi.mock('@icons/MessageText.vue', () => createIconMock('MessageTextIcon'))
vi.mock('@icons/Folder.vue', () => createIconMock('FolderIcon'))

// Mock components
vi.mock('@/components/PageWrapper', () =>
  createComponentMock('PageWrapper', {
    template: '<div class="page-wrapper-mock"><slot /></div>',
  }),
)

vi.mock('@/components/PageHeader', () =>
  createComponentMock('PageHeader', {
    template: '<div class="page-header-mock">{{ title }}</div>',
    props: ['title', 'subtitle'],
  }),
)

vi.mock('@/components/UserInfo', () =>
  createComponentMock('UserInfo', {
    template:
      '<div class="user-info-mock" :data-user-id="userId" :data-display-name="displayName" :data-is-guest="isGuest"><slot name="meta" /></div>',
    props: ['userId', 'displayName', 'avatarSize', 'isGuest', 'roles', 'clickable', 'showRoles'],
  }),
)

import AdminDashboard from '../AdminDashboard.vue'
import { ocs } from '@/axios'

const mockOcsGet = vi.mocked(ocs.get)

function createDashboardStats(overrides: Record<string, unknown> = {}) {
  return {
    data: {
      totals: { users: 10, threads: 20, posts: 50, categories: 5 },
      recent: { users: 2, threads: 3, posts: 8 },
      topContributorsAllTime: [],
      topContributorsRecent: [],
      ...overrides,
    },
  }
}

describe('AdminDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockOcsGet.mockResolvedValue(createDashboardStats())
  })

  const createWrapper = () => {
    return mount(AdminDashboard)
  }

  describe('rendering', () => {
    it('renders the dashboard', async () => {
      const wrapper = createWrapper()
      await flushPromises()
      expect(wrapper.find('.admin-dashboard').exists()).toBe(true)
    })

    it('shows loading state initially', () => {
      let _resolve: (v: unknown) => void
      mockOcsGet.mockImplementation(() => new Promise((r) => (_resolve = r)))
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('Loading')
    })

    it('shows stats after loading', async () => {
      const wrapper = createWrapper()
      await flushPromises()
      expect(wrapper.find('.dashboard-content').exists()).toBe(true)
    })

    it('shows total statistics', async () => {
      const wrapper = createWrapper()
      await flushPromises()
      expect(wrapper.text()).toContain('10') // users
      expect(wrapper.text()).toContain('20') // threads
      expect(wrapper.text()).toContain('50') // posts
      expect(wrapper.text()).toContain('5') // categories
    })
  })

  describe('error handling', () => {
    beforeEach(() => {
      vi.spyOn(console, 'error').mockImplementation(() => {})
    })

    it('shows error state on fetch failure', async () => {
      mockOcsGet.mockRejectedValue(new Error('Network error'))
      const wrapper = createWrapper()
      await flushPromises()
      expect(wrapper.text()).toContain('Error loading dashboard')
    })
  })

  describe('top contributors', () => {
    it('shows no contributors message when lists are empty', async () => {
      const wrapper = createWrapper()
      await flushPromises()
      expect(wrapper.text()).toContain('No contributors yet')
    })

    it('renders regular user contributors with display name', async () => {
      mockOcsGet.mockResolvedValue(
        createDashboardStats({
          topContributorsAllTime: [
            {
              userId: 'alice',
              displayName: 'Alice Smith',
              isGuest: false,
              roles: [],
              postCount: 10,
              threadCount: 3,
            },
          ],
        }),
      )

      const wrapper = createWrapper()
      await flushPromises()

      const userInfos = wrapper.findAll('.user-info-mock')
      const aliceInfo = userInfos.find((el) => el.attributes('data-display-name') === 'Alice Smith')
      expect(aliceInfo).toBeDefined()
      expect(aliceInfo!.attributes('data-is-guest')).toBe('false')
    })

    it('renders guest contributors with isGuest flag', async () => {
      const guestRole = createMockRole({ id: 4, name: 'Guest', roleType: 'guest' })
      mockOcsGet.mockResolvedValue(
        createDashboardStats({
          topContributorsRecent: [
            {
              userId: 'guest:abc123',
              displayName: 'BrightMountain42',
              isGuest: true,
              roles: [guestRole],
              postCount: 5,
              threadCount: 1,
            },
          ],
        }),
      )

      const wrapper = createWrapper()
      await flushPromises()

      const userInfos = wrapper.findAll('.user-info-mock')
      const guestInfo = userInfos.find(
        (el) => el.attributes('data-display-name') === 'BrightMountain42',
      )
      expect(guestInfo).toBeDefined()
      expect(guestInfo!.attributes('data-is-guest')).toBe('true')
    })

    it('passes roles to UserInfo for guest contributors', async () => {
      const guestRole = createMockRole({ id: 4, name: 'Guest', roleType: 'guest' })
      mockOcsGet.mockResolvedValue(
        createDashboardStats({
          topContributorsRecent: [
            {
              userId: 'guest:abc123',
              displayName: 'SwiftRiver99',
              isGuest: true,
              roles: [guestRole],
              postCount: 3,
              threadCount: 0,
            },
          ],
        }),
      )

      const wrapper = createWrapper()
      await flushPromises()

      const userInfo = wrapper.findComponent({ name: 'UserInfo' })
      expect(userInfo.props('roles')).toEqual([guestRole])
      expect(userInfo.props('isGuest')).toBe(true)
    })

    it('passes roles to UserInfo for regular contributors', async () => {
      const adminRole = createMockRole({ id: 1, name: 'Admin', roleType: 'admin' })
      mockOcsGet.mockResolvedValue(
        createDashboardStats({
          topContributorsAllTime: [
            {
              userId: 'admin',
              displayName: 'Admin User',
              isGuest: false,
              roles: [adminRole],
              postCount: 20,
              threadCount: 5,
            },
          ],
        }),
      )

      const wrapper = createWrapper()
      await flushPromises()

      const userInfo = wrapper.findComponent({ name: 'UserInfo' })
      expect(userInfo.props('roles')).toEqual([adminRole])
      expect(userInfo.props('isGuest')).toBe(false)
    })

    it('shows contributor stats (threads and posts counts)', async () => {
      mockOcsGet.mockResolvedValue(
        createDashboardStats({
          topContributorsAllTime: [
            {
              userId: 'alice',
              displayName: 'Alice',
              isGuest: false,
              roles: [],
              postCount: 10,
              threadCount: 3,
            },
          ],
        }),
      )

      const wrapper = createWrapper()
      await flushPromises()

      const contributorStats = wrapper.find('.contributor-stats')
      expect(contributorStats.exists()).toBe(true)
    })

    it('shows rank numbers for contributors', async () => {
      mockOcsGet.mockResolvedValue(
        createDashboardStats({
          topContributorsRecent: [
            {
              userId: 'alice',
              displayName: 'Alice',
              isGuest: false,
              roles: [],
              postCount: 10,
              threadCount: 3,
            },
            {
              userId: 'bob',
              displayName: 'Bob',
              isGuest: false,
              roles: [],
              postCount: 5,
              threadCount: 1,
            },
          ],
        }),
      )

      const wrapper = createWrapper()
      await flushPromises()

      const ranks = wrapper.findAll('.contributor-rank')
      expect(ranks.length).toBeGreaterThanOrEqual(2)
      expect(ranks[0].text()).toBe('1')
      expect(ranks[1].text()).toBe('2')
    })
  })
})
