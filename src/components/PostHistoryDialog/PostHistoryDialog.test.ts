import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import type { PostHistoryResponse, Post, PostHistoryEntry, User } from '@/types'

// Mock axios - must use factory that doesn't reference external variables
vi.mock('@/axios', () => ({
  ocs: {
    get: vi.fn(),
  },
}))

// Mock icons
vi.mock('@icons/History.vue', () => createIconMock('HistoryIcon'))

// Mock UserInfo component
vi.mock('@/components/UserInfo', () =>
  createComponentMock('UserInfo', {
    template: '<span class="user-info-mock">{{ displayName }}</span>',
    props: ['userId', 'displayName', 'isDeleted', 'avatarSize', 'inline'],
  }),
)

// Import after mocks
import { ocs } from '@/axios'
import PostHistoryDialog from './PostHistoryDialog.vue'

const mockGet = vi.mocked(ocs.get)

describe('PostHistoryDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGet.mockResolvedValue({ data: null } as never)
  })

  const createMockUser = (overrides: Partial<User> = {}): User => ({
    userId: 'testuser',
    displayName: 'Test User',
    isDeleted: false,
    roles: [],
    signature: null,
    signatureRaw: null,
    ...overrides,
  })

  const createMockPost = (overrides: Partial<Post> = {}): Post => ({
    id: 1,
    threadId: 1,
    authorId: 'testuser',
    content: '<p>Current content</p>',
    contentRaw: 'Current content',
    isEdited: true,
    isFirstPost: false,
    editedAt: 1700000000,
    createdAt: 1699000000,
    updatedAt: 1700000000,
    author: createMockUser(),
    ...overrides,
  })

  const createMockHistoryEntry = (overrides: Partial<PostHistoryEntry> = {}): PostHistoryEntry => ({
    id: 1,
    postId: 1,
    content: '<p>Old content</p>',
    editedBy: 'editor1',
    editedAt: 1699500000,
    editor: createMockUser({ userId: 'editor1', displayName: 'Editor One' }),
    ...overrides,
  })

  const createMockHistoryResponse = (
    overrides: Partial<PostHistoryResponse> = {},
  ): PostHistoryResponse => ({
    current: createMockPost(),
    history: [createMockHistoryEntry()],
    ...overrides,
  })

  const createWrapper = (props = {}) => {
    return mount(PostHistoryDialog, {
      props: {
        open: true,
        postId: 1,
        ...props,
      },
    })
  }

  describe('rendering', () => {
    it('renders the dialog when open', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.nc-dialog').exists()).toBe(true)
    })

    it('does not render the dialog when closed', () => {
      const wrapper = createWrapper({ open: false })
      expect(wrapper.find('.nc-dialog').exists()).toBe(false)
    })

    it('passes the correct title to dialog', async () => {
      mockGet.mockResolvedValue({ data: createMockHistoryResponse() } as never)
      const wrapper = createWrapper()
      await flushPromises()

      // The title is passed as a prop to NcDialog, not rendered as text
      const vm = wrapper.vm as unknown as { strings: { title: string } }
      expect(vm.strings.title).toBe('Edit history')
    })
  })

  describe('loading state', () => {
    it('shows loading state while fetching history', async () => {
      let resolvePromise: (value: unknown) => void
      mockGet.mockImplementation(
        () =>
          new Promise((resolve) => {
            resolvePromise = resolve
          }) as never,
      )

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      expect(wrapper.find('.loading-state').exists()).toBe(true)
      expect(wrapper.text()).toContain('Loading history')

      resolvePromise!({ data: createMockHistoryResponse() })
      await flushPromises()

      expect(wrapper.find('.loading-state').exists()).toBe(false)
    })
  })

  describe('error state', () => {
    it('displays error state when fetch fails', async () => {
      mockGet.mockRejectedValue(new Error('Network error'))
      vi.spyOn(console, 'error').mockImplementation(() => {})

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      expect(wrapper.find('.error-state').exists()).toBe(true)
      expect(wrapper.text()).toContain('Failed to load edit history')
    })
  })

  describe('empty state', () => {
    it('displays empty state when no history exists', async () => {
      mockGet.mockResolvedValue({
        data: createMockHistoryResponse({ history: [] }),
      } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      expect(wrapper.find('.empty-state').exists()).toBe(true)
      expect(wrapper.text()).toContain('This post has no edit history')
    })

    it('displays history icon in empty state', async () => {
      mockGet.mockResolvedValue({
        data: createMockHistoryResponse({ history: [] }),
      } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      // The icon mock uses kebab-case class name: HistoryIcon -> .history-icon
      expect(wrapper.find('.history-icon').exists()).toBe(true)
    })
  })

  describe('history content', () => {
    it('displays current version', async () => {
      mockGet.mockResolvedValue({ data: createMockHistoryResponse() } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      expect(wrapper.find('.current-version').exists()).toBe(true)
      expect(wrapper.text()).toContain('Current version')
    })

    it('displays current version content', async () => {
      const response = createMockHistoryResponse({
        current: createMockPost({ content: '<p>This is current content</p>' }),
      })
      mockGet.mockResolvedValue({ data: response } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      expect(wrapper.find('.entry-content').html()).toContain('This is current content')
    })

    it('displays historical versions', async () => {
      const response = createMockHistoryResponse({
        history: [
          createMockHistoryEntry({ id: 1, content: '<p>Version 1 content</p>' }),
          createMockHistoryEntry({ id: 2, content: '<p>Version 2 content</p>' }),
        ],
      })
      mockGet.mockResolvedValue({ data: response } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const entries = wrapper.findAll('.history-entry')
      // 1 current + 2 historical
      expect(entries.length).toBe(3)
    })

    it('displays version labels correctly', async () => {
      const response = createMockHistoryResponse({
        history: [createMockHistoryEntry({ id: 1 }), createMockHistoryEntry({ id: 2 })],
      })
      mockGet.mockResolvedValue({ data: response } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      // Translation mock replaces {index} with actual values
      // Version labels should be "Version 2", "Version 1" (in reverse order)
      expect(wrapper.text()).toContain('Version 2')
      expect(wrapper.text()).toContain('Version 1')
    })

    it('displays editor info for historical versions', async () => {
      const response = createMockHistoryResponse({
        history: [
          createMockHistoryEntry({
            editor: createMockUser({ userId: 'editor1', displayName: 'Editor One' }),
          }),
        ],
      })
      mockGet.mockResolvedValue({ data: response } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      expect(wrapper.text()).toContain('Edited by')
      expect(wrapper.find('.user-info-mock').exists()).toBe(true)
    })
  })

  describe('API calls', () => {
    it('fetches history when dialog opens', async () => {
      const wrapper = createWrapper({ open: true, postId: 42 })
      await flushPromises()

      expect(mockGet).toHaveBeenCalledWith('/posts/42/history')
    })

    it('does not fetch when dialog is closed', async () => {
      createWrapper({ open: false, postId: 42 })
      await flushPromises()

      expect(mockGet).not.toHaveBeenCalled()
    })

    it('refetches when dialog reopens', async () => {
      const wrapper = createWrapper({ open: true, postId: 42 })
      await flushPromises()

      expect(mockGet).toHaveBeenCalledTimes(1)

      // Close
      await wrapper.setProps({ open: false })
      await flushPromises()

      // Reopen
      await wrapper.setProps({ open: true })
      await flushPromises()

      expect(mockGet).toHaveBeenCalledTimes(2)
    })

    it('clears data when dialog closes', async () => {
      mockGet.mockResolvedValue({ data: createMockHistoryResponse() } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      expect(wrapper.find('.history-content').exists()).toBe(true)

      await wrapper.setProps({ open: false })
      await flushPromises()

      // Reopen - should show loading again
      mockGet.mockImplementation(
        () =>
          new Promise(() => {
            /* never resolves */
          }) as never,
      )
      await wrapper.setProps({ open: true })
      await flushPromises()

      expect(wrapper.find('.loading-state').exists()).toBe(true)
    })
  })

  describe('close event', () => {
    it('emits update:open event when close button is clicked', async () => {
      mockGet.mockResolvedValue({ data: createMockHistoryResponse() } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const closeButton = wrapper.find('button')
      await closeButton.trigger('click')

      expect(wrapper.emitted('update:open')).toBeTruthy()
      expect(wrapper.emitted('update:open')![0]).toEqual([false])
    })

    it('emits update:open event when handleClose is called', async () => {
      const wrapper = createWrapper({ open: true })

      ;(wrapper.vm as unknown as { handleClose: () => void }).handleClose()

      expect(wrapper.emitted('update:open')).toBeTruthy()
      expect(wrapper.emitted('update:open')![0]).toEqual([false])
    })
  })

  describe('timestamps', () => {
    it('displays editedAt timestamp for current version when edited', async () => {
      const response = createMockHistoryResponse({
        current: createMockPost({ editedAt: 1700000000, createdAt: 1699000000 }),
      })
      mockGet.mockResolvedValue({ data: response } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const dateTime = wrapper.find('.current-version .nc-datetime')
      expect(dateTime.exists()).toBe(true)
      // editedAt * 1000 = 1700000000000
      expect(dateTime.attributes('data-timestamp')).toBe('1700000000000')
    })

    it('displays createdAt timestamp for current version when not edited', async () => {
      const response = createMockHistoryResponse({
        current: createMockPost({ editedAt: null, createdAt: 1699000000 }),
      })
      mockGet.mockResolvedValue({ data: response } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const dateTime = wrapper.find('.current-version .nc-datetime')
      expect(dateTime.exists()).toBe(true)
      // createdAt * 1000 = 1699000000000
      expect(dateTime.attributes('data-timestamp')).toBe('1699000000000')
    })

    it('displays timestamps for historical versions', async () => {
      const response = createMockHistoryResponse({
        history: [createMockHistoryEntry({ editedAt: 1699500000 })],
      })
      mockGet.mockResolvedValue({ data: response } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const entries = wrapper.findAll('.history-entry:not(.current-version)')
      expect(entries.length).toBeGreaterThan(0)

      const dateTime = entries[0]!.find('.nc-datetime')
      expect(dateTime.exists()).toBe(true)
      expect(dateTime.attributes('data-timestamp')).toBe('1699500000000')
    })
  })

  describe('edge cases', () => {
    it('handles null historyData gracefully', async () => {
      mockGet.mockResolvedValue({ data: null } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      expect(wrapper.find('.empty-state').exists()).toBe(true)
    })

    it('uses editedBy as fallback when editor is not available', async () => {
      const response = createMockHistoryResponse({
        history: [
          {
            id: 1,
            postId: 1,
            content: '<p>Old content</p>',
            editedBy: 'fallback_user',
            editedAt: 1699500000,
            editor: undefined,
          },
        ],
      })
      mockGet.mockResolvedValue({ data: response } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      // Should use editedBy as userId and displayName
      const userInfo = wrapper.find('.user-info-mock')
      expect(userInfo.text()).toBe('fallback_user')
    })
  })
})
