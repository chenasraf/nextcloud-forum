import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import AdminModerationView from '../admin/AdminModerationView.vue'

// Uses global mocks for @/axios, @nextcloud/l10n, @nextcloud/dialogs, NcButton from test-setup.ts

vi.mock('@nextcloud/vue/components/NcTextField', () =>
  createComponentMock('NcTextField', { props: ['modelValue', 'placeholder', 'label'] }),
)
vi.mock('@icons/SortCalendarDescending.vue', () => createIconMock('SortCalendarDescendingIcon'))
vi.mock('@icons/SortCalendarAscending.vue', () => createIconMock('SortCalendarAscendingIcon'))

vi.mock('@/components/PageWrapper', () =>
  createComponentMock('PageWrapper', { template: '<div><slot /></div>' }),
)
vi.mock('@/components/PageHeader', () =>
  createComponentMock('PageHeader', { props: ['title', 'subtitle'] }),
)
vi.mock('@/components/ModerationThreadDialog', () =>
  createComponentMock('ModerationThreadDialog', {
    props: ['open', 'threadId', 'threadTitle', 'restoring', 'deleting'],
  }),
)
vi.mock('@/components/ModerationDeletedList', () =>
  createComponentMock('ModerationDeletedList', {
    props: [
      'mode',
      'items',
      'total',
      'page',
      'perPage',
      'loading',
      'error',
      'restoring',
      'deleting',
      'selectedIds',
      'bulkDeleting',
    ],
    emits: [
      'view',
      'restore',
      'delete',
      'retry',
      'update:page',
      'update:selectedIds',
      'bulk-delete',
    ],
  }),
)
vi.mock('@/components/ModerationNestedRepliesDialog', () =>
  createComponentMock('ModerationNestedRepliesDialog', {
    props: ['open', 'postCount', 'replyCount'],
    emits: ['update:open', 'choose'],
  }),
)

import { ocs } from '@/axios'
const mockOcsGet = vi.mocked(ocs.get)
const mockOcsDelete = vi.mocked(ocs.delete)
const mockOcsPost = vi.mocked(ocs.post)

const replies = [
  { id: 10, replyCount: 0 },
  { id: 11, replyCount: 2 },
  { id: 12, replyCount: 1 },
]

async function mountOnRepliesTab() {
  const wrapper = mount(AdminModerationView)
  await flushPromises()
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const vm = wrapper.vm as any
  vm.switchTab('replies')
  await flushPromises()
  return wrapper
}

function list(wrapper: Awaited<ReturnType<typeof mountOnRepliesTab>>) {
  return wrapper.findComponent({ name: 'ModerationDeletedList' })
}

function nestedDialog(wrapper: Awaited<ReturnType<typeof mountOnRepliesTab>>) {
  return wrapper.findComponent({ name: 'ModerationNestedRepliesDialog' })
}

describe('AdminModerationView', () => {
  const confirmSpy = vi.fn(() => true)

  beforeEach(() => {
    vi.clearAllMocks()
    mockOcsGet.mockResolvedValue({ data: { items: replies, total: replies.length } })
    confirmSpy.mockReturnValue(true)
    vi.stubGlobal('confirm', confirmSpy)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  describe('permanently deleting a reply', () => {
    it('should confirm and delete a reply without nested replies', async () => {
      const wrapper = await mountOnRepliesTab()

      list(wrapper).vm.$emit('delete', replies[0])
      await flushPromises()

      expect(confirmSpy).toHaveBeenCalled()
      expect(nestedDialog(wrapper).props('open')).toBe(false)
      expect(mockOcsDelete).toHaveBeenCalledWith('/moderation/replies/10', {
        params: { replies: 'keep' },
      })
    })

    it('should not delete when the confirmation is declined', async () => {
      confirmSpy.mockReturnValue(false)
      const wrapper = await mountOnRepliesTab()

      list(wrapper).vm.$emit('delete', replies[0])
      await flushPromises()

      expect(mockOcsDelete).not.toHaveBeenCalled()
    })

    it('should ask what to do with nested replies instead of confirming', async () => {
      const wrapper = await mountOnRepliesTab()

      list(wrapper).vm.$emit('delete', replies[1])
      await flushPromises()

      expect(confirmSpy).not.toHaveBeenCalled()
      expect(nestedDialog(wrapper).props()).toMatchObject({
        open: true,
        postCount: 1,
        replyCount: 2,
      })
      expect(mockOcsDelete).not.toHaveBeenCalled()
    })

    it.each(['keep', 'delete'])('should send the chosen mode %s', async (mode) => {
      const wrapper = await mountOnRepliesTab()

      list(wrapper).vm.$emit('delete', replies[1])
      await flushPromises()
      nestedDialog(wrapper).vm.$emit('choose', mode)
      await flushPromises()

      expect(nestedDialog(wrapper).props('open')).toBe(false)
      expect(mockOcsDelete).toHaveBeenCalledWith('/moderation/replies/11', {
        params: { replies: mode },
      })
    })

    it('should not delete when the nested replies dialog is closed', async () => {
      const wrapper = await mountOnRepliesTab()

      list(wrapper).vm.$emit('delete', replies[1])
      await flushPromises()
      nestedDialog(wrapper).vm.$emit('update:open', false)
      await flushPromises()

      expect(nestedDialog(wrapper).props('open')).toBe(false)
      expect(mockOcsDelete).not.toHaveBeenCalled()
    })
  })

  describe('bulk deleting replies', () => {
    it('should confirm and keep nested replies when none are selected', async () => {
      const wrapper = await mountOnRepliesTab()

      list(wrapper).vm.$emit('update:selectedIds', [10])
      list(wrapper).vm.$emit('bulk-delete')
      await flushPromises()

      expect(confirmSpy).toHaveBeenCalled()
      expect(mockOcsPost).toHaveBeenCalledWith('/moderation/replies/bulk-delete', {
        ids: [10],
        replies: 'keep',
      })
    })

    it('should ask once about the nested replies of all selected replies', async () => {
      const wrapper = await mountOnRepliesTab()

      list(wrapper).vm.$emit('update:selectedIds', [10, 11, 12])
      list(wrapper).vm.$emit('bulk-delete')
      await flushPromises()

      expect(confirmSpy).not.toHaveBeenCalled()
      expect(nestedDialog(wrapper).props()).toMatchObject({
        open: true,
        postCount: 3,
        replyCount: 3,
      })

      nestedDialog(wrapper).vm.$emit('choose', 'delete')
      await flushPromises()

      expect(mockOcsPost).toHaveBeenCalledTimes(1)
      expect(mockOcsPost).toHaveBeenCalledWith('/moderation/replies/bulk-delete', {
        ids: [10, 11, 12],
        replies: 'delete',
      })
    })

    it('should not send a nested replies mode for threads', async () => {
      const wrapper = mount(AdminModerationView)
      await flushPromises()

      list(wrapper).vm.$emit('update:selectedIds', [1])
      list(wrapper).vm.$emit('bulk-delete')
      await flushPromises()

      expect(mockOcsPost).toHaveBeenCalledWith('/moderation/threads/bulk-delete', { ids: [1] })
    })
  })
})
