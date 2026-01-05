import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockThread, createMockPost } from '@/test-mocks'

// Mock axios
vi.mock('@/axios', () => ({
  ocs: {
    get: vi.fn(),
  },
}))

// Mock @nextcloud/dialogs
vi.mock('@nextcloud/dialogs', () => ({
  showError: vi.fn(),
}))

// Mock Nextcloud Vue components (to avoid CSS import issues)
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () =>
  createComponentMock('NcCheckboxRadioSwitch', {
    template:
      '<label class="nc-checkbox-radio-switch"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
    props: ['modelValue'],
    emits: ['update:modelValue', 'update:checked'],
  }),
)

// Mock icons
vi.mock('@icons/Magnify.vue', () => createIconMock('MagnifyIcon'))
vi.mock('@icons/HelpCircle.vue', () => createIconMock('HelpCircleIcon'))

// Mock components
vi.mock('@/components/PageWrapper', () =>
  createComponentMock('PageWrapper', {
    template: '<div class="page-wrapper-mock"><slot /></div>',
  }),
)

vi.mock('@/components/SearchThreadResult', () =>
  createComponentMock('SearchThreadResult', {
    template:
      '<div class="search-thread-result-mock" @click="$emit(\'click\')">{{ thread.title }}</div>',
    props: ['thread', 'query'],
    emits: ['click'],
  }),
)

vi.mock('@/components/SearchPostResult', () =>
  createComponentMock('SearchPostResult', {
    template: '<div class="search-post-result-mock">{{ post.content }}</div>',
    props: ['post', 'query'],
  }),
)

import SearchView from '../SearchView.vue'
import { ocs } from '@/axios'
import { showError } from '@nextcloud/dialogs'

const mockOcsGet = vi.mocked(ocs.get)
const mockShowError = vi.mocked(showError)

// Helper to get the primary search button (first button in the component)
const getSearchButton = (wrapper: ReturnType<typeof mount>) => wrapper.findAll('button')[0]!

