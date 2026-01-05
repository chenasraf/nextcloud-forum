import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref, computed } from 'vue'
import { createIconMock, createComponentMock } from '@/test-utils'

// Mock useCurrentUser composable
vi.mock('@/composables/useCurrentUser', () => ({
  useCurrentUser: () => ({
    userId: computed(() => 'testuser'),
    displayName: computed(() => 'Test User'),
  }),
}))

// Mock icons
vi.mock('@icons/Check.vue', () => createIconMock('CheckIcon'))
vi.mock('@icons/ContentSave.vue', () => createIconMock('ContentSaveIcon'))
vi.mock('@icons/ContentSaveCheck.vue', () => createIconMock('ContentSaveCheckIcon'))
vi.mock('@icons/ContentSaveAlert.vue', () => createIconMock('ContentSaveAlertIcon'))

// Mock UserInfo component
vi.mock('@/components/UserInfo', () =>
  createComponentMock('UserInfo', {
    template: '<div class="user-info-mock">{{ displayName }}</div>',
    props: ['userId', 'displayName', 'avatarSize', 'clickable'],
  }),
)

// Mock BBCodeEditor component
vi.mock('@/components/BBCodeEditor', () => ({
  default: {
    name: 'BBCodeEditor',
    template:
      '<textarea class="bbcode-editor-mock" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" :disabled="disabled" />',
    props: ['modelValue', 'placeholder', 'rows', 'disabled', 'minHeight'],
    emits: ['update:modelValue'],
    methods: {
      focus() {},
    },
  },
}))

// Import after mocks
import ThreadCreateForm from './ThreadCreateForm.vue'

