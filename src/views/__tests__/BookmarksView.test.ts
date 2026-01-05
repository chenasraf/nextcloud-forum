import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { computed } from 'vue'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockThread } from '@/test-mocks'

// Mock axios
vi.mock('@/axios', () => ({
  ocs: {
    get: vi.fn(),
  },
}))

// Mock useCurrentUser composable
vi.mock('@/composables/useCurrentUser', () => ({
  useCurrentUser: () => ({
    userId: computed(() => 'testuser'),
    displayName: computed(() => 'Test User'),
  }),
}))

// Mock icons
vi.mock('@icons/ArrowLeft.vue', () => createIconMock('ArrowLeftIcon'))
vi.mock('@icons/Refresh.vue', () => createIconMock('RefreshIcon'))
vi.mock('@icons/Bookmark.vue', () => createIconMock('BookmarkIcon'))

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
    template:
      '<div class="page-header-mock"><span class="title">{{ title }}</span><span class="subtitle">{{ subtitle }}</span></div>',
    props: ['title', 'subtitle'],
  }),
)

vi.mock('@/components/ThreadCard', () =>
  createComponentMock('ThreadCard', {
    template: '<div class="thread-card-mock" @click="$emit(\'click\')">{{ thread.title }}</div>',
    props: ['thread', 'isUnread'],
    emits: ['click'],
  }),
)

vi.mock('@/components/Pagination', () =>
  createComponentMock('Pagination', {
    template:
      '<div class="pagination-mock" @click="$emit(\'update:current-page\', 2)">Page {{ currentPage }} of {{ maxPages }}</div>',
    props: ['currentPage', 'maxPages'],
    emits: ['update:current-page'],
  }),
)

import BookmarksView from '../BookmarksView.vue'
import { ocs } from '@/axios'

const mockOcsGet = vi.mocked(ocs.get)

