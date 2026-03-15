import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FormSection from './FormSection.vue'

describe('FormSection', () => {
  describe('rendering', () => {
    it('should render the title', () => {
      const wrapper = mount(FormSection, {
        props: { title: 'Test Title' },
      })
      expect(wrapper.find('h3').text()).toBe('Test Title')
    })

    it('should render subtitle when provided', () => {
      const wrapper = mount(FormSection, {
        props: { title: 'Title', subtitle: 'Subtitle text' },
      })
      expect(wrapper.find('p.muted').exists()).toBe(true)
      expect(wrapper.find('p.muted').text()).toBe('Subtitle text')
    })

    it('should not render subtitle when not provided', () => {
      const wrapper = mount(FormSection, {
        props: { title: 'Title' },
      })
      expect(wrapper.find('p.muted').exists()).toBe(false)
    })

    it('should not render subtitle when empty string', () => {
      const wrapper = mount(FormSection, {
        props: { title: 'Title', subtitle: '' },
      })
      expect(wrapper.find('p.muted').exists()).toBe(false)
    })
  })

  describe('slots', () => {
    it('should render default slot content', () => {
      const wrapper = mount(FormSection, {
        props: { title: 'Title' },
        slots: {
          default: '<div class="slot-content">Slot content</div>',
        },
      })
      expect(wrapper.find('.slot-content').exists()).toBe(true)
      expect(wrapper.find('.slot-content').text()).toBe('Slot content')
    })

    it('should render without slot content', () => {
      const wrapper = mount(FormSection, {
        props: { title: 'Title' },
      })
      expect(wrapper.find('.form-section').exists()).toBe(true)
    })
  })

  describe('structure', () => {
    it('should have the form-section class on root element', () => {
      const wrapper = mount(FormSection, {
        props: { title: 'Title' },
      })
      expect(wrapper.classes()).toContain('form-section')
    })

    it('should render title before subtitle', () => {
      const wrapper = mount(FormSection, {
        props: { title: 'Title', subtitle: 'Subtitle' },
      })
      const children = wrapper.find('.form-section').element.children
      expect(children[0].tagName).toBe('H3')
      expect(children[1].tagName).toBe('P')
    })
  })
})
