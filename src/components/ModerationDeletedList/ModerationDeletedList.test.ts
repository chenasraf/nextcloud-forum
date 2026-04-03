import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock, createComponentMock, RouterLinkStub } from '@/test-utils'
import { createMockThread, createMockPost } from '@/test-mocks'
import ModerationDeletedList from './ModerationDeletedList.vue'

// Uses global mocks for @nextcloud/l10n, NcButton, NcEmptyContent, NcLoadingIcon, NcDateTime from test-setup.ts

vi.mock('@icons/DeleteRestore.vue', () => createIconMock('DeleteRestoreIcon'))

vi.mock('@/components/ThreadCard', () =>
  createComponentMock('ThreadCard', {
    template: '<div class="thread-card-mock" :data-id="thread.id" />',
    props: ['thread'],
  }),
)

vi.mock('@/components/PostCard', () =>
  createComponentMock('PostCard', {
    template: '<div class="post-card-mock" :data-id="post.id" />',
    props: ['post'],
  }),
)

vi.mock('@/components/Pagination', () =>
  createComponentMock('Pagination', {
    template: '<div class="pagination-mock" @click="$emit(\'update:currentPage\', 2)" />',
    props: ['currentPage', 'maxPages'],
    emits: ['update:currentPage'],
  }),
)

