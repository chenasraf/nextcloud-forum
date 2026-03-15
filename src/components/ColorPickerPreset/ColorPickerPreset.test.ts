import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@nextcloud/vue/components/NcColorPicker', () => ({
  default: {
    name: 'NcColorPicker',
    template: '<div class="nc-color-picker-mock"><slot /></div>',
    props: ['modelValue', 'palette', 'advancedFields'],
    emits: ['update:modelValue', 'submit'],
  },
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template: '<button class="nc-button-mock"><slot name="icon" /><slot /></button>',
    props: [],
  },
}))

import ColorPickerPreset from './ColorPickerPreset.vue'

const presets = ['#dc2626', '#2563eb', '#059669', '#7c3aed', '#ea580c']

describe('ColorPickerPreset', () => {
  const createWrapper = (props = {}) => {
    return mount(ColorPickerPreset, {
      props: {
        presets,
        ...props,
      },
    })
  }

  describe('rendering', () => {
    it('renders the color picker', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.nc-color-picker-mock').exists()).toBe(true)
    })

    it('renders label when provided', () => {
      const wrapper = createWrapper({ label: 'Pick a color' })
      expect(wrapper.find('.color-picker-label').text()).toBe('Pick a color')
    })

    it('does not render label when empty', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.color-picker-label').exists()).toBe(false)
    })

    it('passes presets as palette prop to NcColorPicker', () => {
      const wrapper = createWrapper()
      const picker = wrapper.findComponent({ name: 'NcColorPicker' })
      expect(picker.props('palette')).toEqual(presets)
    })

    it('passes value to NcColorPicker', () => {
      const wrapper = createWrapper({ modelValue: '#dc2626' })
      const picker = wrapper.findComponent({ name: 'NcColorPicker' })
      expect(picker.props('modelValue')).toBe('#dc2626')
    })

    it('shows color preview with background when value is set', () => {
      const wrapper = createWrapper({ modelValue: '#dc2626' })
      const preview = wrapper.find('.color-preview')
      expect(preview.attributes('style')).toContain('#dc2626')
      expect(preview.classes('empty')).toBe(false)
    })

    it('shows empty preview when no value', () => {
      const wrapper = createWrapper({ modelValue: null })
      const preview = wrapper.find('.color-preview')
      expect(preview.classes('empty')).toBe(true)
    })
  })

  describe('events', () => {
    it('emits update:modelValue when NcColorPicker emits submit', async () => {
      const wrapper = createWrapper()
      const picker = wrapper.findComponent({ name: 'NcColorPicker' })
      await picker.vm.$emit('submit', '#ff0000')
      expect(wrapper.emitted('update:modelValue')).toEqual([['#ff0000']])
    })
  })
})
