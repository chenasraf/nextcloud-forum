import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createComponentMock } from '@/test-utils'
import PostEditForm from './PostEditForm.vue'

// Mock BBCodeEditor
vi.mock('@/components/BBCodeEditor', () =>
  createComponentMock('BBCodeEditor', {
    template: `<div class="bbcode-editor-mock">
      <textarea
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        @input="$emit('update:modelValue', $event.target.value)"
        @keydown="$emit('keydown', $event)"
      />
    </div>`,
    props: ['modelValue', 'placeholder', 'rows', 'disabled', 'minHeight'],
    emits: ['update:modelValue', 'keydown'],
  }),
)

// Mock NcLoadingIcon
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () =>
  createComponentMock('NcLoadingIcon', {
    template: '<span class="loading-icon-mock" />',
    props: ['size'],
  }),
)

describe('PostEditForm', () => {
  const initialContent = 'Original post content'

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('rendering', () => {
    it('should render with initial content', () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const textarea = wrapper.find('textarea')
      expect(textarea.element.value).toBe(initialContent)
    })

    it('should render cancel and save buttons', () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const buttons = wrapper.findAll('button')
      expect(buttons).toHaveLength(2)
      expect(buttons[0]!.text()).toBe('Cancel')
      expect(buttons[1]!.text()).toBe('Save')
    })
  })

  describe('submit button state', () => {
    it('should disable save button when content is unchanged', () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const saveButton = wrapper.findAll('button')[1]!
      expect(saveButton.attributes('disabled')).toBeDefined()
    })

    it('should disable save button when content is empty', async () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const textarea = wrapper.find('textarea')
      await textarea.setValue('')

      const saveButton = wrapper.findAll('button')[1]!
      expect(saveButton.attributes('disabled')).toBeDefined()
    })

    it('should disable save button when content is only whitespace', async () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const textarea = wrapper.find('textarea')
      await textarea.setValue('   ')

      const saveButton = wrapper.findAll('button')[1]!
      expect(saveButton.attributes('disabled')).toBeDefined()
    })

    it('should enable save button when content is changed and not empty', async () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const textarea = wrapper.find('textarea')
      await textarea.setValue('Updated content')

      const saveButton = wrapper.findAll('button')[1]!
      expect(saveButton.attributes('disabled')).toBeUndefined()
    })
  })

  describe('submit', () => {
    it('should emit submit with trimmed content when save is clicked', async () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const textarea = wrapper.find('textarea')
      await textarea.setValue('  New content with spaces  ')

      const saveButton = wrapper.findAll('button')[1]!
      await saveButton.trigger('click')

      expect(wrapper.emitted('submit')).toBeTruthy()
      expect(wrapper.emitted('submit')![0]).toEqual(['New content with spaces'])
    })

    it('should not emit submit when content is unchanged', async () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const saveButton = wrapper.findAll('button')[1]!
      await saveButton.trigger('click')

      expect(wrapper.emitted('submit')).toBeFalsy()
    })
  })

  describe('cancel', () => {
    it('should emit cancel when cancel button is clicked with no changes', async () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const cancelButton = wrapper.findAll('button')[0]!
      await cancelButton.trigger('click')

      expect(wrapper.emitted('cancel')).toBeTruthy()
    })

    it('should show confirmation when canceling with changes', async () => {
      const confirmMock = vi.fn(() => false)
      vi.stubGlobal('confirm', confirmMock)

      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const textarea = wrapper.find('textarea')
      await textarea.setValue('Changed content')

      const cancelButton = wrapper.findAll('button')[0]!
      await cancelButton.trigger('click')

      expect(confirmMock).toHaveBeenCalled()
      expect(wrapper.emitted('cancel')).toBeFalsy()

      vi.unstubAllGlobals()
    })

    it('should emit cancel when confirmation is accepted', async () => {
      const confirmMock = vi.fn(() => true)
      vi.stubGlobal('confirm', confirmMock)

      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const textarea = wrapper.find('textarea')
      await textarea.setValue('Changed content')

      const cancelButton = wrapper.findAll('button')[0]!
      await cancelButton.trigger('click')

      expect(confirmMock).toHaveBeenCalled()
      expect(wrapper.emitted('cancel')).toBeTruthy()

      vi.unstubAllGlobals()
    })
  })

  describe('submitting state', () => {
    it('should disable buttons when submitting', async () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const textarea = wrapper.find('textarea')
      await textarea.setValue('New content')

      // Trigger submit
      const saveButton = wrapper.findAll('button')[1]!
      await saveButton.trigger('click')

      // Both buttons should be disabled
      const buttons = wrapper.findAll('button')
      expect(buttons[0]!.attributes('disabled')).toBeDefined()
      expect(buttons[1]!.attributes('disabled')).toBeDefined()
    })

    it('should disable editor when submitting', async () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const textarea = wrapper.find('textarea')
      await textarea.setValue('New content')

      const saveButton = wrapper.findAll('button')[1]!
      await saveButton.trigger('click')

      expect(wrapper.find('textarea').attributes('disabled')).toBeDefined()
    })

    it('should expose setSubmitting method', async () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })

      const vm = wrapper.vm as InstanceType<typeof PostEditForm>
      vm.setSubmitting(true)
      await wrapper.vm.$nextTick()

      expect(wrapper.find('textarea').attributes('disabled')).toBeDefined()

      vm.setSubmitting(false)
      await wrapper.vm.$nextTick()

      expect(wrapper.find('textarea').attributes('disabled')).toBeUndefined()
    })
  })

  describe('computed properties', () => {
    it('should correctly compute hasChanges', async () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const vm = wrapper.vm as InstanceType<typeof PostEditForm>

      expect(vm.hasChanges).toBe(false)

      const textarea = wrapper.find('textarea')
      await textarea.setValue('Different content')

      expect(vm.hasChanges).toBe(true)
    })

    it('should correctly compute canSubmit', async () => {
      const wrapper = mount(PostEditForm, {
        props: { initialContent },
      })
      const vm = wrapper.vm as InstanceType<typeof PostEditForm>

      // Same content - cannot submit
      expect(vm.canSubmit).toBe(false)

      const textarea = wrapper.find('textarea')

      // Empty content - cannot submit
      await textarea.setValue('')
      expect(vm.canSubmit).toBe(false)

      // Different non-empty content - can submit
      await textarea.setValue('New content')
      expect(vm.canSubmit).toBe(true)
    })
  })
})
