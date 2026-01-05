import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock } from '@/test-utils'
import { createMockThread, createMockUser } from '@/test-mocks'
import SearchThreadResult from './SearchThreadResult.vue'

// Uses global mocks for @nextcloud/l10n, isDarkTheme, NcDateTime from test-setup.ts

vi.mock('@icons/Folder.vue', () => createIconMock('FolderIcon'))
vi.mock('@icons/Account.vue', () => createIconMock('AccountIcon'))
vi.mock('@icons/Message.vue', () => createIconMock('MessageIcon'))
vi.mock('@icons/Eye.vue', () => createIconMock('EyeIcon'))
vi.mock('@icons/Clock.vue', () => createIconMock('ClockIcon'))
vi.mock('@icons/Pin.vue', () => createIconMock('PinIcon'))
vi.mock('@icons/Lock.vue', () => createIconMock('LockIcon'))

describe('SearchThreadResult', () => {
  describe('rendering', () => {
    it('should render thread title', () => {
      const thread = createMockThread({ title: 'How to configure settings' })
      const wrapper = mount(SearchThreadResult, {
        props: { thread, query: 'test' },
      })
      expect(wrapper.find('.thread-title').text()).toContain('How to configure settings')
    })

    it('should render category name', () => {
      const thread = createMockThread({ categoryName: 'Technical Support' })
      const wrapper = mount(SearchThreadResult, {
        props: { thread, query: 'test' },
      })
      expect(wrapper.find('.category').text()).toContain('Technical Support')
    })

    it('should render author name', () => {
      const thread = createMockThread({
        author: createMockUser({ userId: 'john', displayName: 'John Doe' }),
      })
      const wrapper = mount(SearchThreadResult, {
        props: { thread, query: 'test' },
      })
      expect(wrapper.find('.author').text()).toContain('John Doe')
    })

    it('should show "Deleted user" when author is missing', () => {
      const thread = createMockThread()
      thread.author = undefined
      const wrapper = mount(SearchThreadResult, {
        props: { thread, query: 'test' },
      })
      expect(wrapper.find('.author').text()).toContain('Deleted user')
    })
  })

  describe('badges', () => {
    it('should show pin icon when thread is pinned', () => {
      const thread = createMockThread({ isPinned: true })
      const wrapper = mount(SearchThreadResult, {
        props: { thread, query: 'test' },
      })
      expect(wrapper.find('.pin-icon').exists()).toBe(true)
    })

    it('should show lock icon when thread is locked', () => {
      const thread = createMockThread({ isLocked: true })
      const wrapper = mount(SearchThreadResult, {
        props: { thread, query: 'test' },
      })
      expect(wrapper.find('.lock-icon').exists()).toBe(true)
    })
  })

  describe('highlighting', () => {
    it('should highlight search terms in title', () => {
      const thread = createMockThread({ title: 'How to test your code' })
      const wrapper = mount(SearchThreadResult, {
        props: { thread, query: 'test' },
      })
      expect(wrapper.find('.thread-title').html()).toContain('<mark>test</mark>')
    })

    it('should handle quoted phrases', () => {
      const thread = createMockThread({ title: 'Testing the exact phrase match' })
      const wrapper = mount(SearchThreadResult, {
        props: { thread, query: '"exact phrase"' },
      })
      expect(wrapper.find('.thread-title').html()).toContain('<mark>exact phrase</mark>')
    })

    it('should exclude AND/OR operators from highlighting', () => {
      const thread = createMockThread({ title: 'Test AND production environments' })
      const wrapper = mount(SearchThreadResult, {
        props: { thread, query: 'test AND production' },
      })
      const html = wrapper.find('.thread-title').html().toLowerCase()
      expect(html).toContain('<mark>test</mark>')
      expect(html).toContain('<mark>production</mark>')
      expect(html).not.toContain('<mark>and</mark>')
    })
  })

  describe('events', () => {
    it('should emit click event when clicked', async () => {
      const thread = createMockThread()
      const wrapper = mount(SearchThreadResult, {
        props: { thread, query: 'test' },
      })
      await wrapper.find('.search-thread-result').trigger('click')
      expect(wrapper.emitted('click')).toBeTruthy()
    })
  })
})