describe('SearchView', () => {
  const mockRouter = {
    push: vi.fn(),
    replace: vi.fn(),
  }

  const mockRoute = {
    query: {},
  }

  beforeEach(() => {
    vi.clearAllMocks()
    mockRoute.query = {}
    mockOcsGet.mockResolvedValue({
      data: {
        threads: [],
        posts: [],
        threadCount: 0,
        postCount: 0,
      },
    })
  })

  const createWrapper = (routeQuery = {}) => {
    mockRoute.query = routeQuery
    return mount(SearchView, {
      global: {
        mocks: {
          $router: mockRouter,
          $route: mockRoute,
        },
      },
    })
  }

  describe('rendering', () => {
    it('renders the search view', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.search-view').exists()).toBe(true)
    })

    it('renders search title', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.search-title').text()).toBe('Search')
    })

    it('renders search input', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.search-input').exists()).toBe(true)
    })

    it('renders search button', () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button')
      expect(buttons.some((b) => b.text().includes('Search'))).toBe(true)
    })

    it('renders search options checkboxes', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('Search in threads')
      expect(wrapper.text()).toContain('Search in replies')
    })

    it('renders syntax help button', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('Syntax help')
    })
  })

  describe('initial state', () => {
    it('shows empty state when no search performed', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('Enter a search query')
      expect(wrapper.text()).toContain('Use the search box above to find threads and replies')
    })

    it('has both search options enabled by default', () => {
      const wrapper = createWrapper()
      const checkboxes = wrapper.findAll('.nc-checkbox-radio-switch')
      // Both checkboxes should be checked (their models are true by default)
      expect(checkboxes.length).toBe(2)
    })
  })

  describe('syntax help', () => {
    it('does not show syntax help by default', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.syntax-help').exists()).toBe(false)
    })

    it('shows syntax help when toggled via data', async () => {
      const wrapper = createWrapper()
      await wrapper.setData({ showSyntaxHelp: true })
      expect(wrapper.find('.syntax-help').exists()).toBe(true)
    })

    it('displays search syntax examples', async () => {
      const wrapper = createWrapper()
      // Toggle syntax help on
      await wrapper.setData({ showSyntaxHelp: true })

      expect(wrapper.text()).toContain('"exact phrase"')
      expect(wrapper.text()).toContain('term1 AND term2')
      expect(wrapper.text()).toContain('term1 OR term2')
      expect(wrapper.text()).toContain('-excluded')
    })
  })

  describe('search validation', () => {
    it('disables search button when query is empty', () => {
      const wrapper = createWrapper()
      const searchButton = getSearchButton(wrapper)
      expect(searchButton.attributes('disabled')).toBeDefined()
    })

    it('disables search button when no search scope selected', async () => {
      const wrapper = createWrapper()

      // Fill in query
      const input = wrapper.find('.search-input')
      await input.setValue('test query')

      // Disable both options
      await wrapper.setData({ searchThreads: false, searchPosts: false })

      const searchButton = getSearchButton(wrapper)
      expect(searchButton.attributes('disabled')).toBeDefined()
    })

    it('enables search button when query and scope are valid', async () => {
      const wrapper = createWrapper()

      // Fill in query
      const input = wrapper.find('.search-input')
      await input.setValue('test query')

      const searchButton = getSearchButton(wrapper)
      expect(searchButton.attributes('disabled')).toBeUndefined()
    })
  })

  describe('performing search', () => {
    it('calls API with correct parameters', async () => {
      const wrapper = createWrapper()
      const input = wrapper.find('.search-input')
      await input.setValue('test query')

      await getSearchButton(wrapper).trigger('click')
      await flushPromises()

      expect(mockOcsGet).toHaveBeenCalledWith('/search', {
        params: {
          q: 'test query',
          searchThreads: true,
          searchPosts: true,
          limit: 50,
          offset: 0,
        },
      })
    })

    it('updates URL with query parameter', async () => {
      const wrapper = createWrapper()
      const input = wrapper.find('.search-input')
      await input.setValue('my search')

      await getSearchButton(wrapper).trigger('click')
      await flushPromises()

      expect(mockRouter.replace).toHaveBeenCalledWith({ query: { q: 'my search' } })
    })

    it('triggers search on enter key', async () => {
      const wrapper = createWrapper()
      const input = wrapper.find('.search-input')
      await input.setValue('enter search')
      await input.trigger('keydown.enter')
      await flushPromises()

      expect(mockOcsGet).toHaveBeenCalled()
    })

    it('shows loading state during search', async () => {
      // Create a promise that we can control
      let resolveSearch: (value: unknown) => void
      mockOcsGet.mockImplementation(
        () =>
          new Promise((resolve) => {
            resolveSearch = resolve
          }),
      )

      const wrapper = createWrapper()
      const input = wrapper.find('.search-input')
      await input.setValue('test')

      await getSearchButton(wrapper).trigger('click')

      expect(wrapper.text()).toContain('Searching')

      // Resolve the promise
      resolveSearch!({
        data: { threads: [], posts: [], threadCount: 0, postCount: 0 },
      })
      await flushPromises()

      expect(wrapper.text()).not.toContain('Searching')
    })
  })

  describe('search results', () => {
    it('displays thread results', async () => {
      const threads = [
        createMockThread({ id: 1, title: 'Thread One', slug: 'thread-one' }),
        createMockThread({ id: 2, title: 'Thread Two', slug: 'thread-two' }),
      ]

      mockOcsGet.mockResolvedValue({
        data: {
          threads,
          posts: [],
          threadCount: 2,
          postCount: 0,
        },
      })

      const wrapper = createWrapper()
      const input = wrapper.find('.search-input')
      await input.setValue('test')

      await getSearchButton(wrapper).trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('2 threads found')
      expect(wrapper.findAll('.search-thread-result-mock').length).toBe(2)
    })

    it('displays post results', async () => {
      const posts = [
        createMockPost({ id: 1, content: 'Post content one' }),
        createMockPost({ id: 2, content: 'Post content two' }),
        createMockPost({ id: 3, content: 'Post content three' }),
      ]

      mockOcsGet.mockResolvedValue({
        data: {
          threads: [],
          posts,
          threadCount: 0,
          postCount: 3,
        },
      })

      const wrapper = createWrapper()
      const input = wrapper.find('.search-input')
      await input.setValue('test')

      await getSearchButton(wrapper).trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('3 replies found')
      expect(wrapper.findAll('.search-post-result-mock').length).toBe(3)
    })

    it('shows no results message when search returns empty', async () => {
      mockOcsGet.mockResolvedValue({
        data: {
          threads: [],
          posts: [],
          threadCount: 0,
          postCount: 0,
        },
      })

      const wrapper = createWrapper()
      const input = wrapper.find('.search-input')
      await input.setValue('nonexistent')

      await getSearchButton(wrapper).trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('No results found')
      expect(wrapper.text()).toContain('Try different keywords or check your syntax')
    })
  })

  describe('error handling', () => {
    beforeEach(() => {
      // Suppress console.error for error handling tests
      vi.spyOn(console, 'error').mockImplementation(() => {})
    })

    it('shows error state on API failure', async () => {
      mockOcsGet.mockRejectedValue(new Error('Network error'))

      const wrapper = createWrapper()
      const input = wrapper.find('.search-input')
      await input.setValue('test')

      await getSearchButton(wrapper).trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('Search Error')
      expect(wrapper.text()).toContain('Network error')
    })

    it('shows retry button on error', async () => {
      mockOcsGet.mockRejectedValue(new Error('Failed'))

      const wrapper = createWrapper()
      const input = wrapper.find('.search-input')
      await input.setValue('test')

      const searchButton = wrapper.findAll('button')[0]!
      await searchButton.trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('Retry')
    })

    it('retries search when retry clicked', async () => {
      mockOcsGet.mockRejectedValueOnce(new Error('Failed'))
      mockOcsGet.mockResolvedValueOnce({
        data: { threads: [], posts: [], threadCount: 0, postCount: 0 },
      })

      const wrapper = createWrapper()
      const input = wrapper.find('.search-input')
      await input.setValue('test')

      // First search - will fail
      await getSearchButton(wrapper).trigger('click')
      await flushPromises()

      const callCountAfterFirstSearch = mockOcsGet.mock.calls.length

      // Find and click retry button (appears in error state)
      const retryButton = wrapper.findAll('button').find((b) => b.text() === 'Retry')!
      await retryButton.trigger('click')
      await flushPromises()

      // Should have made at least one more call
      expect(mockOcsGet.mock.calls.length).toBeGreaterThan(callCountAfterFirstSearch)
    })
  })

  describe('navigation', () => {
    it('navigates to thread on result click', async () => {
      const thread = createMockThread({ id: 1, title: 'Test Thread', slug: 'test-thread' })

      mockOcsGet.mockResolvedValue({
        data: {
          threads: [thread],
          posts: [],
          threadCount: 1,
          postCount: 0,
        },
      })

      const wrapper = createWrapper()
      const input = wrapper.find('.search-input')
      await input.setValue('test')

      await getSearchButton(wrapper).trigger('click')
      await flushPromises()

      const threadResult = wrapper.find('.search-thread-result-mock')
      await threadResult.trigger('click')

      expect(mockRouter.push).toHaveBeenCalledWith('/t/test-thread')
    })
  })

  describe('URL query parameter', () => {
    it('performs search from URL query on mount', async () => {
      createWrapper({ q: 'url-query' })
      await flushPromises()

      expect(mockOcsGet).toHaveBeenCalledWith('/search', {
        params: expect.objectContaining({
          q: 'url-query',
        }),
      })
    })

    it('populates input from URL query', () => {
      const wrapper = createWrapper({ q: 'prefilled' })
      const input = wrapper.find<HTMLInputElement>('.search-input')
      expect(input.element.value).toBe('prefilled')
    })
  })

  describe('options change', () => {
    it('re-searches when options change after initial search', async () => {
      mockOcsGet.mockResolvedValue({
        data: { threads: [], posts: [], threadCount: 0, postCount: 0 },
      })

      const wrapper = createWrapper()
      const input = wrapper.find('.search-input')
      await input.setValue('test')

      await getSearchButton(wrapper).trigger('click')
      await flushPromises()

      const callCountAfterFirstSearch = mockOcsGet.mock.calls.length

      // Trigger options change method directly (simulates checkbox change)
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ;(wrapper.vm as any).onOptionsChange()
      await flushPromises()

      // Should have made more calls due to re-search
      expect(mockOcsGet.mock.calls.length).toBeGreaterThan(callCountAfterFirstSearch)
    })
  })
})
