import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { computed } from 'vue'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockCategory, createMockThread } from '@/test-mocks'

// Mock axios
vi.mock('@/axios', () => ({
  ocs: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

// Mock useCurrentUser composable
vi.mock('@/composables/useCurrentUser', () => ({
  useCurrentUser: () => ({
    userId: computed(() => 'testuser'),
    displayName: computed(() => 'Test User'),
  }),
}))

// Mock useCategories composable
vi.mock('@/composables/useCategories', () => ({
  useCategories: () => ({
    markCategoryAsRead: vi.fn(),
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
    props: ['thread', 'isUnread'],
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
      if (url.includes('/paginated')) {
        return mockGetResponse(createThreadsResponse(threads))
      }
      if (url.includes('/read-markers')) {
        return mockGetResponse({ data: {} })
      }
      return mockGetResponse({ data: null })
    })
  }

  beforeEach(() => {
    vi.clearAllMocks()
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
})
