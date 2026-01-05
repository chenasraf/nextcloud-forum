import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createComponentMock } from '@/test-utils'
import PostReactions from './PostReactions.vue'
import type { ReactionGroup } from '@/composables/useReactions'

// Mock LazyEmojiPicker
vi.mock('@/components/LazyEmojiPicker', () =>
  createComponentMock('LazyEmojiPicker', {
    template: '<div class="emoji-picker-mock"><slot /></div>',
    props: [],
  }),
)

// Mock useReactions composable
const mockToggleReaction = vi.fn()
vi.mock('@/composables/useReactions', () => ({
  useReactions: () => ({
    toggleReaction: mockToggleReaction,
  }),
}))

// Mock getCurrentUser
vi.mock('@nextcloud/auth', () => ({
  getCurrentUser: () => ({ uid: 'testuser', displayName: 'Test User' }),
}))

describe('PostReactions', () => {
  beforeEach(() => {
    mockToggleReaction.mockReset()
  })

  const defaultEmojis = ['👍', '❤️', '😄', '🎉', '👏']

  describe('rendering', () => {
    it('should render default emojis', () => {
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions: [] },
      })
      const buttons = wrapper.findAll('.reaction-button')
      expect(buttons.length).toBe(defaultEmojis.length)
      defaultEmojis.forEach((emoji) => {
        expect(wrapper.text()).toContain(emoji)
      })
    })

    it('should render add reaction button', () => {
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions: [] },
      })
      expect(wrapper.find('.add-reaction-button').exists()).toBe(true)
    })

    it('should display reaction counts when present', () => {
      const reactions: ReactionGroup[] = [
        { emoji: '👍', count: 5, hasReacted: false, userIds: ['user1', 'user2'] },
      ]
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions },
      })
      expect(wrapper.find('.count').text()).toBe('5')
    })

    it('should not display count when zero', () => {
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions: [] },
      })
      const thumbsUpButton = wrapper.findAll('.reaction-button')[0]!
      expect(thumbsUpButton.find('.count').exists()).toBe(false)
    })
  })

  describe('CSS classes', () => {
    it('should apply reacted class when user has reacted', () => {
      const reactions: ReactionGroup[] = [
        { emoji: '👍', count: 1, hasReacted: true, userIds: ['testuser'] },
      ]
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions },
      })
      const thumbsUpButton = wrapper.findAll('.reaction-button')[0]!
      expect(thumbsUpButton.classes()).toContain('reacted')
    })

    it('should not apply reacted class when user has not reacted', () => {
      const reactions: ReactionGroup[] = [
        { emoji: '👍', count: 1, hasReacted: false, userIds: ['otheruser'] },
      ]
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions },
      })
      const thumbsUpButton = wrapper.findAll('.reaction-button')[0]!
      expect(thumbsUpButton.classes()).not.toContain('reacted')
    })

    it('should apply has-count class when count is greater than zero', () => {
      const reactions: ReactionGroup[] = [
        { emoji: '👍', count: 3, hasReacted: false, userIds: ['user1'] },
      ]
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions },
      })
      const thumbsUpButton = wrapper.findAll('.reaction-button')[0]!
      expect(thumbsUpButton.classes()).toContain('has-count')
    })
  })

  describe('sorting', () => {
    it('should sort emojis by count (highest first)', () => {
      const reactions: ReactionGroup[] = [
        { emoji: '👍', count: 2, hasReacted: false, userIds: [] },
        { emoji: '❤️', count: 10, hasReacted: false, userIds: [] },
        { emoji: '😄', count: 5, hasReacted: false, userIds: [] },
      ]
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions },
      })
      const buttons = wrapper.findAll('.reaction-button')
      const emojis = buttons.map((b) => b.find('.emoji').text())
      // ❤️ (10) should be first, then 😄 (5), then 👍 (2)
      expect(emojis[0]).toBe('❤️')
      expect(emojis[1]).toBe('😄')
      expect(emojis[2]).toBe('👍')
    })

    it('should preserve default order for equal counts', () => {
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions: [] },
      })
      const buttons = wrapper.findAll('.reaction-button')
      const emojis = buttons.map((b) => b.find('.emoji').text())
      expect(emojis).toEqual(defaultEmojis)
    })

    it('should show custom emojis with reactions', () => {
      const reactions: ReactionGroup[] = [{ emoji: '🚀', count: 3, hasReacted: false, userIds: [] }]
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions },
      })
      expect(wrapper.text()).toContain('🚀')
    })
  })

  describe('tooltips', () => {
    it('should show "React with" tooltip for zero reactions', () => {
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions: [] },
      })
      const thumbsUpButton = wrapper.findAll('.reaction-button')[0]!
      expect(thumbsUpButton.attributes('title')).toBe('React with 👍')
    })

    it('should show "You reacted" tooltip when user is sole reactor', () => {
      const reactions: ReactionGroup[] = [
        { emoji: '👍', count: 1, hasReacted: true, userIds: ['testuser'] },
      ]
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions },
      })
      const thumbsUpButton = wrapper.findAll('.reaction-button')[0]!
      expect(thumbsUpButton.attributes('title')).toBe('You reacted with 👍')
    })

    it('should show count tooltip when user has not reacted', () => {
      const reactions: ReactionGroup[] = [
        { emoji: '👍', count: 3, hasReacted: false, userIds: ['a', 'b', 'c'] },
      ]
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions },
      })
      const thumbsUpButton = wrapper.findAll('.reaction-button')[0]!
      expect(thumbsUpButton.attributes('title')).toBe('3 people reacted with 👍')
    })
  })

  describe('toggle reaction', () => {
    it('should call toggleReaction when clicking a reaction button', async () => {
      mockToggleReaction.mockResolvedValue({ action: 'added' })
      const wrapper = mount(PostReactions, {
        props: { postId: 42, reactions: [] },
      })
      const thumbsUpButton = wrapper.findAll('.reaction-button')[0]!
      await thumbsUpButton.trigger('click')
      expect(mockToggleReaction).toHaveBeenCalledWith(42, '👍')
    })

    it('should emit update event after toggling reaction', async () => {
      mockToggleReaction.mockResolvedValue({ action: 'added' })
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions: [] },
      })
      const thumbsUpButton = wrapper.findAll('.reaction-button')[0]!
      await thumbsUpButton.trigger('click')
      expect(wrapper.emitted('update')).toBeTruthy()
    })

    it('should update local state when adding reaction', async () => {
      mockToggleReaction.mockResolvedValue({ action: 'added' })
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions: [] },
      })
      const thumbsUpButton = wrapper.findAll('.reaction-button')[0]!
      await thumbsUpButton.trigger('click')

      // Wait for async update
      await wrapper.vm.$nextTick()

      // Check that the button now shows as reacted
      expect(thumbsUpButton.classes()).toContain('reacted')
      expect(thumbsUpButton.find('.count').text()).toBe('1')
    })

    it('should update local state when removing reaction', async () => {
      mockToggleReaction.mockResolvedValue({ action: 'removed' })
      const reactions: ReactionGroup[] = [
        { emoji: '👍', count: 1, hasReacted: true, userIds: ['testuser'] },
      ]
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions },
      })
      const thumbsUpButton = wrapper.findAll('.reaction-button')[0]!
      await thumbsUpButton.trigger('click')

      await wrapper.vm.$nextTick()

      expect(thumbsUpButton.classes()).not.toContain('reacted')
    })
  })

  describe('props reactivity', () => {
    it('should update when reactions prop changes', async () => {
      const wrapper = mount(PostReactions, {
        props: { postId: 1, reactions: [] },
      })

      // Initially no count
      expect(wrapper.find('.count').exists()).toBe(false)

      // Update reactions
      await wrapper.setProps({
        reactions: [{ emoji: '👍', count: 5, hasReacted: false, userIds: [] }],
      })

      expect(wrapper.find('.count').text()).toBe('5')
    })
  })
})