const defaultProps = {
  mode: 'threads' as const,
  items: [],
  total: 0,
  page: 1,
  perPage: 20,
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function mountWith(props: Record<string, any>) {
  return mount(ModerationDeletedList, {
    props,
    global: {
      stubs: { RouterLink: RouterLinkStub },
    },
  })
}

// Helper to create thread/post items with deletedAt (not in base types)
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function mockThread(overrides: Record<string, unknown> = {}): any {
  return createMockThread(overrides as Parameters<typeof createMockThread>[0])
}
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function mockPost(overrides: Record<string, unknown> = {}): any {
  return createMockPost(overrides as Parameters<typeof createMockPost>[0])
}

describe('ModerationDeletedList', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('loading state', () => {
    it('should show loading icon when loading', () => {
      const wrapper = mountWith({ ...defaultProps, loading: true })
      expect(wrapper.find('.nc-loading-icon').exists()).toBe(true)
    })

    it('should not show items when loading', () => {
      const wrapper = mountWith({ ...defaultProps, loading: true })
      expect(wrapper.find('.item-list').exists()).toBe(false)
    })
  })

  describe('error state', () => {
    it('should show error content when error is set', () => {
      const wrapper = mountWith({ ...defaultProps, error: 'Something went wrong' })
      expect(wrapper.find('.nc-empty-content').exists()).toBe(true)
      expect(wrapper.find('.title').text()).toBe('Error loading content')
    })

    it('should show retry button on error', () => {
      const wrapper = mountWith({ ...defaultProps, error: 'Something went wrong' })
      const retryButton = wrapper.findAll('button').find((b) => b.text().includes('Retry'))
      expect(retryButton).toBeDefined()
    })

    it('should emit retry when retry button is clicked', async () => {
      const wrapper = mountWith({ ...defaultProps, error: 'Something went wrong' })
      const retryButton = wrapper.findAll('button').find((b) => b.text().includes('Retry'))
      await retryButton?.trigger('click')
      expect(wrapper.emitted('retry')).toBeTruthy()
    })
  })

  describe('empty state', () => {
    it('should show empty content when no items', () => {
      const wrapper = mountWith({ ...defaultProps, items: [] })
      expect(wrapper.find('.nc-empty-content').exists()).toBe(true)
      expect(wrapper.find('.title').text()).toBe('No deleted content')
    })
  })

  describe('threads mode', () => {
    it('should render ThreadCard for each item in threads mode', () => {
      const items = [mockThread({ id: 1, deletedAt: 1000 }), mockThread({ id: 2, deletedAt: 2000 })]
      const wrapper = mountWith({ ...defaultProps, mode: 'threads', items, total: 2 })
      expect(wrapper.findAll('.thread-card-mock')).toHaveLength(2)
    })

    it('should make items clickable in threads mode', () => {
      const items = [mockThread({ id: 1, deletedAt: 1000 })]
      const wrapper = mountWith({ ...defaultProps, mode: 'threads', items, total: 1 })
      expect(wrapper.find('.deleted-item-wrapper.clickable').exists()).toBe(true)
    })

    it('should emit view when thread item is clicked', async () => {
      const items = [mockThread({ id: 1, deletedAt: 1000 })]
      const wrapper = mountWith({ ...defaultProps, mode: 'threads', items, total: 1 })
      await wrapper.find('.deleted-item-wrapper').trigger('click')
      expect(wrapper.emitted('view')).toBeTruthy()
      expect(wrapper.emitted('view')![0]).toEqual([items[0]])
    })
  })

  describe('replies mode', () => {
    it('should render PostCard for each item in replies mode', () => {
      const items = [mockPost({ id: 1, deletedAt: 1000 }), mockPost({ id: 2, deletedAt: 2000 })]
      const wrapper = mountWith({ ...defaultProps, mode: 'replies', items, total: 2 })
      expect(wrapper.findAll('.post-card-mock')).toHaveLength(2)
    })

    it('should not make items clickable in replies mode', () => {
      const items = [mockPost({ id: 1, deletedAt: 1000 })]
      const wrapper = mountWith({ ...defaultProps, mode: 'replies', items, total: 1 })
      expect(wrapper.find('.deleted-item-wrapper.clickable').exists()).toBe(false)
    })

    it('should show thread link for replies with threadSlug', () => {
      const items = [
        mockPost({
          id: 1,
          deletedAt: 1000,
          threadSlug: 'test-thread',
          threadTitle: 'Test Thread',
        }),
      ]
      const wrapper = mountWith({ ...defaultProps, mode: 'replies', items, total: 1 })
      const link = wrapper.find('.router-link')
      expect(link.exists()).toBe(true)
      expect(link.attributes('href')).toBe('/t/test-thread')
    })
  })

  describe('restore action', () => {
    it('should show restore button for each item', () => {
      const items = [mockThread({ id: 1, deletedAt: 1000 })]
      const wrapper = mountWith({ ...defaultProps, items, total: 1 })
      const restoreButton = wrapper.findAll('button').find((b) => b.text().includes('Restore'))
      expect(restoreButton).toBeDefined()
    })

    it('should render restore icon for each item', () => {
      const items = [mockThread({ id: 1, deletedAt: 1000 })]
      const wrapper = mountWith({ ...defaultProps, items, total: 1 })
      const restoreButtons = wrapper.findAll('button').filter((b) => b.text().includes('Restore'))
      expect(restoreButtons.length).toBe(1)
      expect(wrapper.find('.delete-restore-icon').exists()).toBe(true)
    })

    it('should disable restore button when restoring that item', () => {
      const items = [mockThread({ id: 1, deletedAt: 1000 })]
      const wrapper = mountWith({ ...defaultProps, items, total: 1, restoring: 1 })
      const restoreButton = wrapper.findAll('button').find((b) => b.text().includes('Restore'))
      expect(restoreButton?.attributes('disabled')).toBeDefined()
    })

    it('should show loading icon when restoring that item', () => {
      const items = [mockThread({ id: 1, deletedAt: 1000 })]
      const wrapper = mountWith({ ...defaultProps, items, total: 1, restoring: 1 })
      expect(wrapper.find('.nc-loading-icon').exists()).toBe(true)
    })
  })

  describe('pagination', () => {
    it('should show pagination when maxPages > 1', () => {
      const items = [mockThread({ id: 1, deletedAt: 1000 })]
      const wrapper = mountWith({ ...defaultProps, items, total: 40, perPage: 20 })
      expect(wrapper.find('.pagination-mock').exists()).toBe(true)
    })

    it('should not show pagination when maxPages is 1', () => {
      const items = [mockThread({ id: 1, deletedAt: 1000 })]
      const wrapper = mountWith({ ...defaultProps, items, total: 10, perPage: 20 })
      expect(wrapper.find('.pagination-mock').exists()).toBe(false)
    })

    it('should emit update:page when pagination changes', async () => {
      const items = [mockThread({ id: 1, deletedAt: 1000 })]
      const wrapper = mountWith({ ...defaultProps, items, total: 40, perPage: 20 })
      await wrapper.find('.pagination-mock').trigger('click')
      expect(wrapper.emitted('update:page')).toBeTruthy()
    })
  })

  describe('deleted badge', () => {
    it('should show deleted badge with timestamp', () => {
      const items = [mockThread({ id: 1, deletedAt: 1000 })]
      const wrapper = mountWith({ ...defaultProps, items, total: 1 })
      expect(wrapper.find('.deleted-badge').exists()).toBe(true)
      expect(wrapper.find('.deleted-badge').text()).toContain('Deleted')
    })
  })
})
