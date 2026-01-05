import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockPost, createMockUser, createMockRole } from '@/test-mocks'
import PostCard from './PostCard.vue'

// Mock icons
vi.mock('@icons/Reply.vue', () => createIconMock('ReplyIcon'))
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))
vi.mock('@icons/History.vue', () => createIconMock('HistoryIcon'))

// Mock components
vi.mock('@/components/UserInfo', () =>
  createComponentMock('UserInfo', {
    template: '<div class="user-info-mock" :data-user-id="userId"><slot name="meta" /></div>',
    props: ['userId', 'displayName', 'isDeleted', 'avatarSize', 'roles'],
  }),
)

vi.mock('@/components/PostReactions', () =>
  createComponentMock('PostReactions', {
    template: '<div class="post-reactions-mock" :data-post-id="postId" />',
    props: ['postId', 'reactions'],
  }),
)

vi.mock('@/components/PostEditForm', () =>
  createComponentMock('PostEditForm', {
    template: '<div class="post-edit-form-mock" />',
    props: ['initialContent'],
  }),
)

vi.mock('@/components/PostHistoryDialog', () =>
  createComponentMock('PostHistoryDialog', {
    template: '<div class="post-history-dialog-mock" v-if="open" />',
    props: ['open', 'postId'],
  }),
)

// Mock NcActions and NcActionButton
vi.mock('@nextcloud/vue/components/NcActions', () =>
  createComponentMock('NcActions', {
    template: '<div class="nc-actions-mock"><slot /></div>',
    props: [],
  }),
)

vi.mock('@nextcloud/vue/components/NcActionButton', () =>
  createComponentMock('NcActionButton', {
    template:
      '<button class="nc-action-button" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
    props: [],
    emits: ['click'],
  }),
)

// Mock getCurrentUser
const mockCurrentUser = vi.fn()
vi.mock('@nextcloud/auth', () => ({
  getCurrentUser: () => mockCurrentUser(),
}))

// Mock useUserRole
const mockIsAdmin = vi.fn(() => false)
const mockIsModerator = vi.fn(() => false)
vi.mock('@/composables/useUserRole', () => ({
  useUserRole: () => ({
    isAdmin: mockIsAdmin(),
    isModerator: mockIsModerator(),
  }),
}))

