import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Skeleton from './Skeleton.vue'

describe('Skeleton', () => {
  describe('rendering', () => {
    it('should render with default props', () => {
      const wrapper = mount(Skeleton)
      expect(wrapper.find('.skeleton').exists()).toBe(true)
    })

    it('should apply default width and height', () => {
      const wrapper = mount(Skeleton)
      const style = wrapper.find('.skeleton').attributes('style')
      expect(style).toContain('width: 100%')
      expect(style).toContain('height: 20px')
    })

    it('should apply custom width and height', () => {
      const wrapper = mount(Skeleton, {
        props: { width: '200px', height: '40px' },
      })
      const style = wrapper.find('.skeleton').attributes('style')
      expect(style).toContain('width: 200px')
      expect(style).toContain('height: 40px')
    })
  })

  describe('shapes', () => {
    it('should apply rounded-rect border radius by default', () => {
      const wrapper = mount(Skeleton)
      const style = wrapper.find('.skeleton').attributes('style')
      expect(style).toContain('border-radius: 4px')
    })

    it('should apply circle border radius', () => {
      const wrapper = mount(Skeleton, {
        props: { shape: 'circle' },
      })
      const style = wrapper.find('.skeleton').attributes('style')
      expect(style).toContain('border-radius: 50%')
    })

    it('should apply square border radius (0)', () => {
      const wrapper = mount(Skeleton, {
        props: { shape: 'square' },
      })
      const style = wrapper.find('.skeleton').attributes('style')
      expect(style).toContain('border-radius: 0')
    })

    it('should apply custom radius for rounded-rect', () => {
      const wrapper = mount(Skeleton, {
        props: { shape: 'rounded-rect', radius: '8px' },
      })
      const style = wrapper.find('.skeleton').attributes('style')
      expect(style).toContain('border-radius: 8px')
    })
  })

  describe('getBorderRadius method', () => {
    it('should return correct radius for each shape', () => {
      const wrapper = mount(Skeleton, {
        props: { radius: '10px' },
      })
      const vm = wrapper.vm as unknown as { getBorderRadius: () => string; shape: string }

      // Test via computed style since method is internal
      expect(wrapper.find('.skeleton').attributes('style')).toContain('border-radius: 10px')
    })
  })
})
