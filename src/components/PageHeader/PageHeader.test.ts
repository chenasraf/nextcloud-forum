import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createComponentMock } from '@/test-utils'
import PageHeader from './PageHeader.vue'

vi.mock('@/components/Skeleton', () =>
  createComponentMock('Skeleton', {
    template: '<div class="skeleton-mock" :style="{ width, height }"></div>',
    props: ['width', 'height', 'radius'],
  }),
)

describe('PageHeader', () => {
  describe('rendering', () => {
    it('should render title', () => {
      const wrapper = mount(PageHeader, {
        props: { title: 'Test Title' },
      })
      expect(wrapper.find('.page-title').text()).toBe('Test Title')
    })

    it('should render subtitle when provided', () => {
      const wrapper = mount(PageHeader, {
        props: { title: 'Title', subtitle: 'Subtitle text' },
      })
      expect(wrapper.find('.page-subtitle').exists()).toBe(true)
      expect(wrapper.find('.page-subtitle').text()).toBe('Subtitle text')
    })

    it('should not render subtitle when not provided', () => {
      const wrapper = mount(PageHeader, {
        props: { title: 'Title' },
      })
      expect(wrapper.find('.page-subtitle').exists()).toBe(false)
    })

    it('should not render subtitle when empty string', () => {
      const wrapper = mount(PageHeader, {
        props: { title: 'Title', subtitle: '' },
      })
      expect(wrapper.find('.page-subtitle').exists()).toBe(false)
    })
  })

  describe('loading state', () => {
    it('should show skeleton loaders when loading', () => {
      const wrapper = mount(PageHeader, {
        props: { title: 'Title', loading: true },
      })
      expect(wrapper.findAll('.skeleton-mock').length).toBe(2)
      expect(wrapper.find('.page-title').exists()).toBe(false)
    })

    it('should show content when not loading', () => {
      const wrapper = mount(PageHeader, {
        props: { title: 'Title', loading: false },
      })
      expect(wrapper.find('.skeleton-mock').exists()).toBe(false)
      expect(wrapper.find('.page-title').exists()).toBe(true)
    })
  })

  describe('default props', () => {
    it('should have empty title by default', () => {
      const wrapper = mount(PageHeader)
      expect(wrapper.find('.page-title').text()).toBe('')
    })

    it('should not be loading by default', () => {
      const wrapper = mount(PageHeader, {
        props: { title: 'Test' },
      })
      expect(wrapper.find('.page-title').exists()).toBe(true)
    })
  })
})
