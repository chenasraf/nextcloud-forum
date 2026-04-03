import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { computed, ref } from 'vue'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockThread, createMockPost, createMockUser } from '@/test-mocks'

// Uses global mock for @/axios from test-setup.ts

// Mock useCurrentUser composable
const mockUserId = ref<string | null>('testuser')
vi.mock('@/composables/useCurrentUser', () => ({
  useCurrentUser: () => ({
    userId: computed(() => mockUserId.value),
    displayName: computed(() => 'Test User'),
  }),
}))

// Mock useCurrentThread composable
const mockThread = ref(createMockThread({ id: 1, categoryId: 5, slug: 'test-thread' }))
const mockFetchThread = vi.fn()
vi.mock('@/composables/useCurrentThread', () => ({
  useCurrentThread: () => ({
    currentThread: mockThread,
    fetchThread: mockFetchThread,
  }),
}))

// Mock usePermissions composable
const mockCheckCategoryPermission = vi.fn()
vi.mock('@/composables/usePermissions', () => ({
  usePermissions: () => ({
    checkCategoryPermission: mockCheckCategoryPermission,
  }),
}))

// Uses global mock for @nextcloud/dialogs from test-setup.ts

// Mock icons
vi.mock('@icons/ArrowLeft.vue', () => createIconMock('ArrowLeftIcon'))
vi.mock('@icons/Refresh.vue', () => createIconMock('RefreshIcon'))
vi.mock('@icons/Reply.vue', () => createIconMock('ReplyIcon'))
vi.mock('@icons/Pin.vue', () => createIconMock('PinIcon'))
vi.mock('@icons/PinOff.vue', () => createIconMock('PinOffIcon'))
vi.mock('@icons/Lock.vue', () => createIconMock('LockIcon'))
vi.mock('@icons/LockOpen.vue', () => createIconMock('LockOpenIcon'))
vi.mock('@icons/Eye.vue', () => createIconMock('EyeIcon'))
vi.mock('@icons/Bell.vue', () => createIconMock('BellIcon'))
vi.mock('@icons/Bookmark.vue', () => createIconMock('BookmarkIcon'))
vi.mock('@icons/BookmarkOutline.vue', () => createIconMock('BookmarkOutlineIcon'))
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))
vi.mock('@icons/Check.vue', () => createIconMock('CheckIcon'))
vi.mock('@icons/FolderMove.vue', () => createIconMock('FolderMoveIcon'))

// Mock components
vi.mock('@/components/PageWrapper', () =>
  createComponentMock('PageWrapper', {
    template: '<div class="page-wrapper-mock"><slot name="toolbar" /><slot /></div>',
    props: ['fullWidth'],
  }),
)

vi.mock('@/components/AppToolbar', () =>
  createComponentMock('AppToolbar', {
    template: '<div class="app-toolbar-mock"><slot name="left" /><slot name="right" /></div>',
  }),
)

vi.mock('@/components/PostCard', () =>
  createComponentMock('PostCard', {
    template:
      '<div class="post-card-mock" :data-can-reply="canReply" :data-can-moderate="canModerateCategory" />',
    props: ['post', 'isFirstPost', 'isUnread', 'canModerateCategory', 'canReply'],
    emits: ['reply', 'update', 'delete', 'reassigned'],
  }),
)

vi.mock('@/components/PostReplyForm', () =>
  createComponentMock('PostReplyForm', {
    template: '<div class="post-reply-form-mock" />',
    emits: ['submit', 'cancel'],
  }),
)

vi.mock('@/components/Pagination', () =>
  createComponentMock('Pagination', {
    template: '<div class="pagination-mock" />',
    props: ['currentPage', 'maxPages'],
    emits: ['update:current-page'],
  }),
)

vi.mock('@/views/ThreadNotFound.vue', () =>
  createComponentMock('ThreadNotFound', {
    template: '<div class="thread-not-found-mock" />',
  }),
)

vi.mock('@/components/MoveCategoryDialog', () =>
  createComponentMock('MoveCategoryDialog', {
    template: '<div class="move-category-dialog-mock" />',
    props: ['open', 'currentCategoryId'],
    emits: ['update:open', 'move'],
  }),
)

vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () =>
  createComponentMock('NcCheckboxRadioSwitch', {
    template: '<div class="nc-checkbox-mock"><slot /></div>',
    props: ['modelValue', 'type'],
    emits: ['update:model-value'],
  }),
)

