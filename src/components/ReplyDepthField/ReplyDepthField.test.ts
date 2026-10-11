import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createComponentMock } from '@/test-utils'
import ReplyDepthField from './ReplyDepthField.vue'

vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () =>
  createComponentMock('NcCheckboxRadioSwitch', {
    template: '<div class="nc-switch-mock"><slot /></div>',
    props: ['modelValue', 'type'],
    emits: ['update:modelValue'],
  }),
)
vi.mock('@nextcloud/vue/components/NcTextField', () =>
  createComponentMock('NcTextField', {
    template: '<input class="nc-text-field-mock" />',
    props: ['modelValue', 'type', 'min', 'max'],
    emits: ['update:modelValue'],
  }),
)

function mountField(modelValue: number) {
  return mount(ReplyDepthField, { props: { modelValue, inputId: 'depth' } })
}

function toggle(wrapper: ReturnType<typeof mountField>) {
  return wrapper.findComponent({ name: 'NcCheckboxRadioSwitch' })
}

function depthInput(wrapper: ReturnType<typeof mountField>) {
  return wrapper.findComponent({ name: 'NcTextField' })
}

describe('ReplyDepthField', () => {
  it('shows the depth when nested replies are enabled', () => {
    const wrapper = mountField(3)

    expect(toggle(wrapper).props('modelValue')).toBe(true)
    expect(depthInput(wrapper).props('modelValue')).toBe('3')
  })

  it('hides the depth when nested replies are disabled', () => {
    const wrapper = mountField(0)

    expect(toggle(wrapper).props('modelValue')).toBe(false)
    expect(depthInput(wrapper).exists()).toBe(false)
  })

  it('emits 0 when nested replies are turned off', () => {
    const wrapper = mountField(3)

    toggle(wrapper).vm.$emit('update:modelValue', false)

    expect(wrapper.emitted('update:modelValue')).toEqual([[0]])
  })

  it('restores the last depth when nested replies are turned back on', async () => {
    const wrapper = mountField(3)
    await wrapper.setProps({ modelValue: 0 })

    toggle(wrapper).vm.$emit('update:modelValue', true)

    expect(wrapper.emitted('update:modelValue')).toEqual([[3]])
  })

  it('uses the default depth when turning on from disabled', () => {
    const wrapper = mountField(0)

    toggle(wrapper).vm.$emit('update:modelValue', true)

    expect(wrapper.emitted('update:modelValue')).toEqual([[5]])
  })

  it('keeps the depth between 1 and 10 and ignores non-numbers', () => {
    const wrapper = mountField(3)

    depthInput(wrapper).vm.$emit('update:modelValue', '0')
    depthInput(wrapper).vm.$emit('update:modelValue', '42')
    depthInput(wrapper).vm.$emit('update:modelValue', '7')
    depthInput(wrapper).vm.$emit('update:modelValue', '')

    expect(wrapper.emitted('update:modelValue')).toEqual([[1], [10], [7]])
  })
})
