import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockThread } from '@/test-mocks'
import ThreadCard from './ThreadCard.vue'

// Uses global mocks for @nextcloud/l10n, NcDateTime from test-setup.ts

vi.mock('@/components/UserInfo', () =>
  createComponentMock('UserInfo', {
    template: '<div class="user-info-mock"><slot name="meta" /></div>',
    props: ['userId', 'displayName', 'isDeleted', 'avatarSize', 'roles', 'showRoles', 'layout'],
  }),
)
vi.mock('@icons/Pin.vue', () => createIconMock('PinIcon'))
vi.mock('@icons/Lock.vue', () => createIconMock('LockIcon'))
vi.mock('@icons/Comment.vue', () => createIconMock('CommentIcon'))
vi.mock('@icons/Eye.vue', () => createIconMock('EyeIcon'))

describe('ThreadCard', () => {
  describe('rendering', () => {
    it('should render as an article element for screen reader landmark navigation', () => {
      const thread = createMockThread()
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      expect(wrapper.element.tagName).toBe('ARTICLE')
    })

    it('should render thread title', () => {
      const thread = createMockThread({ title: 'My First Thread' })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      expect(wrapper.find('.thread-title').text()).toContain('My First Thread')
    })

    it('should render post count', () => {
      const thread = createMockThread({ postCount: 25 })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      expect(wrapper.find('.thread-stats').text()).toContain('25')
    })

    it('should render view count', () => {
      const thread = createMockThread({ viewCount: 500 })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      expect(wrapper.find('.thread-stats').text()).toContain('500')
    })
  })

  describe('badges', () => {
    it('should show pin icon when thread is pinned', () => {
      const thread = createMockThread({ isPinned: true })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      expect(wrapper.find('.pin-icon').exists()).toBe(true)
      expect(wrapper.find('.badge-pinned').exists()).toBe(true)
    })

    it('should not show pin icon when thread is not pinned', () => {
      const thread = createMockThread({ isPinned: false })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      expect(wrapper.find('.pin-icon').exists()).toBe(false)
    })

    it('should show lock icon when thread is locked', () => {
      const thread = createMockThread({ isLocked: true })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      expect(wrapper.find('.lock-icon').exists()).toBe(true)
      expect(wrapper.find('.badge-locked').exists()).toBe(true)
    })

    it('should not show lock icon when thread is not locked', () => {
      const thread = createMockThread({ isLocked: false })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      expect(wrapper.find('.lock-icon').exists()).toBe(false)
    })
  })

  describe('CSS classes', () => {
    it('should have pinned class when thread is pinned', () => {
      const thread = createMockThread({ isPinned: true })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      expect(wrapper.find('.thread-card').classes()).toContain('pinned')
    })

    it('should have locked class when thread is locked', () => {
      const thread = createMockThread({ isLocked: true })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      expect(wrapper.find('.thread-card').classes()).toContain('locked')
    })

    it('should have unread class when isUnread prop is true', () => {
      const thread = createMockThread()
      const wrapper = mount(ThreadCard, {
        props: { thread, isUnread: true },
      })
      expect(wrapper.find('.thread-card').classes()).toContain('unread')
    })

    it('should show unread indicator when isUnread is true', () => {
      const thread = createMockThread()
      const wrapper = mount(ThreadCard, {
        props: { thread, isUnread: true },
      })
      expect(wrapper.find('.unread-indicator').exists()).toBe(true)
    })

    it('should not show unread indicator by default', () => {
      const thread = createMockThread()
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      expect(wrapper.find('.unread-indicator').exists()).toBe(false)
    })
  })

  describe('stats handling', () => {
    it('should handle zero counts', () => {
      const thread = createMockThread({ postCount: 0, viewCount: 0 })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      const statValues = wrapper.findAll('.stat-value')
      expect(statValues[0]!.text()).toBe('0')
      expect(statValues[1]!.text()).toBe('0')
    })
  })

  describe('last reply', () => {
    it('should show last reply info when thread has a last reply', () => {
      const thread = createMockThread({
        lastPostId: 42,
        lastReplyAuthorId: 'alice',
        lastReplyAt: 1700000000,
        lastReply: {
          postId: 42,
          author: {
            userId: 'alice',
            displayName: 'Alice',
            isDeleted: false,
            roles: [],
            signature: null,
            signatureRaw: null,
          },
          createdAt: 1700000000,
        },
      })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      const lastReply = wrapper.find('.last-reply')
      expect(lastReply.exists()).toBe(true)
      expect(lastReply.text()).toContain('Alice')
    })

    it('should not show last reply when thread has no last reply', () => {
      const thread = createMockThread({ lastReply: undefined })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      expect(wrapper.find('.last-reply').exists()).toBe(false)
    })

    it('should emit navigate-last-reply when last reply link is clicked', async () => {
      const thread = createMockThread({
        lastPostId: 42,
        lastReplyAuthorId: 'alice',
        lastReplyAt: 1700000000,
        lastReply: {
          postId: 42,
          author: {
            userId: 'alice',
            displayName: 'Alice',
            isDeleted: false,
            roles: [],
            signature: null,
            signatureRaw: null,
          },
          createdAt: 1700000000,
        },
      })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      await wrapper.find('.last-reply').trigger('click')
      expect(wrapper.emitted('navigate-last-reply')).toBeTruthy()
    })

    it('should fall back to lastReplyAuthorId when author displayName is unavailable', () => {
      const thread = createMockThread({
        lastPostId: 42,
        lastReplyAuthorId: 'bob',
        lastReplyAt: 1700000000,
        lastReply: {
          postId: 42,
          author: null,
          createdAt: 1700000000,
        },
      })
      const wrapper = mount(ThreadCard, {
        props: { thread },
      })
      expect(wrapper.find('.last-reply').text()).toContain('bob')
    })
  })
})