describe('BookmarksView', () => {
  const mockRouter = {
    push: vi.fn(),
  }

  const createBookmarksResponse = (
    threads: ReturnType<typeof createMockThread>[] = [],
    options = {},
  ) => ({
    data: {
      threads,
      pagination: {
        page: 1,
        perPage: 20,
        total: threads.length,
        totalPages: Math.ceil(threads.length / 20) || 1,
        ...options,
      },
      readMarkers: {},
    },
  })

  beforeEach(() => {
    vi.clearAllMocks()
    mockOcsGet.mockResolvedValue(createBookmarksResponse())
  })

  const createWrapper = () => {
    return mount(BookmarksView, {
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    })
  }

  describe('rendering', () => {
    it('renders the bookmarks view', async () => {
      const wrapper = createWrapper()
      await flushPromises()
      expect(wrapper.find('.bookmarks-view').exists()).toBe(true)
    })

    it('renders page wrapper', async () => {
      const wrapper = createWrapper()
      await flushPromises()
      expect(wrapper.find('.page-wrapper-mock').exists()).toBe(true)
    })

    it('renders app toolbar', async () => {
      const wrapper = createWrapper()
      await flushPromises()
      expect(wrapper.find('.app-toolbar-mock').exists()).toBe(true)
    })

    it('renders back button', async () => {
      const wrapper = createWrapper()
      await flushPromises()
      expect(wrapper.text()).toContain('Back to home')
    })

    it('renders refresh button', async () => {
      const wrapper = createWrapper()
      await flushPromises()
      // Refresh button is in the toolbar right slot
      const buttons = wrapper.findAll('button')
      expect(buttons.length).toBeGreaterThanOrEqual(2) // Back button + Refresh button
    })

    it('renders page header with title', async () => {
      mockOcsGet.mockResolvedValue(createBookmarksResponse([createMockThread()]))
      const wrapper = createWrapper()
      await flushPromises()
      expect(wrapper.find('.title').text()).toBe('Bookmarks')
    })

    it('renders page header with subtitle', async () => {
      mockOcsGet.mockResolvedValue(createBookmarksResponse([createMockThread()]))
      const wrapper = createWrapper()
      await flushPromises()
      expect(wrapper.find('.subtitle').text()).toBe('Your bookmarked threads')
    })
  })

  describe('loading state', () => {
    it('shows loading state initially', () => {
      // Don't resolve the promise yet
      let resolvePromise: (value: unknown) => void
      mockOcsGet.mockImplementation(
        () =>
          new Promise((resolve) => {
            resolvePromise = resolve
          }),
      )

      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('Loading')

      // Resolve to clean up
      resolvePromise!(createBookmarksResponse())
    })

    it('hides loading state after fetch', async () => {
      const wrapper = createWrapper()
      await flushPromises()
      expect(wrapper.text()).not.toContain('Loading')
    })
  })

  describe('empty state', () => {
    it('shows empty state when no bookmarks', async () => {
      mockOcsGet.mockResolvedValue(createBookmarksResponse([]))
      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.text()).toContain('No bookmarks yet')
      expect(wrapper.text()).toContain('Bookmark threads to quickly find them later.')
    })
  })

  describe('bookmarks list', () => {
    it('displays bookmarked threads', async () => {
      const threads = [
        createMockThread({ id: 1, title: 'First Thread', slug: 'first-thread' }),
        createMockThread({ id: 2, title: 'Second Thread', slug: 'second-thread' }),
      ]
      mockOcsGet.mockResolvedValue(createBookmarksResponse(threads))

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.findAll('.thread-card-mock').length).toBe(2)
      expect(wrapper.text()).toContain('First Thread')
      expect(wrapper.text()).toContain('Second Thread')
    })

    it('marks thread as unread when no read marker exists', async () => {
      const thread = createMockThread({ id: 1, lastPostId: 5 })
      mockOcsGet.mockResolvedValue({
        data: {
          threads: [thread],
          pagination: { page: 1, perPage: 20, total: 1, totalPages: 1 },
          readMarkers: {}, // No marker for this thread
        },
      })

      const wrapper = createWrapper()
      await flushPromises()

      const threadCard = wrapper.findComponent({ name: 'ThreadCard' })
      expect(threadCard.props('isUnread')).toBe(true)
    })

    it('marks thread as read when read marker is up to date', async () => {
      const thread = createMockThread({ id: 1, lastPostId: 5 })
      mockOcsGet.mockResolvedValue({
        data: {
          threads: [thread],
          pagination: { page: 1, perPage: 20, total: 1, totalPages: 1 },
          readMarkers: {
            1: { lastReadPostId: 5, readAt: Date.now() },
          },
        },
      })

      const wrapper = createWrapper()
      await flushPromises()

      const threadCard = wrapper.findComponent({ name: 'ThreadCard' })
      expect(threadCard.props('isUnread')).toBe(false)
    })

    it('marks thread as unread when new posts exist', async () => {
      const thread = createMockThread({ id: 1, lastPostId: 10 })
      mockOcsGet.mockResolvedValue({
        data: {
          threads: [thread],
          pagination: { page: 1, perPage: 20, total: 1, totalPages: 1 },
          readMarkers: {
            1: { lastReadPostId: 5, readAt: Date.now() }, // Behind lastPostId
          },
        },
      })

      const wrapper = createWrapper()
      await flushPromises()

      const threadCard = wrapper.findComponent({ name: 'ThreadCard' })
      expect(threadCard.props('isUnread')).toBe(true)
    })
  })

  describe('pagination', () => {
    it('does not show pagination when only one page', async () => {
      mockOcsGet.mockResolvedValue(createBookmarksResponse([createMockThread()]))
      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.find('.pagination-mock').exists()).toBe(false)
    })

    it('shows pagination when multiple pages', async () => {
      const threads = [createMockThread()]
      mockOcsGet.mockResolvedValue({
        data: {
          threads,
          pagination: { page: 1, perPage: 20, total: 50, totalPages: 3 },
          readMarkers: {},
        },
      })

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.find('.pagination-mock').exists()).toBe(true)
    })

    it('fetches new page when pagination changes', async () => {
      const threads = [createMockThread()]
      mockOcsGet.mockResolvedValue({
        data: {
          threads,
          pagination: { page: 1, perPage: 20, total: 50, totalPages: 3 },
          readMarkers: {},
        },
      })

      const wrapper = createWrapper()
      await flushPromises()

      const callCountAfterInit = mockOcsGet.mock.calls.length

      // Trigger page change
      const pagination = wrapper.find('.pagination-mock')
      await pagination.trigger('click')
      await flushPromises()

      expect(mockOcsGet.mock.calls.length).toBeGreaterThan(callCountAfterInit)
    })
  })

  describe('navigation', () => {
    it('navigates to thread on click', async () => {
      const thread = createMockThread({ slug: 'test-thread' })
      mockOcsGet.mockResolvedValue(createBookmarksResponse([thread]))

      const wrapper = createWrapper()
      await flushPromises()

      const threadCard = wrapper.find('.thread-card-mock')
      await threadCard.trigger('click')

      expect(mockRouter.push).toHaveBeenCalledWith('/t/test-thread')
    })

    it('navigates to home on back button click', async () => {
      const wrapper = createWrapper()
      await flushPromises()

      const backButton = wrapper.findAll('button').find((b) => b.text().includes('Back to home'))!
      await backButton.trigger('click')

      expect(mockRouter.push).toHaveBeenCalledWith('/')
    })
  })

  describe('refresh', () => {
    it('refreshes bookmarks on refresh button click', async () => {
      const wrapper = createWrapper()
      await flushPromises()

      const callCountAfterInit = mockOcsGet.mock.calls.length

      // The refresh button is the second button (after back button)
      const buttons = wrapper.findAll('button')
      const refreshButton = buttons[1]!
      await refreshButton.trigger('click')
      await flushPromises()

      expect(mockOcsGet.mock.calls.length).toBeGreaterThan(callCountAfterInit)
    })
  })

  describe('error handling', () => {
    beforeEach(() => {
      // Suppress console.error for error handling tests
      vi.spyOn(console, 'error').mockImplementation(() => {})
    })

    it('shows error state on fetch failure', async () => {
      mockOcsGet.mockRejectedValue(new Error('Network error'))

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.text()).toContain('Error loading bookmarks')
    })

    it('shows retry button on error', async () => {
      mockOcsGet.mockRejectedValue(new Error('Failed'))

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.text()).toContain('Retry')
    })

    it('retries fetch on retry button click', async () => {
      mockOcsGet.mockRejectedValueOnce(new Error('Failed'))
      mockOcsGet.mockResolvedValueOnce(createBookmarksResponse())

      const wrapper = createWrapper()
      await flushPromises()

      const callCountAfterError = mockOcsGet.mock.calls.length

      const retryButton = wrapper.findAll('button').find((b) => b.text().includes('Retry'))!
      await retryButton.trigger('click')
      await flushPromises()

      expect(mockOcsGet.mock.calls.length).toBeGreaterThan(callCountAfterError)
    })
  })

  describe('API calls', () => {
    it('fetches bookmarks on mount', async () => {
      createWrapper()
      await flushPromises()

      expect(mockOcsGet).toHaveBeenCalledWith('/bookmarks', {
        params: {
          page: 1,
          perPage: 20,
        },
      })
    })

    it('fetches with correct page parameter', async () => {
      mockOcsGet.mockResolvedValue({
        data: {
          threads: [createMockThread()],
          pagination: { page: 1, perPage: 20, total: 50, totalPages: 3 },
          readMarkers: {},
        },
      })

      const wrapper = createWrapper()
      await flushPromises()

      // Trigger page change to page 2
      const pagination = wrapper.find('.pagination-mock')
      await pagination.trigger('click')
      await flushPromises()

      expect(mockOcsGet).toHaveBeenCalledWith('/bookmarks', {
        params: {
          page: 2,
          perPage: 20,
        },
      })
    })
  })
})
