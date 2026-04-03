import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import BBCodeEditor from './BBCodeEditor.vue'

// Uses global mocks for @nextcloud/l10n, NcNoteCard from test-setup.ts

vi.mock('@icons/Upload.vue', () => createIconMock('UploadIcon'))

vi.mock('@nextcloud/vue/components/NcRichContenteditable', () =>
  createComponentMock('NcRichContenteditable', {
    template:
      '<div class="nc-rich-contenteditable"><div contenteditable="true" @input="$emit(\'update:modelValue\', $event.target?.textContent || \'\')"><slot /></div></div>',
    props: ['modelValue', 'placeholder', 'disabled', 'autoComplete', 'userData', 'multiline'],
    emits: ['update:modelValue', 'keydown'],
  }),
)

vi.mock('@/components/BBCodeToolbar', () =>
  createComponentMock('BBCodeToolbar', {
    template: '<div class="bbcode-toolbar-mock" />',
    props: ['textareaRef', 'modelValue', 'editorContext'],
    emits: ['insert'],
  }),
)

// Uses global mock for @/axios from test-setup.ts
import { ocs } from '@/axios'
const mockOcsGet = vi.mocked(ocs.get)

// Helper to call private methods on BBCodeEditor vm
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function callVm(vm: InstanceType<typeof BBCodeEditor>, method: string, ...args: unknown[]): any {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  return (vm as any)[method](...args)
}

describe('BBCodeEditor', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockOcsGet.mockResolvedValue({ data: [] })
  })

  describe('rendering', () => {
    it('should render the editor container', () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '' },
      })
      expect(wrapper.find('.bbcode-editor-container').exists()).toBe(true)
    })

    it('should render the BBCodeToolbar', () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '' },
      })
      expect(wrapper.find('.bbcode-toolbar-mock').exists()).toBe(true)
    })

    it('should not show attachment disclaimer when no attachment bbcode', () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: 'Hello world' },
      })
      expect(wrapper.find('.attachment-disclaimer').exists()).toBe(false)
    })

    it('should show attachment disclaimer when attachment bbcode is present', () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: 'Check this [attachment]file.pdf[/attachment]' },
      })
      expect(wrapper.find('.attachment-disclaimer').exists()).toBe(true)
    })

    it('should not show drag overlay by default', () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '' },
      })
      expect(wrapper.find('.drag-overlay').exists()).toBe(false)
    })
  })

  describe('props', () => {
    it('should accept modelValue prop', () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: 'test content' },
      })
      expect(wrapper.props('modelValue')).toBe('test content')
    })

    it('should accept placeholder prop', () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '', placeholder: 'Type here …' },
      })
      expect(wrapper.props('placeholder')).toBe('Type here …')
    })

    it('should accept disabled prop', () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '', disabled: true },
      })
      expect(wrapper.props('disabled')).toBe(true)
    })

    it('should accept editorContext prop', () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '', editorContext: 'thread' },
      })
      expect(wrapper.props('editorContext')).toBe('thread')
    })
  })

  describe('events', () => {
    it('should emit update:modelValue when input changes', async () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '' },
      })
      await vi.dynamicImportSettled()

      callVm(wrapper.vm, 'handleInput', 'new content')
      await wrapper.vm.$nextTick()

      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')![0]).toEqual(['new content'])
    })

    it('should strip zero-width spaces from emitted value', async () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '' },
      })
      await vi.dynamicImportSettled()

      callVm(wrapper.vm, 'handleInput', 'content\u200B')
      await wrapper.vm.$nextTick()

      expect(wrapper.emitted('update:modelValue')![0]).toEqual(['content'])
    })

    it('should emit update:modelValue on BBCode toolbar insert', async () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '' },
      })
      await vi.dynamicImportSettled()

      callVm(wrapper.vm, 'handleBBCodeInsert', { text: '[b]bold[/b]', cursorPos: 10 })
      await wrapper.vm.$nextTick()

      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')![0]).toEqual(['[b]bold[/b]'])
    })
  })

  describe('drag and drop', () => {
    it('should show drag overlay on dragenter with files', async () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '' },
      })

      await wrapper.find('.bbcode-editor-container').trigger('dragenter', {
        dataTransfer: { types: ['Files'] },
      })

      expect(wrapper.find('.drag-overlay').exists()).toBe(true)
    })

    it('should hide drag overlay on dragleave', async () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '' },
      })

      await wrapper.find('.bbcode-editor-container').trigger('dragenter', {
        dataTransfer: { types: ['Files'] },
      })
      expect(wrapper.find('.drag-overlay').exists()).toBe(true)

      await wrapper.find('.bbcode-editor-container').trigger('dragleave')
      expect(wrapper.find('.drag-overlay').exists()).toBe(false)
    })

    it('should hide drag overlay on drop', async () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '' },
      })

      await wrapper.find('.bbcode-editor-container').trigger('dragenter', {
        dataTransfer: { types: ['Files'] },
      })

      await wrapper.find('.bbcode-editor-container').trigger('drop', {
        dataTransfer: { files: [] },
      })
      expect(wrapper.find('.drag-overlay').exists()).toBe(false)
    })
  })

  describe('mentions', () => {
    it('should add cursor helper after mentions at end of content', () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '' },
      })
      const result = callVm(wrapper.vm, 'addCursorHelperAfterMentions', 'Hello @john')
      expect(result).toBe('Hello @john\u200B')
    })

    it('should not add cursor helper when no mention at end', () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '' },
      })
      const result = callVm(wrapper.vm, 'addCursorHelperAfterMentions', 'Hello world')
      expect(result).toBe('Hello world')
    })

    it('should handle quoted mentions at end of content', () => {
      const wrapper = mount(BBCodeEditor, {
        props: { modelValue: '' },
      })
      const result = callVm(wrapper.vm, 'addCursorHelperAfterMentions', 'Hello @"john doe"')
      expect(result).toBe('Hello @"john doe"\u200B')
    })

    it('should parse mentions and fetch user data', async () => {
      mockOcsGet.mockResolvedValue({
        data: [{ id: 'john', label: 'John Doe', icon: '', source: 'users' }],
      })

      mount(BBCodeEditor, {
        props: { modelValue: 'Hello @john' },
      })
      await vi.dynamicImportSettled()

      expect(mockOcsGet).toHaveBeenCalledWith('/users/autocomplete', {
        params: { search: 'john', limit: 1 },
      })
    })
  })
})
