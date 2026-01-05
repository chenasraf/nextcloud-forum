import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock } from '@/test-utils'
import { createMockPost, createMockUser } from '@/test-mocks'
import SearchPostResult from './SearchPostResult.vue'

// Uses global mocks for @nextcloud/l10n, isDarkTheme, NcDateTime from test-setup.ts

vi.mock('@icons/Account.vue', () => createIconMock('AccountIcon'))
vi.mock('@icons/Clock.vue', () => createIconMock('ClockIcon'))
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))

const mockPush = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush }),
}))

describe('SearchPostResult', () => {
  beforeEach(() => {
    mockPush.mockClear()
  })

  describe('rendering', () => {
    it('should render thread title link', () => {
      const post = createMockPost({ threadTitle: 'Discussion Thread' })
      const wrapper = mount(SearchPostResult, {
        props: { post, query: 'test' },
        global: {
          stubs: {
            'router-link': {
              template: '<a class="thread-link"><slot /></a>',
              props: ['to'],
            },
          },
          mocks: { $router: { push: mockPush } },
        },
      })
      expect(wrapper.find('.thread-link').text()).toBe('Discussion Thread')
    })

    it('should show thread unavailable when no slug', () => {
      const post = createMockPost({ threadSlug: undefined })
      const wrapper = mount(SearchPostResult, {
        props: { post, query: 'test' },
        global: {
          stubs: { 'router-link': true },
          mocks: { $router: { push: mockPush } },
        },
      })
      expect(wrapper.find('.thread-missing').text()).toBe('Thread unavailable')
    })

    it('should render author name', () => {
      const post = createMockPost({
        author: createMockUser({ userId: 'john', displayName: 'John Doe' }),
      })
      const wrapper = mount(SearchPostResult, {
        props: { post, query: 'test' },
        global: {
          stubs: { 'router-link': true },
          mocks: { $router: { push: mockPush } },
        },
      })
      expect(wrapper.find('.author').text()).toContain('John Doe')
    })

    it('should show "Deleted user" when author is missing', () => {
      const post = createMockPost()
      post.author = undefined
      const wrapper = mount(SearchPostResult, {
        props: { post, query: 'test' },
        global: {
          stubs: { 'router-link': true },
          mocks: { $router: { push: mockPush } },
        },
      })
      expect(wrapper.find('.author').text()).toContain('Deleted user')
    })

    it('should show edited indicator when post is edited', () => {
      const post = createMockPost({ isEdited: true })
      const wrapper = mount(SearchPostResult, {
        props: { post, query: 'test' },
        global: {
          stubs: { 'router-link': true },
          mocks: { $router: { push: mockPush } },
        },
      })
      expect(wrapper.find('.edited').exists()).toBe(true)
      expect(wrapper.find('.pencil-icon').exists()).toBe(true)
    })

    it('should not show edited indicator when post is not edited', () => {
      const post = createMockPost({ isEdited: false })
      const wrapper = mount(SearchPostResult, {
        props: { post, query: 'test' },
        global: {
          stubs: { 'router-link': true },
          mocks: { $router: { push: mockPush } },
        },
      })
      expect(wrapper.find('.edited').exists()).toBe(false)
    })
  })

  describe('content processing', () => {
    it('should strip HTML from content', () => {
      const post = createMockPost({ content: '<p>Hello <strong>World</strong></p>' })
      const wrapper = mount(SearchPostResult, {
        props: { post, query: 'xyz' },
        global: {
          stubs: { 'router-link': true },
          mocks: { $router: { push: mockPush } },
        },
      })
      const content = wrapper.find('.post-content').text()
      expect(content).not.toContain('<p>')
      expect(content).not.toContain('<strong>')
    })

    it('should truncate long content', () => {
      const longContent = '<p>' + 'A'.repeat(500) + '</p>'
      const post = createMockPost({ content: longContent })
      const wrapper = mount(SearchPostResult, {
        props: { post, query: 'xyz' },
        global: {
          stubs: { 'router-link': true },
          mocks: { $router: { push: mockPush } },
        },
      })
      const content = wrapper.find('.post-content').text()
      expect(content.length).toBeLessThan(300)
      expect(content).toContain('...')
    })

    it('should highlight search terms', () => {
      const post = createMockPost({ content: '<p>This is a test post</p>' })
      const wrapper = mount(SearchPostResult, {
        props: { post, query: 'test' },
        global: {
          stubs: { 'router-link': true },
          mocks: { $router: { push: mockPush } },
        },
      })
      expect(wrapper.find('.post-content').html()).toContain('<mark>test</mark>')
    })
  })

  describe('navigation', () => {
    it('should navigate to post when clicked', async () => {
      const post = createMockPost({ threadSlug: 'my-thread', id: 42 })
      const wrapper = mount(SearchPostResult, {
        props: { post, query: 'test' },
        global: {
          stubs: { 'router-link': true },
          mocks: { $router: { push: mockPush } },
        },
      })
      await wrapper.find('.search-post-result').trigger('click')
      expect(mockPush).toHaveBeenCalledWith('/t/my-thread#post-42')
    })

    it('should not navigate when thread slug is missing', async () => {
      const post = createMockPost({ threadSlug: undefined })
      const wrapper = mount(SearchPostResult, {
        props: { post, query: 'test' },
        global: {
          stubs: { 'router-link': true },
          mocks: { $router: { push: mockPush } },
        },
      })
      await wrapper.find('.search-post-result').trigger('click')
      expect(mockPush).not.toHaveBeenCalled()
    })
  })
})
