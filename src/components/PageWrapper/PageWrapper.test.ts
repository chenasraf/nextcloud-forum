import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PageWrapper from './PageWrapper.vue'

describe('PageWrapper', () => {
  describe('rendering', () => {
    it('should render default slot content', () => {
      const wrapper = mount(PageWrapper, {
        slots: {
          default: '<div class="test-content">Content</div>',
        },
      })
      expect(wrapper.find('.test-content').exists()).toBe(true)
      expect(wrapper.find('.test-content').text()).toBe('Content')
    })

    it('should render toolbar slot when provided', () => {
      const wrapper = mount(PageWrapper, {
        slots: {
          toolbar: '<div class="test-toolbar">Toolbar</div>',
          default: '<div>Content</div>',
        },
      })
      expect(wrapper.find('.page-wrapper-toolbar').exists()).toBe(true)
      expect(wrapper.find('.test-toolbar').exists()).toBe(true)
    })

    it('should not render toolbar wrapper when slot is empty', () => {
      const wrapper = mount(PageWrapper, {
        slots: {
          default: '<div>Content</div>',
        },
      })
      expect(wrapper.find('.page-wrapper-toolbar').exists()).toBe(false)
    })
  })

  describe('fullWidth prop', () => {
    it('should not have full-width class by default', () => {
      const wrapper = mount(PageWrapper, {
        slots: { default: '<div>Content</div>' },
      })
      expect(wrapper.find('.page-wrapper-content').classes()).not.toContain('full-width')
    })

    it('should have full-width class when fullWidth is true', () => {
      const wrapper = mount(PageWrapper, {
        props: { fullWidth: true },
        slots: { default: '<div>Content</div>' },
      })
      expect(wrapper.find('.page-wrapper-content').classes()).toContain('full-width')
    })
  })

  describe('structure', () => {
    it('should have correct container structure', () => {
      const wrapper = mount(PageWrapper, {
        slots: {
          toolbar: '<div>Toolbar</div>',
          default: '<div>Content</div>',
        },
      })
      expect(wrapper.find('.page-wrapper-container').exists()).toBe(true)
      expect(wrapper.find('.page-wrapper-toolbar').exists()).toBe(true)
      expect(wrapper.find('.page-wrapper-content').exists()).toBe(true)
    })
  })
})
