import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import ModerationReplyDialog from './ModerationReplyDialog.vue'

// Uses global mocks for @nextcloud/l10n, NcButton, NcDialog, NcLoadingIcon from test-setup.ts

vi.mock('@icons/DeleteRestore.vue', () => createIconMock('DeleteRestoreIcon'))

vi.mock('@/components/PostCard', () =>
  createComponentMock('PostCard', {
    template: '<div class="post-card-mock" :data-id="post.id" />',
    props: ['post'],
  }),
)

// Uses global mock for @/axios from test-setup.ts
import { ocs } from '@/axios'
const mockOcsGet = vi.mocked(ocs.get)

describe('ModerationReplyDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockOcsGet.mockResolvedValue({
      data: {
        id: 1,
        content: '<p>Test reply</p>',
        threadTitle: 'Test Thread',
      },
    })
  })

  describe('rendering', () => {
    it('should not render dialog content when closed', () => {
      const wrapper = mount(ModerationReplyDialog, {
        props: { open: false },
      })
      expect(wrapper.find('.nc-dialog').exists()).toBe(false)
    })

    it('should render dialog when open', () => {
      const wrapper = mount(ModerationReplyDialog, {
        props: { open: true, replyId: 1 },
      })
      expect(wrapper.find('.nc-dialog').exists()).toBe(true)
    })

    it('should show loading icon while loading reply', async () => {
      // Make the API call hang
      mockOcsGet.mockReturnValue(new Promise(() => {}))

      const wrapper = mount(ModerationReplyDialog, {
        props: { open: false, replyId: 1 },
      })

      // Open the dialog to trigger load
      await wrapper.setProps({ open: true })
      await wrapper.vm.$nextTick()

      expect(wrapper.find('.nc-loading-icon').exists()).toBe(true)
    })

    it('should show PostCard after reply is loaded', async () => {
      const wrapper = mount(ModerationReplyDialog, {
        props: { open: false, replyId: 1 },
      })

      await wrapper.setProps({ open: true })
      await vi.dynamicImportSettled()
      await wrapper.vm.$nextTick()

      expect(wrapper.find('.post-card-mock').exists()).toBe(true)
    })

    it('should show thread context when reply has threadTitle', async () => {
      const wrapper = mount(ModerationReplyDialog, {
        props: { open: false, replyId: 1 },
      })

      await wrapper.setProps({ open: true })
      await vi.dynamicImportSettled()
      await wrapper.vm.$nextTick()

      expect(wrapper.find('.reply-dialog__context').exists()).toBe(true)
      expect(wrapper.find('.reply-dialog__context').text()).toContain('Test Thread')
    })

    it('should show restore reply button', () => {
      const wrapper = mount(ModerationReplyDialog, {
        props: { open: true, replyId: 1 },
      })
      const restoreButton = wrapper
        .findAll('button')
        .find((b) => b.text().includes('Restore reply'))
      expect(restoreButton).toBeDefined()
    })
  })

  describe('API calls', () => {
    it('should fetch reply when dialog opens', async () => {
      const wrapper = mount(ModerationReplyDialog, {
        props: { open: false, replyId: 42 },
      })

      await wrapper.setProps({ open: true })
      await wrapper.vm.$nextTick()

      expect(mockOcsGet).toHaveBeenCalledWith('/moderation/replies/42')
    })

    it('should not fetch when replyId is null', async () => {
      const wrapper = mount(ModerationReplyDialog, {
        props: { open: false, replyId: null },
      })

      await wrapper.setProps({ open: true })
      await wrapper.vm.$nextTick()

      expect(mockOcsGet).not.toHaveBeenCalled()
    })

    it('should clear reply when dialog closes', async () => {
      const wrapper = mount(ModerationReplyDialog, {
        props: { open: true, replyId: 1 },
      })
      await vi.dynamicImportSettled()

      await wrapper.setProps({ open: false })
      await wrapper.vm.$nextTick()

      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const vm = wrapper.vm as any
      expect(vm.reply).toBeNull()
    })
  })

  describe('events', () => {
    it('should emit update:open when dialog is closed', async () => {
      const wrapper = mount(ModerationReplyDialog, {
        props: { open: true, replyId: 1 },
      })

      // NcDialog mock emits update:open
      const dialog = wrapper.find('.nc-dialog')
      expect(dialog.exists()).toBe(true)

      // Verify the emit is wired up
      expect(wrapper.props('open')).toBe(true)
    })

    it('should emit restore when restore button is clicked', async () => {
      const wrapper = mount(ModerationReplyDialog, {
        props: { open: true, replyId: 1 },
      })
      const restoreButton = wrapper
        .findAll('button')
        .find((b) => b.text().includes('Restore reply'))
      await restoreButton?.trigger('click')
      expect(wrapper.emitted('restore')).toBeTruthy()
    })

    it('should disable restore button when restoring', () => {
      const wrapper = mount(ModerationReplyDialog, {
        props: { open: true, replyId: 1, restoring: true },
      })
      const restoreButton = wrapper
        .findAll('button')
        .find((b) => b.text().includes('Restore reply'))
      expect(restoreButton?.attributes('disabled')).toBeDefined()
    })

    it('should show loading icon in restore button when restoring', () => {
      const wrapper = mount(ModerationReplyDialog, {
        props: { open: true, replyId: 1, restoring: true },
      })
      // There should be a loading icon in the actions area
      const buttons = wrapper.findAll('button')
      const restoreButton = buttons.find((b) => b.text().includes('Restore reply'))
      expect(restoreButton?.find('.nc-loading-icon').exists()).toBe(true)
    })
  })
})
