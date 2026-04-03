import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import ModerationThreadDialog from './ModerationThreadDialog.vue'

// Uses global mocks for @nextcloud/l10n, NcButton, NcDialog, NcLoadingIcon from test-setup.ts

vi.mock('@icons/DeleteRestore.vue', () => createIconMock('DeleteRestoreIcon'))

vi.mock('@/components/PostCard', () =>
  createComponentMock('PostCard', {
    template: '<div class="post-card-mock" :data-id="post.id" :data-first="isFirstPost" />',
    props: ['post', 'isFirstPost'],
  }),
)

vi.mock('@/components/Pagination', () =>
  createComponentMock('Pagination', {
    template: '<div class="pagination-mock" />',
    props: ['currentPage', 'maxPages'],
    emits: ['update:currentPage'],
  }),
)

// Uses global mock for @/axios from test-setup.ts
import { ocs } from '@/axios'
const mockOcsGet = vi.mocked(ocs.get)

const createThreadResponse = (overrides: Record<string, unknown> = {}) => ({
  data: {
    title: 'Test Thread',
    posts: [
      { id: 1, isFirstPost: true, content: '<p>First post</p>', deletedAt: null },
      { id: 2, isFirstPost: false, content: '<p>Reply 1</p>', deletedAt: null },
      { id: 3, isFirstPost: false, content: '<p>Reply 2</p>', deletedAt: null },
    ],
    totalPosts: 3,
    ...overrides,
  },
})