describe('ThreadCreateForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal(
      'confirm',
      vi.fn(() => true),
    )
  })

  const createWrapper = (props = {}) => {
    return mount(ThreadCreateForm, {
      props: {
        ...props,
      },
    })
  }

  describe('rendering', () => {
    it('renders the form', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.thread-create-form').exists()).toBe(true)
    })

    it('renders user info header', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.user-info-mock').exists()).toBe(true)
    })

    it('renders title input', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.nc-text-field').exists()).toBe(true)
    })

    it('renders BBCode editor', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.bbcode-editor-mock').exists()).toBe(true)
    })

    it('renders cancel button', () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button')
      expect(buttons.some((b) => b.text() === 'Cancel')).toBe(true)
    })

    it('renders submit button', () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button')
      expect(buttons.some((b) => b.text() === 'Create thread')).toBe(true)
    })
  })

  describe('draft status', () => {
    it('shows saving status', () => {
      const wrapper = createWrapper({ draftStatus: 'saving' })
      expect(wrapper.text()).toContain('Saving draft')
    })

    it('shows saved status', () => {
      const wrapper = createWrapper({ draftStatus: 'saved' })
      expect(wrapper.text()).toContain('Draft saved')
    })

    it('shows dirty status', () => {
      const wrapper = createWrapper({ draftStatus: 'dirty' })
      expect(wrapper.text()).toContain('Unsaved changes')
    })

    it('hides status when null', () => {
      const wrapper = createWrapper({ draftStatus: null })
      expect(wrapper.text()).not.toContain('Saving draft')
      expect(wrapper.text()).not.toContain('Draft saved')
      expect(wrapper.text()).not.toContain('Unsaved changes')
    })

    it('displays saving icon for saving status', () => {
      const wrapper = createWrapper({ draftStatus: 'saving' })
      expect(wrapper.find('.content-save-icon').exists()).toBe(true)
    })

    it('displays saved icon for saved status', () => {
      const wrapper = createWrapper({ draftStatus: 'saved' })
      expect(wrapper.find('.content-save-check-icon').exists()).toBe(true)
    })

    it('displays dirty icon for dirty status', () => {
      const wrapper = createWrapper({ draftStatus: 'dirty' })
      expect(wrapper.find('.content-save-alert-icon').exists()).toBe(true)
    })
  })

  describe('validation', () => {
    it('disables submit when title is empty', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { canSubmit: boolean }
      expect(vm.canSubmit).toBe(false)
    })

    it('disables submit when content is empty', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { title: string; canSubmit: boolean }
      vm.title = 'Test Title'

      await flushPromises()

      expect(vm.canSubmit).toBe(false)
    })

    it('enables submit when both title and content have values', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { title: string; content: string; canSubmit: boolean }
      vm.title = 'Test Title'
      vm.content = 'Test Content'

      await flushPromises()

      expect(vm.canSubmit).toBe(true)
    })

    it('disables submit when title is only whitespace', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { title: string; content: string; canSubmit: boolean }
      vm.title = '   '
      vm.content = 'Test Content'

      await flushPromises()

      expect(vm.canSubmit).toBe(false)
    })
  })

  describe('submitting', () => {
    it('emits submit event with trimmed data', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as {
        title: string
        content: string
        submitThread: () => Promise<void>
      }
      vm.title = '  Test Title  '
      vm.content = '  Test Content  '

      await vm.submitThread()

      expect(wrapper.emitted('submit')).toBeTruthy()
      expect(wrapper.emitted('submit')![0]).toEqual([
        { title: 'Test Title', content: 'Test Content' },
      ])
    })

    it('sets submitting state to true', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as {
        title: string
        content: string
        submitting: boolean
        submitThread: () => Promise<void>
      }
      vm.title = 'Test Title'
      vm.content = 'Test Content'

      await vm.submitThread()

      expect(vm.submitting).toBe(true)
    })

    it('does not submit when already submitting', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as {
        title: string
        content: string
        submitting: boolean
        submitThread: () => Promise<void>
      }
      vm.title = 'Test Title'
      vm.content = 'Test Content'
      vm.submitting = true

      await vm.submitThread()

      expect(wrapper.emitted('submit')).toBeFalsy()
    })

    it('does not submit when validation fails', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as {
        title: string
        content: string
        submitThread: () => Promise<void>
      }
      vm.title = ''
      vm.content = ''

      await vm.submitThread()

      expect(wrapper.emitted('submit')).toBeFalsy()
    })
  })

  describe('canceling', () => {
    it('emits cancel event when cancel is clicked without content', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { cancel: () => void }
      vm.cancel()

      expect(wrapper.emitted('cancel')).toBeTruthy()
    })

    it('shows confirmation when content exists', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { title: string; content: string; cancel: () => void }
      vm.title = 'Test Title'
      vm.content = 'Test Content'

      vm.cancel()

      expect(window.confirm).toHaveBeenCalled()
    })

    it('emits cancel event when confirmed', async () => {
      vi.stubGlobal(
        'confirm',
        vi.fn(() => true),
      )

      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { title: string; content: string; cancel: () => void }
      vm.title = 'Test Title'
      vm.content = 'Test Content'

      vm.cancel()

      expect(wrapper.emitted('cancel')).toBeTruthy()
    })

    it('does not emit cancel event when not confirmed', async () => {
      vi.stubGlobal(
        'confirm',
        vi.fn(() => false),
      )

      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { title: string; content: string; cancel: () => void }
      vm.title = 'Test Title'
      vm.content = 'Test Content'

      vm.cancel()

      expect(wrapper.emitted('cancel')).toBeFalsy()
    })

    it('clears title and content on cancel', async () => {
      vi.stubGlobal(
        'confirm',
        vi.fn(() => true),
      )

      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as {
        title: string
        content: string
        cancel: () => void
      }
      vm.title = 'Test Title'
      vm.content = 'Test Content'

      vm.cancel()

      expect(vm.title).toBe('')
      expect(vm.content).toBe('')
    })
  })

  describe('update events', () => {
    it('emits update:title when title changes', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { title: string }
      vm.title = 'New Title'

      await flushPromises()

      expect(wrapper.emitted('update:title')).toBeTruthy()
      expect(wrapper.emitted('update:title')![0]).toEqual(['New Title'])
    })

    it('emits update:content when content changes', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { content: string }
      vm.content = 'New Content'

      await flushPromises()

      expect(wrapper.emitted('update:content')).toBeTruthy()
      expect(wrapper.emitted('update:content')![0]).toEqual(['New Content'])
    })
  })

  describe('clear method', () => {
    it('resets title, content, and submitting state', () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as {
        title: string
        content: string
        submitting: boolean
        clear: () => void
      }
      vm.title = 'Test Title'
      vm.content = 'Test Content'
      vm.submitting = true

      vm.clear()

      expect(vm.title).toBe('')
      expect(vm.content).toBe('')
      expect(vm.submitting).toBe(false)
    })
  })

  describe('setSubmitting method', () => {
    it('sets submitting state', () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as {
        submitting: boolean
        setSubmitting: (value: boolean) => void
      }

      vm.setSubmitting(true)
      expect(vm.submitting).toBe(true)

      vm.setSubmitting(false)
      expect(vm.submitting).toBe(false)
    })
  })

  describe('setTitle method', () => {
    it('sets title value', () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as {
        title: string
        setTitle: (value: string) => void
      }

      vm.setTitle('New Title')
      expect(vm.title).toBe('New Title')
    })
  })

  describe('setContent method', () => {
    it('sets content value', () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as {
        content: string
        setContent: (value: string) => void
      }

      vm.setContent('New Content')
      expect(vm.content).toBe('New Content')
    })
  })

  describe('hasContent computed', () => {
    it('returns false when both title and content are empty', () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { hasContent: boolean }
      expect(vm.hasContent).toBe(false)
    })

    it('returns true when title has content', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { title: string; hasContent: boolean }
      vm.title = 'Test'

      await flushPromises()

      expect(vm.hasContent).toBe(true)
    })

    it('returns true when content has content', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { content: string; hasContent: boolean }
      vm.content = 'Test'

      await flushPromises()

      expect(vm.hasContent).toBe(true)
    })

    it('returns false when title and content are only whitespace', () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { title: string; content: string; hasContent: boolean }
      vm.title = '   '
      vm.content = '   '

      expect(vm.hasContent).toBe(false)
    })
  })

  describe('disabled state', () => {
    it('disables inputs when submitting', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as {
        title: string
        content: string
        submitting: boolean
      }
      vm.title = 'Test'
      vm.content = 'Test'
      vm.submitting = true

      await flushPromises()

      const titleInput = wrapper.find('.nc-text-field')
      expect(titleInput.attributes('disabled')).toBeDefined()
    })

    it('disables cancel button when submitting', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as { submitting: boolean }
      vm.submitting = true

      await flushPromises()

      const cancelButton = wrapper.findAll('button').find((b) => b.text() === 'Cancel')
      expect(cancelButton!.attributes('disabled')).toBeDefined()
    })

    it('disables submit button when submitting', async () => {
      const wrapper = createWrapper()

      const vm = wrapper.vm as unknown as {
        title: string
        content: string
        submitting: boolean
      }
      vm.title = 'Test'
      vm.content = 'Test'
      vm.submitting = true

      await flushPromises()

      const submitButton = wrapper.findAll('button').find((b) => b.text() === 'Create thread')
      expect(submitButton!.attributes('disabled')).toBeDefined()
    })
  })
})