describe('PostCard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockCurrentUser.mockReturnValue({ uid: 'testuser', displayName: 'Test User' })
    mockIsAdmin.mockReturnValue(false)
    mockIsModerator.mockReturnValue(false)
  })

  describe('rendering', () => {
    it('should render post content', () => {
      const post = createMockPost({ content: '<p>Hello world</p>' })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      expect(wrapper.find('.content-text').html()).toContain('Hello world')
    })

    it('should render user info with author data', () => {
      const author = createMockUser({ userId: 'john', displayName: 'John Doe' })
      const post = createMockPost({ author })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      expect(wrapper.find('.user-info-mock').attributes('data-user-id')).toBe('john')
    })

    it('should render reactions component', () => {
      const post = createMockPost({ id: 42 })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      expect(wrapper.find('.post-reactions-mock').attributes('data-post-id')).toBe('42')
    })

    it('should render edited badge when post is edited', () => {
      const post = createMockPost({ isEdited: true, editedAt: Date.now() / 1000 })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      expect(wrapper.find('.edited-badge').exists()).toBe(true)
      expect(wrapper.find('.edited-label').text()).toBe('Edited')
    })

    it('should not render edited badge when post is not edited', () => {
      const post = createMockPost({ isEdited: false })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      expect(wrapper.find('.edited-badge').exists()).toBe(false)
    })
  })

  describe('CSS classes', () => {
    it('should apply first-post class when isFirstPost is true', () => {
      const post = createMockPost()
      const wrapper = mount(PostCard, {
        props: { post, isFirstPost: true },
      })
      expect(wrapper.find('.post-card').classes()).toContain('first-post')
    })

    it('should apply unread class when isUnread is true', () => {
      const post = createMockPost()
      const wrapper = mount(PostCard, {
        props: { post, isUnread: true },
      })
      expect(wrapper.find('.post-card').classes()).toContain('unread')
    })

    it('should show unread indicator when isUnread is true', () => {
      const post = createMockPost()
      const wrapper = mount(PostCard, {
        props: { post, isUnread: true },
      })
      expect(wrapper.find('.unread-indicator').exists()).toBe(true)
    })
  })

  describe('signature', () => {
    it('should render signature when author has one', () => {
      const author = createMockUser({ signature: '<p>My signature</p>' })
      const post = createMockPost({ author })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      expect(wrapper.find('.post-signature').exists()).toBe(true)
      expect(wrapper.find('.signature-content').html()).toContain('My signature')
    })

    it('should not render signature when author has none', () => {
      const author = createMockUser({ signature: null })
      const post = createMockPost({ author })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      expect(wrapper.find('.post-signature').exists()).toBe(false)
    })
  })

  describe('action buttons', () => {
    it('should always show reply button', () => {
      const post = createMockPost()
      const wrapper = mount(PostCard, {
        props: { post },
      })
      const buttons = wrapper.findAll('.nc-action-button')
      expect(buttons.some((b) => b.text().includes('Quote reply'))).toBe(true)
    })

    it('should show edit button when user is author', () => {
      mockCurrentUser.mockReturnValue({ uid: 'author123' })
      const post = createMockPost({ authorId: 'author123' })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      const buttons = wrapper.findAll('.nc-action-button')
      expect(buttons.some((b) => b.text().includes('Edit'))).toBe(true)
    })

    it('should show edit button when user is admin', () => {
      mockCurrentUser.mockReturnValue({ uid: 'admin' })
      mockIsAdmin.mockReturnValue(true)
      const post = createMockPost({ authorId: 'someone_else' })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      const buttons = wrapper.findAll('.nc-action-button')
      expect(buttons.some((b) => b.text().includes('Edit'))).toBe(true)
    })

    it('should show edit button when user is moderator', () => {
      mockCurrentUser.mockReturnValue({ uid: 'mod' })
      mockIsModerator.mockReturnValue(true)
      const post = createMockPost({ authorId: 'someone_else' })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      const buttons = wrapper.findAll('.nc-action-button')
      expect(buttons.some((b) => b.text().includes('Edit'))).toBe(true)
    })

    it('should show edit button when user can moderate category', () => {
      mockCurrentUser.mockReturnValue({ uid: 'catmod' })
      const post = createMockPost({ authorId: 'someone_else' })
      const wrapper = mount(PostCard, {
        props: { post, canModerateCategory: true },
      })
      const buttons = wrapper.findAll('.nc-action-button')
      expect(buttons.some((b) => b.text().includes('Edit'))).toBe(true)
    })

    it('should not show edit button when user has no permissions', () => {
      mockCurrentUser.mockReturnValue({ uid: 'random_user' })
      const post = createMockPost({ authorId: 'someone_else' })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      const buttons = wrapper.findAll('.nc-action-button')
      expect(buttons.some((b) => b.text().includes('Edit'))).toBe(false)
    })

    it('should show view history button when post is edited', () => {
      const post = createMockPost({ isEdited: true })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      const buttons = wrapper.findAll('.nc-action-button')
      expect(buttons.some((b) => b.text().includes('View edit history'))).toBe(true)
    })

    it('should not show view history button when post is not edited', () => {
      const post = createMockPost({ isEdited: false })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      const buttons = wrapper.findAll('.nc-action-button')
      expect(buttons.some((b) => b.text().includes('View edit history'))).toBe(false)
    })
  })

  describe('events', () => {
    it('should emit reply event when reply button is clicked', async () => {
      const post = createMockPost()
      const wrapper = mount(PostCard, {
        props: { post },
      })
      const replyButton = wrapper
        .findAll('.nc-action-button')
        .find((b) => b.text().includes('Quote reply'))
      await replyButton?.trigger('click')

      expect(wrapper.emitted('reply')).toBeTruthy()
      expect(wrapper.emitted('reply')![0]).toEqual([post])
    })

    it('should emit delete event when delete is confirmed', async () => {
      const confirmMock = vi.fn(() => true)
      vi.stubGlobal('confirm', confirmMock)

      mockCurrentUser.mockReturnValue({ uid: 'author' })
      const post = createMockPost({ authorId: 'author' })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      const deleteButton = wrapper
        .findAll('.nc-action-button')
        .find((b) => b.text().includes('Delete'))
      await deleteButton?.trigger('click')

      expect(confirmMock).toHaveBeenCalled()
      expect(wrapper.emitted('delete')).toBeTruthy()
      expect(wrapper.emitted('delete')![0]).toEqual([post])

      vi.unstubAllGlobals()
    })

    it('should not emit delete event when delete is cancelled', async () => {
      const confirmMock = vi.fn(() => false)
      vi.stubGlobal('confirm', confirmMock)

      mockCurrentUser.mockReturnValue({ uid: 'author' })
      const post = createMockPost({ authorId: 'author' })
      const wrapper = mount(PostCard, {
        props: { post },
      })
      const deleteButton = wrapper
        .findAll('.nc-action-button')
        .find((b) => b.text().includes('Delete'))
      await deleteButton?.trigger('click')

      expect(confirmMock).toHaveBeenCalled()
      expect(wrapper.emitted('delete')).toBeFalsy()

      vi.unstubAllGlobals()
    })
  })

  describe('edit mode', () => {
    it('should show edit form when edit button is clicked', async () => {
      mockCurrentUser.mockReturnValue({ uid: 'author' })
      const post = createMockPost({ authorId: 'author', contentRaw: 'Raw content' })
      const wrapper = mount(PostCard, {
        props: { post },
      })

      expect(wrapper.find('.post-edit-form-mock').exists()).toBe(false)

      const editButton = wrapper.findAll('.nc-action-button').find((b) => b.text().includes('Edit'))
      await editButton?.trigger('click')

      expect(wrapper.find('.post-edit-form-mock').exists()).toBe(true)
      expect(wrapper.find('.content-text').exists()).toBe(false)
    })

    it('should hide reactions when in edit mode', async () => {
      mockCurrentUser.mockReturnValue({ uid: 'author' })
      const post = createMockPost({ authorId: 'author' })
      const wrapper = mount(PostCard, {
        props: { post },
      })

      expect(wrapper.find('.post-reactions-mock').exists()).toBe(true)

      const editButton = wrapper.findAll('.nc-action-button').find((b) => b.text().includes('Edit'))
      await editButton?.trigger('click')

      expect(wrapper.find('.post-reactions-mock').exists()).toBe(false)
    })

    it('should exit edit mode when cancel is triggered', async () => {
      mockCurrentUser.mockReturnValue({ uid: 'author' })
      const post = createMockPost({ authorId: 'author' })
      const wrapper = mount(PostCard, {
        props: { post },
      })

      const editButton = wrapper.findAll('.nc-action-button').find((b) => b.text().includes('Edit'))
      await editButton?.trigger('click')

      const vm = wrapper.vm as InstanceType<typeof PostCard>
      vm.cancelEdit()
      await wrapper.vm.$nextTick()

      expect(wrapper.find('.post-edit-form-mock').exists()).toBe(false)
      expect(wrapper.find('.content-text').exists()).toBe(true)
    })
  })

  describe('unauthenticated user', () => {
    it('should not show edit or delete buttons when not logged in', () => {
      mockCurrentUser.mockReturnValue(null)
      const post = createMockPost()
      const wrapper = mount(PostCard, {
        props: { post },
      })
      const buttons = wrapper.findAll('.nc-action-button')
      expect(buttons.some((b) => b.text().includes('Edit'))).toBe(false)
      expect(buttons.some((b) => b.text().includes('Delete'))).toBe(false)
    })
  })
})
