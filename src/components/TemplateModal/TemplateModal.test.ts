import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockTemplate } from '@/test-mocks'
import type { Template } from '@/types'

// Mock icons
vi.mock('@icons/Plus.vue', () => createIconMock('PlusIcon'))
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))
vi.mock('@icons/TextBox.vue', () => createIconMock('TextBoxIcon'))
vi.mock('@icons/ArrowDown.vue', () => createIconMock('ArrowDownIcon'))

// Mock BBCodeEditor — must include __esModule and __isTeleport for async component compat
vi.mock('@/components/BBCodeEditor', () => ({
  __esModule: true,
  __isTeleport: false,
  default: {
    name: 'BBCodeEditor',
    template:
      '<textarea class="bbcode-editor-mock" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'placeholder', 'rows', 'disabled', 'minHeight', 'editorContext'],
    emits: ['update:modelValue'],
  },
}))

// Mock NcCheckboxRadioSwitch
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      '<label class="nc-checkbox-radio-switch"><input type="radio" :value="value" :checked="modelValue === value" @change="$emit(\'update:modelValue\', value)" /><slot /></label>',
    props: ['modelValue', 'value', 'name', 'type'],
    emits: ['update:modelValue'],
  },
}))

// Mock axios
vi.mock('@/axios', () => ({
  ocs: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

// Import after mocks
import { ocs } from '@/axios'
import TemplateModal from './TemplateModal.vue'

const mockGet = vi.mocked(ocs.get)
const mockPost = vi.mocked(ocs.post)
const mockPut = vi.mocked(ocs.put)
const mockDelete = vi.mocked(ocs.delete)

describe('TemplateModal', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGet.mockResolvedValue({ data: [] } as never)
    vi.stubGlobal(
      'confirm',
      vi.fn(() => true),
    )
  })

  const createWrapper = (props = {}) => {
    return mount(TemplateModal, {
      props: {
        open: true,
        editorContext: null,
        ...props,
      },
    })
  }

  describe('rendering', () => {
    it('renders the dialog when open', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.nc-dialog').exists()).toBe(true)
    })

    it('does not render the dialog when closed', () => {
      const wrapper = createWrapper({ open: false })
      expect(wrapper.find('.nc-dialog').exists()).toBe(false)
    })

    it('shows loading state while fetching', async () => {
      let resolvePromise: (value: unknown) => void
      mockGet.mockImplementation(
        () =>
          new Promise((resolve) => {
            resolvePromise = resolve
          }) as never,
      )

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.find('.loading-state').exists()).toBe(true)
      expect(wrapper.text()).toContain('Loading templates')

      resolvePromise!({ data: [] })
      await flushPromises()

      expect(wrapper.find('.loading-state').exists()).toBe(false)
    })

    it('shows empty state when no templates exist', async () => {
      mockGet.mockResolvedValue({ data: [] } as never)

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.find('.empty-state').exists()).toBe(true)
      expect(wrapper.text()).toContain('No templates yet')
    })

    it('shows error state when fetch fails', async () => {
      mockGet.mockRejectedValue(new Error('Network error'))
      vi.spyOn(console, 'error').mockImplementation(() => {})

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.find('.error-state').exists()).toBe(true)
      expect(wrapper.text()).toContain('Failed to load templates')
    })

    it('renders template list when templates exist', async () => {
      const templates: Template[] = [
        createMockTemplate({ id: 1, name: 'Welcome Message' }),
        createMockTemplate({ id: 2, name: 'Closing Remark' }),
      ]
      mockGet.mockResolvedValue({ data: templates } as never)

      const wrapper = createWrapper()
      await flushPromises()

      const items = wrapper.findAll('.template-item')
      expect(items.length).toBe(2)
      expect(wrapper.text()).toContain('Welcome Message')
      expect(wrapper.text()).toContain('Closing Remark')
    })

    it('shows template preview text', async () => {
      const templates: Template[] = [
        createMockTemplate({ id: 1, name: 'Test', content: '[b]Bold content[/b]' }),
      ]
      mockGet.mockResolvedValue({ data: templates } as never)

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.find('.template-preview').text()).toContain('[b]Bold content[/b]')
    })

    it('shows visibility label for threads', async () => {
      const templates: Template[] = [
        createMockTemplate({ id: 1, name: 'Test', visibility: 'threads' }),
      ]
      mockGet.mockResolvedValue({ data: templates } as never)

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.find('.template-visibility').text()).toBe('Threads')
    })

    it('shows "Threads & replies" label for both visibility', async () => {
      const templates: Template[] = [
        createMockTemplate({ id: 1, name: 'Test', visibility: 'both' }),
      ]
      mockGet.mockResolvedValue({ data: templates } as never)

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.find('.template-visibility').text()).toBe('Threads & replies')
    })
  })

  describe('visibility filtering', () => {
    it('passes thread visibility filter when editorContext is thread', async () => {
      createWrapper({ editorContext: 'thread' })
      await flushPromises()

      expect(mockGet).toHaveBeenCalledWith('/templates', { params: { visibility: 'threads' } })
    })

    it('passes reply visibility filter when editorContext is reply', async () => {
      createWrapper({ editorContext: 'reply' })
      await flushPromises()

      expect(mockGet).toHaveBeenCalledWith('/templates', { params: { visibility: 'replies' } })
    })

    it('passes no filter when editorContext is null', async () => {
      createWrapper({ editorContext: null })
      await flushPromises()

      expect(mockGet).toHaveBeenCalledWith('/templates', { params: {} })
    })
  })

  describe('insert', () => {
    it('emits insert event with template content when clicking insert button', async () => {
      const templates: Template[] = [
        createMockTemplate({ id: 1, name: 'Test', content: '[b]Hello[/b]' }),
      ]
      mockGet.mockResolvedValue({ data: templates } as never)

      const wrapper = createWrapper()
      await flushPromises()

      const insertButton = wrapper.findAll('button').find((b) => b.text().includes('Insert'))
      expect(insertButton).toBeDefined()
      await insertButton!.trigger('click')

      expect(wrapper.emitted('insert')).toBeTruthy()
      expect(wrapper.emitted('insert')![0]).toEqual(['[b]Hello[/b]'])
    })

    it('emits update:open false after inserting', async () => {
      const templates: Template[] = [createMockTemplate({ id: 1 })]
      mockGet.mockResolvedValue({ data: templates } as never)

      const wrapper = createWrapper()
      await flushPromises()

      const insertButton = wrapper.findAll('button').find((b) => b.text().includes('Insert'))
      await insertButton!.trigger('click')

      expect(wrapper.emitted('update:open')).toBeTruthy()
      expect(wrapper.emitted('update:open')![0]).toEqual([false])
    })
  })

  describe('create template', () => {
    it('switches to edit view when clicking add template button', async () => {
      const templates: Template[] = [createMockTemplate({ id: 1 })]
      mockGet.mockResolvedValue({ data: templates } as never)

      const wrapper = createWrapper()
      await flushPromises()

      // Find the "Add template" button in the actions slot
      const buttons = wrapper.findAll('button')
      const addButton = buttons.find((b) => b.text().includes('Add template'))
      expect(addButton).toBeDefined()
      await addButton!.trigger('click')

      // Should now show the edit form
      expect(wrapper.find('.template-edit').exists()).toBe(true)
      expect(wrapper.find('.nc-text-field').exists()).toBe(true)
    })

    it('saves a new template via API', async () => {
      mockGet.mockResolvedValue({ data: [] } as never)
      mockPost.mockResolvedValue({ data: createMockTemplate() } as never)

      const wrapper = createWrapper()
      await flushPromises()

      // Click "Add template" in empty state
      const addButton = wrapper.findAll('button').find((b) => b.text().includes('Add template'))
      await addButton!.trigger('click')

      // Fill form
      const vm = wrapper.vm as unknown as {
        form: { name: string; content: string; visibility: string }
      }
      vm.form.name = 'New Template'
      vm.form.content = '[b]Content[/b]'
      vm.form.visibility = 'both'
      await flushPromises()

      // Click Save
      const saveButton = wrapper.findAll('button').find((b) => b.text().includes('Save'))
      await saveButton!.trigger('click')
      await flushPromises()

      expect(mockPost).toHaveBeenCalledWith('/templates', {
        name: 'New Template',
        content: '[b]Content[/b]',
        visibility: 'both',
      })
    })

    it('returns to list view after saving', async () => {
      mockGet.mockResolvedValue({ data: [] } as never)
      mockPost.mockResolvedValue({ data: createMockTemplate() } as never)

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        currentView: string
        form: { name: string; content: string; visibility: string }
        saveTemplate: () => Promise<void>
      }

      vm.currentView = 'edit'
      vm.form.name = 'New'
      vm.form.content = 'Content'
      await vm.saveTemplate()
      await flushPromises()

      expect(vm.currentView).toBe('list')
    })
  })

  describe('edit template', () => {
    it('populates form when editing', async () => {
      const templates: Template[] = [
        createMockTemplate({
          id: 5,
          name: 'Existing',
          content: 'BBCode here',
          visibility: 'threads',
        }),
      ]
      mockGet.mockResolvedValue({ data: templates } as never)

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        openEdit: (tpl: Template) => void
        form: { name: string; content: string; visibility: string }
        currentView: string
      }

      vm.openEdit(templates[0])
      await flushPromises()

      expect(vm.currentView).toBe('edit')
      expect(vm.form.name).toBe('Existing')
      expect(vm.form.content).toBe('BBCode here')
      expect(vm.form.visibility).toBe('threads')
    })

    it('calls PUT when saving an existing template', async () => {
      const template = createMockTemplate({ id: 5, name: 'Existing' })
      mockGet.mockResolvedValue({ data: [template] } as never)
      mockPut.mockResolvedValue({ data: template } as never)

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        openEdit: (tpl: Template) => void
        form: { name: string; content: string; visibility: string }
        saveTemplate: () => Promise<void>
      }

      vm.openEdit(template)
      vm.form.name = 'Updated Name'
      await vm.saveTemplate()
      await flushPromises()

      expect(mockPut).toHaveBeenCalledWith('/templates/5', {
        name: 'Updated Name',
        content: '[b]Hello[/b] world',
        visibility: 'both',
      })
    })
  })

  describe('delete template', () => {
    it('calls DELETE API when deleting a template', async () => {
      const template = createMockTemplate({ id: 3 })
      mockGet.mockResolvedValue({ data: [template] } as never)
      mockDelete.mockResolvedValue({ data: { success: true } } as never)

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        deleteTemplate: (tpl: Template) => Promise<void>
      }

      await vm.deleteTemplate(template)
      await flushPromises()

      expect(mockDelete).toHaveBeenCalledWith('/templates/3')
    })

    it('does not delete when confirm is cancelled', async () => {
      vi.stubGlobal(
        'confirm',
        vi.fn(() => false),
      )

      const template = createMockTemplate({ id: 3 })
      mockGet.mockResolvedValue({ data: [template] } as never)

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        deleteTemplate: (tpl: Template) => Promise<void>
      }

      await vm.deleteTemplate(template)

      expect(mockDelete).not.toHaveBeenCalled()
    })

    it('refetches templates after deletion', async () => {
      const template = createMockTemplate({ id: 3 })
      mockGet.mockResolvedValue({ data: [template] } as never)
      mockDelete.mockResolvedValue({ data: { success: true } } as never)

      const wrapper = createWrapper()
      await flushPromises()

      const callCountBefore = mockGet.mock.calls.length

      const vm = wrapper.vm as unknown as {
        deleteTemplate: (tpl: Template) => Promise<void>
      }
      await vm.deleteTemplate(template)
      await flushPromises()

      expect(mockGet.mock.calls.length).toBeGreaterThan(callCountBefore)
    })
  })

  describe('cancel edit', () => {
    it('returns to list view when cancelling', async () => {
      mockGet.mockResolvedValue({ data: [] } as never)

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        currentView: string
        openCreate: () => void
        cancelEdit: () => void
      }

      vm.openCreate()
      expect(vm.currentView).toBe('edit')

      vm.cancelEdit()
      expect(vm.currentView).toBe('list')
    })
  })

  describe('canSave computed', () => {
    it('is false when name is empty', async () => {
      mockGet.mockResolvedValue({ data: [] } as never)

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        currentView: string
        form: { name: string; content: string }
        canSave: boolean
      }

      vm.currentView = 'edit'
      vm.form.name = ''
      vm.form.content = 'Some content'
      await flushPromises()

      expect(vm.canSave).toBe(false)
    })

    it('is false when content is empty', async () => {
      mockGet.mockResolvedValue({ data: [] } as never)

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        currentView: string
        form: { name: string; content: string }
        canSave: boolean
      }

      vm.currentView = 'edit'
      vm.form.name = 'Name'
      vm.form.content = ''
      await flushPromises()

      expect(vm.canSave).toBe(false)
    })

    it('is true when both name and content are filled', async () => {
      mockGet.mockResolvedValue({ data: [] } as never)

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        currentView: string
        form: { name: string; content: string }
        canSave: boolean
      }

      vm.currentView = 'edit'
      vm.form.name = 'Name'
      vm.form.content = 'Content'
      await flushPromises()

      expect(vm.canSave).toBe(true)
    })
  })

  describe('close event', () => {
    it('emits update:open event when dialog closes', async () => {
      const wrapper = createWrapper()
      await flushPromises()
      ;(wrapper.vm as unknown as { handleClose: (v: boolean) => void }).handleClose(false)

      expect(wrapper.emitted('update:open')).toBeTruthy()
      expect(wrapper.emitted('update:open')![0]).toEqual([false])
    })
  })

  describe('truncate', () => {
    it('does not truncate short text', async () => {
      mockGet.mockResolvedValue({ data: [] } as never)
      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as { truncate: (text: string, max: number) => string }
      expect(vm.truncate('Short', 120)).toBe('Short')
    })

    it('truncates long text with ellipsis', async () => {
      mockGet.mockResolvedValue({ data: [] } as never)
      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as { truncate: (text: string, max: number) => string }
      const longText = 'a'.repeat(200)
      const result = vm.truncate(longText, 120)
      expect(result.length).toBeLessThan(200)
      expect(result).toContain(' …')
    })
  })

  describe('refetch on reopen', () => {
    it('refetches templates when dialog reopens', async () => {
      mockGet.mockResolvedValue({ data: [] } as never)

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const callCount = mockGet.mock.calls.length

      await wrapper.setProps({ open: false })
      await wrapper.setProps({ open: true })
      await flushPromises()

      expect(mockGet.mock.calls.length).toBeGreaterThan(callCount)
    })
  })
})