vi.mock('@nextcloud/vue/components/NcTextField', () =>
  createComponentMock('NcTextField', {
    template: '<input class="nc-text-field-mock" />',
    props: ['modelValue', 'disabled'],
  }),
)

import ThreadView from '../ThreadView.vue'
import { ocs } from '@/axios'

const mockOcsGet = vi.mocked(ocs.get)
const mockOcsPost = vi.mocked(ocs.post)

describe('ThreadView', () => {
  const mockFirstPost = createMockPost({ id: 1, content: '<p>First post</p>' })

  const mockRouter = {
    push: vi.fn(),
  }

  const mockRoute = {
    params: { slug: 'test-thread' },
    query: {},
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const mockGetResponse = (data: Record<string, unknown>): Promise<any> => Promise.resolve(data)

  beforeEach(() => {
    vi.clearAllMocks()
    mockUserId.value = 'testuser'
    mockThread.value = createMockThread({ id: 1, categoryId: 5, slug: 'test-thread' })
    mockFetchThread.mockResolvedValue(mockThread.value)
    mockCheckCategoryPermission.mockResolvedValue(false)
    mockOcsGet.mockImplementation((url: string) => {
      if (url.includes('/posts')) {
        return mockGetResponse({
          data: {
            firstPost: mockFirstPost,
            replies: [],
            pagination: {
              page: 1,
              perPage: 20,
              total: 1,
              totalPages: 1,
              startPage: 1,
              lastReadPostId: null,
            },
          },
        })
      }
      return mockGetResponse({ data: null })
    })
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    mockOcsPost.mockResolvedValue({ data: {} } as any)
  })

  const createWrapper = () => {
    return mount(ThreadView, {
      global: {
        mocks: {
          $router: mockRouter,
          $route: mockRoute,
        },
      },
    })
  }

  describe('canReply permission', () => {
    it('shows reply button when canReply is true', async () => {
      mockCheckCategoryPermission.mockImplementation((_id: number, perm: string) => {
        if (perm === 'canReply') return Promise.resolve(true)
        return Promise.resolve(false)
      })

      const wrapper = createWrapper()
      await flushPromises()

      const buttons = wrapper.findAll('button')
      expect(buttons.some((b) => b.text().includes('Reply'))).toBe(true)
    })

    it('hides reply button when canReply is false', async () => {
      mockCheckCategoryPermission.mockResolvedValue(false)

      const wrapper = createWrapper()
      await flushPromises()

      const buttons = wrapper.findAll('button')
      // Filter out Back button and Refresh button — look specifically for Reply in toolbar
      expect(buttons.some((b) => b.text() === 'Reply')).toBe(false)
    })

    it('shows reply form when canReply is true and user is authenticated', async () => {
      mockCheckCategoryPermission.mockImplementation((_id: number, perm: string) => {
        if (perm === 'canReply') return Promise.resolve(true)
        return Promise.resolve(false)
      })

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.find('.post-reply-form-mock').exists()).toBe(true)
    })

    it('hides reply form when canReply is false', async () => {
      mockCheckCategoryPermission.mockResolvedValue(false)

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.find('.post-reply-form-mock').exists()).toBe(false)
    })

    it('passes canReply to PostCard components', async () => {
      mockCheckCategoryPermission.mockImplementation((_id: number, perm: string) => {
        if (perm === 'canReply') return Promise.resolve(true)
        return Promise.resolve(false)
      })

      const wrapper = createWrapper()
      await flushPromises()

      const postCards = wrapper.findAll('.post-card-mock')
      expect(postCards.length).toBeGreaterThan(0)
      postCards.forEach((card) => {
        expect(card.attributes('data-can-reply')).toBe('true')
      })
    })

    it('passes canReply=false to PostCard when user lacks permission', async () => {
      mockCheckCategoryPermission.mockResolvedValue(false)

      const wrapper = createWrapper()
      await flushPromises()

      const postCards = wrapper.findAll('.post-card-mock')
      expect(postCards.length).toBeGreaterThan(0)
      postCards.forEach((card) => {
        expect(card.attributes('data-can-reply')).toBe('false')
      })
    })

    it('shows moderation buttons even when canReply is false', async () => {
      mockCheckCategoryPermission.mockImplementation((_id: number, perm: string) => {
        if (perm === 'canModerate') return Promise.resolve(true)
        if (perm === 'canReply') return Promise.resolve(false)
        return Promise.resolve(false)
      })

      const wrapper = createWrapper()
      await flushPromises()

      // Moderation buttons (lock, pin, move) should be visible
      expect(wrapper.find('.lock-icon').exists() || wrapper.find('.lock-open-icon').exists()).toBe(
        true,
      )
    })

    it('passes canModerateCategory=true to PostCard when user can moderate', async () => {
      mockCheckCategoryPermission.mockImplementation((_id: number, perm: string) => {
        if (perm === 'canModerate') return Promise.resolve(true)
        return Promise.resolve(false)
      })

      const wrapper = createWrapper()
      await flushPromises()

      const postCards = wrapper.findAll('.post-card-mock')
      expect(postCards.length).toBeGreaterThan(0)
      postCards.forEach((card) => {
        expect(card.attributes('data-can-moderate')).toBe('true')
      })
    })

    it('passes canModerateCategory=false to PostCard when user cannot moderate', async () => {
      mockCheckCategoryPermission.mockResolvedValue(false)

      const wrapper = createWrapper()
      await flushPromises()

      const postCards = wrapper.findAll('.post-card-mock')
      expect(postCards.length).toBeGreaterThan(0)
      postCards.forEach((card) => {
        expect(card.attributes('data-can-moderate')).toBe('false')
      })
    })

    it('checks canModerate permission for the thread category', async () => {
      mockThread.value = createMockThread({ id: 1, categoryId: 42, slug: 'test-thread' })
      mockFetchThread.mockResolvedValue(mockThread.value)
      mockCheckCategoryPermission.mockResolvedValue(false)

      createWrapper()
      await flushPromises()

      expect(mockCheckCategoryPermission).toHaveBeenCalledWith(42, 'canModerate')
    })

    it('shows guest message for unauthenticated users', async () => {
      mockUserId.value = null

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.text()).toContain('You must be signed in to reply to this thread.')
    })

    it('shows no reply permission message when canReply is false and thread is not locked', async () => {
      mockCheckCategoryPermission.mockResolvedValue(false)
      mockThread.value = createMockThread({ id: 1, categoryId: 5, isLocked: false })
      mockFetchThread.mockResolvedValue(mockThread.value)

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.text()).toContain('You do not have permission to reply in this category.')
    })
  })

  describe('guest reassignment', () => {
    const guestAuthorId = 'guest:abcdef1234567890abcdef1234567890'
    const guestAuthor = createMockUser({
      userId: guestAuthorId,
      displayName: 'BrightMountain42',
      isGuest: true,
    })

    it('updates first post author in-place after reassignment', async () => {
      const guestFirstPost = createMockPost({
        id: 1,
        authorId: guestAuthorId,
        author: guestAuthor,
        content: '<p>Guest post</p>',
      })

      mockOcsGet.mockImplementation((url: string) => {
        if (url.includes('/posts')) {
          return mockGetResponse({
            data: {
              firstPost: guestFirstPost,
              replies: [],
              pagination: {
                page: 1,
                perPage: 20,
                total: 0,
                totalPages: 1,
                startPage: 1,
                lastReadPostId: null,
              },
            },
          })
        }
        if (url === '/users/alice') {
          return mockGetResponse({ data: { userId: 'alice', roles: [{ id: 1, name: 'Default' }] } })
        }
        return mockGetResponse({ data: null })
      })

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as InstanceType<typeof ThreadView>
      await vm.handleReassigned({
        guestAuthorId,
        targetUserId: 'alice',
        targetDisplayName: 'Alice Smith',
      })
      await flushPromises()

      expect(vm.firstPost!.authorId).toBe('alice')
      expect(vm.firstPost!.author!.displayName).toBe('Alice Smith')
      expect(vm.firstPost!.author!.isGuest).toBe(false)
    })

    it('updates replies author in-place after reassignment', async () => {
      const guestReply = createMockPost({ id: 10, authorId: guestAuthorId, author: guestAuthor })
      const otherReply = createMockPost({
        id: 11,
        authorId: 'bob',
        author: createMockUser({ userId: 'bob' }),
      })

      mockOcsGet.mockImplementation((url: string) => {
        if (url.includes('/posts')) {
          return mockGetResponse({
            data: {
              firstPost: mockFirstPost,
              replies: [guestReply, otherReply],
              pagination: {
                page: 1,
                perPage: 20,
                total: 2,
                totalPages: 1,
                startPage: 1,
                lastReadPostId: null,
              },
            },
          })
        }
        if (url === '/users/alice') {
          return mockGetResponse({ data: { userId: 'alice', roles: [] } })
        }
        return mockGetResponse({ data: null })
      })

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as InstanceType<typeof ThreadView>
      await vm.handleReassigned({
        guestAuthorId,
        targetUserId: 'alice',
        targetDisplayName: 'Alice Smith',
      })
      await flushPromises()

      // Guest reply should be updated
      expect(vm.replies[0]!.authorId).toBe('alice')
      expect(vm.replies[0]!.author!.displayName).toBe('Alice Smith')
      expect(vm.replies[0]!.author!.isGuest).toBe(false)

      // Other reply should be unchanged
      expect(vm.replies[1]!.authorId).toBe('bob')
    })

    it('updates thread header author after reassignment', async () => {
      mockThread.value = createMockThread({
        id: 1,
        categoryId: 5,
        authorId: guestAuthorId,
        author: guestAuthor,
      })
      mockFetchThread.mockResolvedValue(mockThread.value)

      mockOcsGet.mockImplementation((url: string) => {
        if (url.includes('/posts')) {
          return mockGetResponse({
            data: {
              firstPost: mockFirstPost,
              replies: [],
              pagination: {
                page: 1,
                perPage: 20,
                total: 0,
                totalPages: 1,
                startPage: 1,
                lastReadPostId: null,
              },
            },
          })
        }
        if (url === '/users/alice') {
          return mockGetResponse({ data: { userId: 'alice', roles: [] } })
        }
        return mockGetResponse({ data: null })
      })

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as InstanceType<typeof ThreadView>
      await vm.handleReassigned({
        guestAuthorId,
        targetUserId: 'alice',
        targetDisplayName: 'Alice Smith',
      })
      await flushPromises()

      expect(vm.thread!.authorId).toBe('alice')
      expect(vm.thread!.author!.displayName).toBe('Alice Smith')
    })

    it('updates thread lastReplyAuthorId after reassignment', async () => {
      mockThread.value = createMockThread({
        id: 1,
        categoryId: 5,
        lastReplyAuthorId: guestAuthorId,
      })
      mockFetchThread.mockResolvedValue(mockThread.value)

      mockOcsGet.mockImplementation((url: string) => {
        if (url.includes('/posts')) {
          return mockGetResponse({
            data: {
              firstPost: mockFirstPost,
              replies: [],
              pagination: {
                page: 1,
                perPage: 20,
                total: 0,
                totalPages: 1,
                startPage: 1,
                lastReadPostId: null,
              },
            },
          })
        }
        return mockGetResponse({ data: null })
      })

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as InstanceType<typeof ThreadView>
      await vm.handleReassigned({
        guestAuthorId,
        targetUserId: 'alice',
        targetDisplayName: 'Alice Smith',
      })
      await flushPromises()

      expect(vm.thread!.lastReplyAuthorId).toBe('alice')
    })

    it('handles user fetch failure gracefully', async () => {
      const guestReply = createMockPost({ id: 10, authorId: guestAuthorId, author: guestAuthor })

      mockOcsGet.mockImplementation((url: string) => {
        if (url.includes('/posts')) {
          return mockGetResponse({
            data: {
              firstPost: mockFirstPost,
              replies: [guestReply],
              pagination: {
                page: 1,
                perPage: 20,
                total: 1,
                totalPages: 1,
                startPage: 1,
                lastReadPostId: null,
              },
            },
          })
        }
        if (url === '/users/alice') {
          return Promise.reject(new Error('Not found'))
        }
        return mockGetResponse({ data: null })
      })

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as InstanceType<typeof ThreadView>
      await vm.handleReassigned({
        guestAuthorId,
        targetUserId: 'alice',
        targetDisplayName: 'Alice Smith',
      })
      await flushPromises()

      // Should still update with empty roles
      expect(vm.replies[0]!.authorId).toBe('alice')
      expect(vm.replies[0]!.author!.displayName).toBe('Alice Smith')
      expect(vm.replies[0]!.author!.roles).toEqual([])
    })
  })
})
