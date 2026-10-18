import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { computed, ref } from 'vue'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockCategory, createMockThread } from '@/test-mocks'
import type { Category } from '@/types'

// Uses global mock for @/axios from test-setup.ts

// Mock useCurrentUser composable
vi.mock('@/composables/useCurrentUser', () => ({
  useCurrentUser: () => ({
    userId: computed(() => 'testuser'),
    displayName: computed(() => 'Test User'),
  }),
}))

// Mock useCategories composable
const mockMarkCategoryAsRead = vi.fn()
const mockUnreadCounts = ref<Record<number, number>>({})
vi.mock('@/composables/useCategories', () => ({
  useCategories: () => ({
    markCategoryAsRead: mockMarkCategoryAsRead,
    findCategoryInTree: vi.fn().mockReturnValue(null),
    getUnreadCount: (category: Category) => mockUnreadCounts.value[category.id] ?? 0,
  }),
}))

const mockShowUnreadCounts = ref(true)
vi.mock('@/composables/useUserPreferences', () => ({
  useUserPreferences: () => ({
    showUnreadCounts: mockShowUnreadCounts,
  }),
}))

// Mock usePermissions composable
const mockCheckCategoryPermission = vi.fn()
vi.mock('@/composables/usePermissions', () => ({
  usePermissions: () => ({
    checkCategoryPermission: mockCheckCategoryPermission,
  }),
}))

// Mock icons
vi.mock('@icons/ArrowLeft.vue', () => createIconMock('ArrowLeftIcon'))
vi.mock('@icons/Refresh.vue', () => createIconMock('RefreshIcon'))
vi.mock('@icons/MessagePlus.vue', () => createIconMock('MessagePlusIcon'))
vi.mock('@icons/CheckAll.vue', () => createIconMock('CheckAllIcon'))

// Mock components
vi.mock('@/components/PageWrapper', () =>
  createComponentMock('PageWrapper', {
    template: '<div class="page-wrapper-mock"><slot name="toolbar" /><slot /></div>',
  }),
)

vi.mock('@/components/AppToolbar', () =>
  createComponentMock('AppToolbar', {
    template: '<div class="app-toolbar-mock"><slot name="left" /><slot name="right" /></div>',
  }),
)

vi.mock('@/components/PageHeader', () =>
  createComponentMock('PageHeader', {
    template: '<div class="page-header-mock"><span class="title">{{ title }}</span></div>',
    props: ['title', 'subtitle'],
  }),
)

vi.mock('@/components/ThreadCard', () =>
  createComponentMock('ThreadCard', {
    template: '<div class="thread-card-mock">{{ thread.title }}</div>',
    props: ['thread', 'isUnread', 'unreadReplyCount'],
    emits: ['click'],
  }),
)

vi.mock('@/components/Pagination', () =>
  createComponentMock('Pagination', {
    template: '<div class="pagination-mock" />',
    props: ['currentPage', 'maxPages'],
    emits: ['update:current-page'],
  }),
)

vi.mock('@/views/CategoryNotFound.vue', () =>
  createComponentMock('CategoryNotFound', {
    template: '<div class="category-not-found-mock" />',
  }),
)

vi.mock('@/components/CategoryCard', () =>
  createComponentMock('CategoryCard', {
    template: '<div class="category-card-mock">{{ category.name }}</div>',
    props: ['category', 'children', 'hideChildren', 'unreadCount', 'showUnreadCount'],
    emits: ['click'],
  }),
)

import CategoryView from '../CategoryView.vue'
import { ocs } from '@/axios'

const mockOcsGet = vi.mocked(ocs.get)
const mockOcsPost = vi.mocked(ocs.post)

