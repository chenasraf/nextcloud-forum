import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RoleBadge from './RoleBadge.vue'
import { createMockRole } from '@/test-mocks'

// Uses global mock for @nextcloud/vue/functions/isDarkTheme from test-setup.ts

describe('RoleBadge', () => {
  describe('rendering', () => {
    it('should display the role name', () => {
      const role = createMockRole({ name: 'Super Admin' })
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      expect(wrapper.text()).toBe('Super Admin')
    })

    it('should apply normal density class by default', () => {
      const role = createMockRole()
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      expect(wrapper.find('.role-badge').classes()).toContain('density-normal')
    })

    it('should apply compact density class when specified', () => {
      const role = createMockRole()
      const wrapper = mount(RoleBadge, {
        props: { role, density: 'compact' },
      })
      expect(wrapper.find('.role-badge').classes()).toContain('density-compact')
    })
  })

  describe('color calculation', () => {
    it('should use colorLight when provided (light theme)', () => {
      const role = createMockRole({ colorLight: '#ff5500' })
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      const style = wrapper.find('.role-badge').attributes('style')
      expect(style).toContain('background-color: #ff5500')
    })

    it('should use fallback color for Admin role (id=1)', () => {
      const role = createMockRole({ id: 1, name: 'Admin', roleType: 'admin' })
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      const style = wrapper.find('.role-badge').attributes('style')
      expect(style).toContain('background-color: #dc2626')
    })

    it('should use fallback color for Moderator role (id=2)', () => {
      const role = createMockRole({ id: 2, name: 'Moderator', roleType: 'moderator' })
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      const style = wrapper.find('.role-badge').attributes('style')
      expect(style).toContain('background-color: #2563eb')
    })

    it('should use fallback color for User role (id=3)', () => {
      const role = createMockRole({ id: 3, name: 'User', roleType: 'default' })
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      const style = wrapper.find('.role-badge').attributes('style')
      expect(style).toContain('background-color: #059669')
    })

    it('should use default fallback for custom roles without colors', () => {
      const role = createMockRole({ id: 999, name: 'Custom' })
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      const style = wrapper.find('.role-badge').attributes('style')
      expect(style).toContain('background-color: #000000')
    })
  })

  describe('text color calculation (contrast)', () => {
    it('should use dark text on light backgrounds', () => {
      const role = createMockRole({ colorLight: '#ffffff' })
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      const style = wrapper.find('.role-badge').attributes('style')
      expect(style).toContain('color: #000000')
    })

    it('should use light text on dark backgrounds', () => {
      const role = createMockRole({ colorLight: '#000000' })
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      const style = wrapper.find('.role-badge').attributes('style')
      expect(style).toContain('color: #ffffff')
    })

    it('should use light text on moderately dark backgrounds', () => {
      const role = createMockRole({ colorLight: '#1e3a5f' })
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      const style = wrapper.find('.role-badge').attributes('style')
      expect(style).toContain('color: #ffffff')
    })

    it('should use dark text on moderately light backgrounds', () => {
      const role = createMockRole({ colorLight: '#ffeb3b' })
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      const style = wrapper.find('.role-badge').attributes('style')
      expect(style).toContain('color: #000000')
    })
  })

  describe('hexToRgb method', () => {
    it('should correctly parse 6-digit hex colors', () => {
      const role = createMockRole({ colorLight: '#ff5500' })
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      const vm = wrapper.vm as unknown as {
        hexToRgb: (hex: string) => { r: number; g: number; b: number } | null
      }
      const result = vm.hexToRgb('#ff5500')
      expect(result).toEqual({ r: 255, g: 85, b: 0 })
    })

    it('should correctly parse 3-digit shorthand hex colors', () => {
      const role = createMockRole({ colorLight: '#f00' })
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      const vm = wrapper.vm as unknown as {
        hexToRgb: (hex: string) => { r: number; g: number; b: number } | null
      }
      const result = vm.hexToRgb('#f00')
      expect(result).toEqual({ r: 255, g: 0, b: 0 })
    })

    it('should handle hex without # prefix', () => {
      const role = createMockRole({ colorLight: '#00ff00' })
      const wrapper = mount(RoleBadge, {
        props: { role },
      })
      const vm = wrapper.vm as unknown as {
        hexToRgb: (hex: string) => { r: number; g: number; b: number } | null
      }
      const result = vm.hexToRgb('00ff00')
      expect(result).toEqual({ r: 0, g: 255, b: 0 })
    })
  })
})
