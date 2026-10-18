import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { RouterLinkStub } from '@/test-utils'
import CategoryCard from './CategoryCard.vue'
import { createMockCategory } from '@/test-mocks'

// Uses global mocks from test-setup.ts

const globalStubs = {
  global: {
    stubs: { 'router-link': RouterLinkStub },
  },
}

describe('CategoryCard', () => {
  describe('rendering', () => {
    it('should render category name', () => {
      const category = createMockCategory({ name: 'General Discussion' })
      const wrapper = mount(CategoryCard, {
        props: { category },
        ...globalStubs,
      })
      expect(wrapper.find('.category-name').text()).toBe('General Discussion')
    })

    it('should render category description', () => {
      const category = createMockCategory({ description: 'Talk about anything' })
      const wrapper = mount(CategoryCard, {
        props: { category },
        ...globalStubs,
      })
      expect(wrapper.find('.category-description').text()).toBe('Talk about anything')
    })

    it('should render placeholder when no description', () => {
      const category = createMockCategory({ description: null })
      const wrapper = mount(CategoryCard, {
        props: { category },
        ...globalStubs,
      })
      expect(wrapper.find('.category-description').text()).toBe('No description available')
      expect(wrapper.find('.category-description').classes()).toContain('muted')
    })
  })

  describe('stats', () => {
    it('should display thread count', () => {
      const category = createMockCategory({ threadCount: 25 })
      const wrapper = mount(CategoryCard, {
        props: { category },
        ...globalStubs,
      })
      const stats = wrapper.findAll('.stat-value')
      expect(stats[0]!.text()).toBe('25')
    })

    it('should display post count', () => {
      const category = createMockCategory({ postCount: 150 })
      const wrapper = mount(CategoryCard, {
        props: { category },
        ...globalStubs,
      })
      const stats = wrapper.findAll('.stat-value')
      expect(stats[1]!.text()).toBe('150')
    })

    it('should handle zero counts', () => {
      const category = createMockCategory({ threadCount: 0, postCount: 0 })
      const wrapper = mount(CategoryCard, {
        props: { category },
        ...globalStubs,
      })
      const stats = wrapper.findAll('.stat-value')
      expect(stats[0]!.text()).toBe('0')
      expect(stats[1]!.text()).toBe('0')
    })

    it('should handle undefined counts as zero', () => {
      const category = createMockCategory()
      // @ts-expect-error Testing undefined case
      category.threadCount = undefined
      // @ts-expect-error Testing undefined case
      category.postCount = undefined
      const wrapper = mount(CategoryCard, {
        props: { category },
        ...globalStubs,
      })
      const stats = wrapper.findAll('.stat-value')
      expect(stats[0]!.text()).toBe('0')
      expect(stats[1]!.text()).toBe('0')
    })
  })

  describe('children', () => {
    it('should not render children section when no children', () => {
      const wrapper = mount(CategoryCard, {
        props: { category: createMockCategory() },
        ...globalStubs,
      })
      expect(wrapper.find('.category-children').exists()).toBe(false)
    })

    it('should not render children section when children is empty', () => {
      const wrapper = mount(CategoryCard, {
        props: { category: createMockCategory(), children: [] },
        ...globalStubs,
      })
      expect(wrapper.find('.category-children').exists()).toBe(false)
    })

    it('should render child links when children provided', () => {
      const children = [
        createMockCategory({ id: 2, name: 'Child 1', slug: 'child-1' }),
        createMockCategory({ id: 3, name: 'Child 2', slug: 'child-2' }),
      ]
      const wrapper = mount(CategoryCard, {
        props: { category: createMockCategory(), children },
        ...globalStubs,
      })
      expect(wrapper.find('.category-children').exists()).toBe(true)
      const links = wrapper.findAll('.child-link')
      expect(links).toHaveLength(2)
      expect(links[0]!.text()).toBe('Child 1')
      expect(links[1]!.text()).toBe('Child 2')
    })

    it('should not render children when hideChildren is true', () => {
      const children = [createMockCategory({ id: 2, name: 'Child 1', slug: 'child-1' })]
      const wrapper = mount(CategoryCard, {
        props: { category: createMockCategory(), children, hideChildren: true },
        ...globalStubs,
      })
      expect(wrapper.find('.category-children').exists()).toBe(false)
    })
  })

  describe('unread state', () => {
    it('should look read when it has no unread threads', () => {
      const wrapper = mount(CategoryCard, {
        props: { category: createMockCategory(), unreadCount: 0, showUnreadCount: true },
        ...globalStubs,
      })
      expect(wrapper.find('.category-card').classes()).not.toContain('unread')
      expect(wrapper.find('.unread-indicator').exists()).toBe(false)
      expect(wrapper.find('.unread-stat').exists()).toBe(false)
    })

    it('should show the labeled unread count beside the description when counts are enabled', () => {
      const wrapper = mount(CategoryCard, {
        props: { category: createMockCategory(), unreadCount: 7, showUnreadCount: true },
        ...globalStubs,
      })
      expect(wrapper.find('.category-card').classes()).toContain('unread')
      const stat = wrapper.find('.category-body .unread-stat')
      expect(stat.find('.stat-value').text()).toBe('7')
      expect(stat.find('.stat-label').text()).toBe('Unread')
      expect(wrapper.find('.unread-indicator').exists()).toBe(false)
    })

    it('should show a dot instead of the count when counts are disabled', () => {
      const wrapper = mount(CategoryCard, {
        props: { category: createMockCategory(), unreadCount: 7, showUnreadCount: false },
        ...globalStubs,
      })
      expect(wrapper.find('.category-card').classes()).toContain('unread')
      expect(wrapper.find('.unread-indicator').exists()).toBe(true)
      expect(wrapper.find('.unread-stat').exists()).toBe(false)
    })
  })

  describe('structure', () => {
    it('should have correct class', () => {
      const wrapper = mount(CategoryCard, {
        props: { category: createMockCategory() },
        ...globalStubs,
      })
      expect(wrapper.find('.category-card').exists()).toBe(true)
    })

    it('should have header with name and stats', () => {
      const wrapper = mount(CategoryCard, {
        props: { category: createMockCategory() },
        ...globalStubs,
      })
      expect(wrapper.find('.category-header').exists()).toBe(true)
      expect(wrapper.find('.category-name').exists()).toBe(true)
      expect(wrapper.find('.category-stats').exists()).toBe(true)
    })
  })
})