describe('CategoryView', () => {
  const mockCategory = createMockCategory({ id: 5, slug: 'general', name: 'General' })

  const mockRouter = {
    push: vi.fn(),
  }

  const mockRoute = {
    params: { slug: 'general' },
  }

  const createThreadsResponse = (threads: ReturnType<typeof createMockThread>[] = []) => ({
    data: {
      threads,
      pagination: {
        page: 1,
        perPage: 20,
        total: threads.length,
        totalPages: 1,
      },
    },
  })

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const mockGetResponse = (data: Record<string, unknown>): Promise<any> => Promise.resolve(data)

  const setupGetMock = (threads: ReturnType<typeof createMockThread>[] = [createMockThread()]) => {
    mockOcsGet.mockImplementation((url: string) => {
      if (url.includes('/categories/slug/')) {
        return mockGetResponse({ data: mockCategory })
      }
      if (url.includes('/threads')) {
        return mockGetResponse(createThreadsResponse(threads))
      }
      return mockGetResponse({ data: null })
    })
  }

  beforeEach(() => {
    vi.clearAllMocks()
    mockUnreadCounts.value = {}
    mockShowUnreadCounts.value = true
    mockMarkCategoryAsRead.mockResolvedValue(undefined)
    mockCheckCategoryPermission.mockResolvedValue(true)
    setupGetMock()
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    mockOcsPost.mockResolvedValue({ data: {} } as any)
  })

  const createWrapper = () => {
    return mount(CategoryView, {
      global: {
        mocks: {
          $router: mockRouter,
          $route: mockRoute,
        },
      },
    })
  }

  describe('canPost permission', () => {
    it('shows New Thread button when canPost is true', async () => {
      mockCheckCategoryPermission.mockResolvedValue(true)
      const wrapper = createWrapper()
      await flushPromises()

      const buttons = wrapper.findAll('button')
      expect(buttons.some((b) => b.text().includes('New thread'))).toBe(true)
    })

    it('hides New Thread button when canPost is false', async () => {
      mockCheckCategoryPermission.mockResolvedValue(false)
      const wrapper = createWrapper()
      await flushPromises()

      const buttons = wrapper.findAll('button')
      expect(buttons.some((b) => b.text().includes('New thread'))).toBe(false)
    })

    it('hides empty state New Thread button when canPost is false', async () => {
      mockCheckCategoryPermission.mockResolvedValue(false)
      setupGetMock([])

      const wrapper = createWrapper()
      await flushPromises()

      // Empty state should show but without the New Thread button
      expect(wrapper.text()).toContain('No threads yet')
      const buttons = wrapper.findAll('button')
      expect(buttons.some((b) => b.text().includes('New thread'))).toBe(false)
    })

    it('shows empty state New Thread button when canPost is true', async () => {
      mockCheckCategoryPermission.mockResolvedValue(true)
      setupGetMock([])

      const wrapper = createWrapper()
      await flushPromises()

      const buttons = wrapper.findAll('button')
      expect(buttons.some((b) => b.text().includes('New thread'))).toBe(true)
    })

    it('checks permission with correct category ID and permission name', async () => {
      createWrapper()
      await flushPromises()

      expect(mockCheckCategoryPermission).toHaveBeenCalledWith(5, 'canPost')
    })
  })

  describe('read state', () => {
    const findMarkAsReadButton = (wrapper: ReturnType<typeof createWrapper>) =>
      wrapper
        .findAllComponents({ name: 'NcButton' })
        .find((b) => b.props('ariaLabel') === 'Mark as read')

    it('does not mark the category as read when visiting it', async () => {
      createWrapper()
      await flushPromises()

      expect(mockMarkCategoryAsRead).not.toHaveBeenCalled()
      expect(mockOcsPost).not.toHaveBeenCalled()
    })

    it('passes the read state of each thread to its card', async () => {
      setupGetMock([
        createMockThread({ id: 1, isUnread: true, unreadReplyCount: 4 }),
        createMockThread({ id: 2, isUnread: false, unreadReplyCount: 0 }),
      ])
      const wrapper = createWrapper()
      await flushPromises()

      const cards = wrapper.findAllComponents({ name: 'ThreadCard' })
      expect(cards[0]!.props('isUnread')).toBe(true)
      expect(cards[0]!.props('unreadReplyCount')).toBe(4)
      expect(cards[1]!.props('isUnread')).toBe(false)
    })

    it('hides unread reply counts when disabled in preferences', async () => {
      mockShowUnreadCounts.value = false
      setupGetMock([createMockThread({ id: 1, isUnread: true, unreadReplyCount: 4 })])
      const wrapper = createWrapper()
      await flushPromises()

      const card = wrapper.findComponent({ name: 'ThreadCard' })
      expect(card.props('isUnread')).toBe(true)
      expect(card.props('unreadReplyCount')).toBeNull()
    })

    it('disables mark as read when nothing is unread', async () => {
      const wrapper = createWrapper()
      await flushPromises()

      expect(findMarkAsReadButton(wrapper)!.props('disabled')).toBe(true)
    })

    it('marks the category as read and clears the listed threads', async () => {
      mockUnreadCounts.value = { 5: 1 }
      setupGetMock([createMockThread({ id: 1, isUnread: true, unreadReplyCount: 4 })])
      const wrapper = createWrapper()
      await flushPromises()

      await findMarkAsReadButton(wrapper)!.trigger('click')
      await flushPromises()

      expect(mockMarkCategoryAsRead).toHaveBeenCalledWith(expect.objectContaining({ id: 5 }))
      const card = wrapper.findComponent({ name: 'ThreadCard' })
      expect(card.props('isUnread')).toBe(false)
      expect(card.props('unreadReplyCount')).toBe(0)
    })

    it('keeps the threads unread when marking as read fails', async () => {
      mockUnreadCounts.value = { 5: 1 }
      mockMarkCategoryAsRead.mockRejectedValue(new Error('Network error'))
      setupGetMock([createMockThread({ id: 1, isUnread: true, unreadReplyCount: 4 })])
      const wrapper = createWrapper()
      await flushPromises()

      await findMarkAsReadButton(wrapper)!.trigger('click')
      await flushPromises()

      expect(wrapper.findComponent({ name: 'ThreadCard' }).props('isUnread')).toBe(true)
    })
  })
})
