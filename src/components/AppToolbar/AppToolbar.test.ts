import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppToolbar from './AppToolbar.vue'

describe('AppToolbar', () => {
  describe('rendering', () => {
    it('should render toolbar container', () => {
      const wrapper = mount(AppToolbar)
      expect(wrapper.find('.app-toolbar').exists()).toBe(true)
    })

    it('should render left and right sections', () => {
      const wrapper = mount(AppToolbar)
      expect(wrapper.find('.toolbar-left').exists()).toBe(true)
      expect(wrapper.find('.toolbar-right').exists()).toBe(true)
    })
  })

  describe('slots', () => {
    it('should render left slot content', () => {
      const wrapper = mount(AppToolbar, {
        slots: {
          left: '<span class="left-content">Left Content</span>',
        },
      })
      expect(wrapper.find('.toolbar-left .left-content').exists()).toBe(true)
      expect(wrapper.find('.toolbar-left').text()).toBe('Left Content')
    })

    it('should render right slot content', () => {
      const wrapper = mount(AppToolbar, {
        slots: {
          right: '<span class="right-content">Right Content</span>',
        },
      })
      expect(wrapper.find('.toolbar-right .right-content').exists()).toBe(true)
      expect(wrapper.find('.toolbar-right').text()).toBe('Right Content')
    })

    it('should render both slots simultaneously', () => {
      const wrapper = mount(AppToolbar, {
        slots: {
          left: '<button>Action</button>',
          right: '<span>Status</span>',
        },
      })
      expect(wrapper.find('.toolbar-left button').exists()).toBe(true)
      expect(wrapper.find('.toolbar-right span').exists()).toBe(true)
    })

    it('should render multiple elements in a slot', () => {
      const wrapper = mount(AppToolbar, {
        slots: {
          left: '<button>One</button><button>Two</button><button>Three</button>',
        },
      })
      const buttons = wrapper.findAll('.toolbar-left button')
      expect(buttons).toHaveLength(3)
    })
  })
})