describe('ModerationThreadDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockOcsGet.mockResolvedValue(createThreadResponse())
  })

  describe('rendering', () => {
    it('should not render dialog content when closed', () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: false },
      })
      expect(wrapper.find('.nc-dialog').exists()).toBe(false)
    })

    it('should render dialog when open', () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: true, threadId: 1 },
      })
      expect(wrapper.find('.nc-dialog').exists()).toBe(true)
    })

    it('should show loading icon while loading thread', async () => {
      mockOcsGet.mockReturnValue(new Promise(() => {}))

      const wrapper = mount(ModerationThreadDialog, {
        props: { open: false, threadId: 1 },
      })

      await wrapper.setProps({ open: true })
      await wrapper.vm.$nextTick()

      expect(wrapper.find('.nc-loading-icon').exists()).toBe(true)
    })

    it('should show posts after thread is loaded', async () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: false, threadId: 1 },
      })

      await wrapper.setProps({ open: true })
      await vi.dynamicImportSettled()
      await wrapper.vm.$nextTick()

      expect(wrapper.findAll('.post-card-mock').length).toBeGreaterThan(0)
    })

    it('should render first post separately', async () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: false, threadId: 1 },
      })

      await wrapper.setProps({ open: true })
      await vi.dynamicImportSettled()
      await wrapper.vm.$nextTick()

      const firstPost = wrapper
        .findAll('.post-card-mock')
        .find((el) => el.attributes('data-first') === 'true')
      expect(firstPost).toBeDefined()
    })

    it('should show restore thread button', () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: true, threadId: 1 },
      })
      const restoreButton = wrapper
        .findAll('button')
        .find((b) => b.text().includes('Restore thread'))
      expect(restoreButton).toBeDefined()
    })
  })

  describe('title', () => {
    it('should use thread title from response', async () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: false, threadId: 1 },
      })

      await wrapper.setProps({ open: true })
      await vi.dynamicImportSettled()
      await wrapper.vm.$nextTick()

      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const vm = wrapper.vm as any
      expect(vm.title).toBe('Test Thread')
    })

    it('should fall back to threadTitle prop', () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: true, threadId: 1, threadTitle: 'Fallback Title' },
      })
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const vm = wrapper.vm as any
      // Before thread is loaded, should use the prop
      expect(vm.title).toBe('Fallback Title')
    })
  })

  describe('API calls', () => {
    it('should fetch thread when dialog opens', async () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: false, threadId: 42 },
      })

      await wrapper.setProps({ open: true })
      await wrapper.vm.$nextTick()

      expect(mockOcsGet).toHaveBeenCalledWith('/moderation/threads/42', {
        params: { postLimit: 21, postOffset: 0 },
      })
    })

    it('should not fetch when threadId is null', async () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: false, threadId: null },
      })

      await wrapper.setProps({ open: true })
      await wrapper.vm.$nextTick()

      expect(mockOcsGet).not.toHaveBeenCalled()
    })

    it('should clear state when dialog closes', async () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: true, threadId: 1 },
      })
      await vi.dynamicImportSettled()

      await wrapper.setProps({ open: false })
      await wrapper.vm.$nextTick()

      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const vm = wrapper.vm as any
      expect(vm.thread).toBeNull()
      expect(vm.firstPost).toBeNull()
      expect(vm.replies).toEqual([])
    })
  })

  describe('pagination', () => {
    it('should show pagination when there are multiple pages of replies', async () => {
      mockOcsGet.mockResolvedValue(
        createThreadResponse({
          totalPosts: 50,
        }),
      )

      const wrapper = mount(ModerationThreadDialog, {
        props: { open: false, threadId: 1 },
      })

      await wrapper.setProps({ open: true })
      await vi.dynamicImportSettled()
      await wrapper.vm.$nextTick()

      expect(wrapper.find('.pagination-mock').exists()).toBe(true)
    })

    it('should not show pagination when replies fit in one page', async () => {
      mockOcsGet.mockResolvedValue(createThreadResponse({ totalPosts: 3 }))

      const wrapper = mount(ModerationThreadDialog, {
        props: { open: false, threadId: 1 },
      })

      await wrapper.setProps({ open: true })
      await vi.dynamicImportSettled()
      await wrapper.vm.$nextTick()

      expect(wrapper.find('.pagination-mock').exists()).toBe(false)
    })

    it('should compute replyMaxPages correctly', async () => {
      mockOcsGet.mockResolvedValue(createThreadResponse({ totalPosts: 41 }))

      const wrapper = mount(ModerationThreadDialog, {
        props: { open: false, threadId: 1 },
      })

      await wrapper.setProps({ open: true })
      await vi.dynamicImportSettled()
      await wrapper.vm.$nextTick()

      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const vm = wrapper.vm as any
      // totalReplies = 41 - 1 = 40, maxPages = ceil(40/20) = 2
      expect(vm.replyMaxPages).toBe(2)
    })

    it('should reset page to 1 when dialog reopens', async () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: false, threadId: 1 },
      })

      await wrapper.setProps({ open: true })
      await vi.dynamicImportSettled()

      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const vm = wrapper.vm as any
      vm.replyPage = 3

      await wrapper.setProps({ open: false })
      await wrapper.vm.$nextTick()

      await wrapper.setProps({ open: true })
      await wrapper.vm.$nextTick()

      expect(vm.replyPage).toBe(1)
    })
  })

  describe('events', () => {
    it('should emit restore when restore button is clicked', async () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: true, threadId: 1 },
      })
      const restoreButton = wrapper
        .findAll('button')
        .find((b) => b.text().includes('Restore thread'))
      await restoreButton?.trigger('click')
      expect(wrapper.emitted('restore')).toBeTruthy()
    })

    it('should disable restore button when restoring', () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: true, threadId: 1, restoring: true },
      })
      const restoreButton = wrapper
        .findAll('button')
        .find((b) => b.text().includes('Restore thread'))
      expect(restoreButton?.attributes('disabled')).toBeDefined()
    })

    it('should show loading icon in restore button when restoring', () => {
      const wrapper = mount(ModerationThreadDialog, {
        props: { open: true, threadId: 1, restoring: true },
      })
      const restoreButton = wrapper
        .findAll('button')
        .find((b) => b.text().includes('Restore thread'))
      expect(restoreButton?.find('.nc-loading-icon').exists()).toBe(true)
    })
  })

  describe('deleted posts styling', () => {
    it('should apply deleted-post class to posts with deletedAt', async () => {
      mockOcsGet.mockResolvedValue(
        createThreadResponse({
          posts: [
            { id: 1, isFirstPost: true, content: '<p>First</p>', deletedAt: 1000 },
            { id: 2, isFirstPost: false, content: '<p>Reply</p>', deletedAt: null },
          ],
        }),
      )

      const wrapper = mount(ModerationThreadDialog, {
        props: { open: false, threadId: 1 },
      })

      await wrapper.setProps({ open: true })
      await vi.dynamicImportSettled()
      await wrapper.vm.$nextTick()

      expect(wrapper.find('.deleted-post').exists()).toBe(true)
    })
  })
})
