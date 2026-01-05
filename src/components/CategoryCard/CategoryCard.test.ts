import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CategoryCard from './CategoryCard.vue'
import { createMockCategory } from '@/test-mocks'

// Uses global mock for @nextcloud/l10n from test-setup.ts

describe('CategoryCard', () => {
  describe('rendering', () => {
    it('should render category name', () => {
      const category = createMockCategory({ name: 'General Discussion' })
      const wrapper = mount(CategoryCard, {
        props: { category },
      })
      expect(wrapper.find('.category-name').text()).toBe('General Discussion')
    })

    it('should render category description', () => {
      const category = createMockCategory({ description: 'Talk about anything' })
      const wrapper = mount(CategoryCard, {
        props: { category },
      })
      expect(wrapper.find('.category-description').text()).toBe('Talk about anything')
    })

    it('should render placeholder when no description', () => {
      const category = createMockCategory({ description: null })
      const wrapper = mount(CategoryCard, {
        props: { category },
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
      })
      const stats = wrapper.findAll('.stat-value')
      expect(stats[0]!.text()).toBe('25')
    })

    it('should display post count', () => {
      const category = createMockCategory({ postCount: 150 })
      const wrapper = mount(CategoryCard, {
        props: { category },
      })
      const stats = wrapper.findAll('.stat-value')
      expect(stats[1]!.text()).toBe('150')
    })

    it('should handle zero counts', () => {
      const category = createMockCategory({ threadCount: 0, postCount: 0 })
      const wrapper = mount(CategoryCard, {
        props: { category },
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
      })
      const stats = wrapper.findAll('.stat-value')
      expect(stats[0]!.text()).toBe('0')
      expect(stats[1]!.text()).toBe('0')
    })
  })

  describe('structure', () => {
    it('should have correct class', () => {
      const wrapper = mount(CategoryCard, {
        props: { category: createMockCategory() },
      })
      expect(wrapper.find('.category-card').exists()).toBe(true)
    })

    it('should have header with name and stats', () => {
      const wrapper = mount(CategoryCard, {
        props: { category: createMockCategory() },
      })
      expect(wrapper.find('.category-header').exists()).toBe(true)
      expect(wrapper.find('.category-name').exists()).toBe(true)
      expect(wrapper.find('.category-stats').exists()).toBe(true)
    })
  })
})
