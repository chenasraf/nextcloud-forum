import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'

// Mock icons
vi.mock('@icons/FormatBold.vue', () => createIconMock('FormatBoldIcon'))
vi.mock('@icons/FormatItalic.vue', () => createIconMock('FormatItalicIcon'))
vi.mock('@icons/FormatStrikethrough.vue', () => createIconMock('FormatStrikethroughIcon'))
vi.mock('@icons/FormatUnderline.vue', () => createIconMock('FormatUnderlineIcon'))
vi.mock('@icons/CodeTags.vue', () => createIconMock('CodeTagsIcon'))
vi.mock('@icons/Email.vue', () => createIconMock('EmailIcon'))
vi.mock('@icons/Link.vue', () => createIconMock('LinkIcon'))
vi.mock('@icons/Image.vue', () => createIconMock('ImageIcon'))
vi.mock('@icons/FormatQuoteClose.vue', () => createIconMock('FormatQuoteCloseIcon'))
vi.mock('@icons/Youtube.vue', () => createIconMock('YoutubeIcon'))
vi.mock('@icons/FormatFont.vue', () => createIconMock('FormatFontIcon'))
vi.mock('@icons/FormatSize.vue', () => createIconMock('FormatSizeIcon'))
vi.mock('@icons/FormatColorFill.vue', () => createIconMock('FormatColorFillIcon'))
vi.mock('@icons/FormatAlignLeft.vue', () => createIconMock('FormatAlignLeftIcon'))
vi.mock('@icons/FormatAlignCenter.vue', () => createIconMock('FormatAlignCenterIcon'))
vi.mock('@icons/FormatAlignRight.vue', () => createIconMock('FormatAlignRightIcon'))
vi.mock('@icons/EyeOff.vue', () => createIconMock('EyeOffIcon'))
vi.mock('@icons/FormatListBulleted.vue', () => createIconMock('FormatListBulletedIcon'))
vi.mock('@icons/Paperclip.vue', () => createIconMock('PaperclipIcon'))
vi.mock('@icons/Upload.vue', () => createIconMock('UploadIcon'))
vi.mock('@icons/Emoticon.vue', () => createIconMock('EmoticonIcon'))
vi.mock('@icons/HelpCircle.vue', () => createIconMock('HelpCircleIcon'))

// Mock child components
vi.mock('@/components/LazyEmojiPicker', () =>
  createComponentMock('LazyEmojiPicker', {
    template: '<div class="emoji-picker-mock"><slot /></div>',
    props: [],
  }),
)

vi.mock('@/components/BBCodeHelpDialog', () =>
  createComponentMock('BBCodeHelpDialog', {
    template: '<div class="bbcode-help-dialog-mock" v-if="open" />',
    props: ['open'],
  }),
)

// Mock Nextcloud dialogs
vi.mock('@nextcloud/dialogs', () => ({
  getFilePickerBuilder: vi.fn(() => ({
    setMultiSelect: vi.fn().mockReturnThis(),
    setType: vi.fn().mockReturnThis(),
    build: vi.fn(() => ({
      pick: vi.fn(),
    })),
  })),
  FilePickerType: { TYPE_FILE: 1 },
}))

// Mock Nextcloud auth
vi.mock('@nextcloud/auth', () => ({
  getCurrentUser: vi.fn(() => ({ uid: 'testuser', displayName: 'Test User' })),
}))

// Mock axios
vi.mock('@/axios', () => ({
  ocs: {
    get: vi.fn(),
  },
  webDav: {
    put: vi.fn(),
    request: vi.fn(),
  },
}))

// Mock NcActions and NcActionButton since they're complex
vi.mock('@nextcloud/vue/components/NcActions', () => ({
  default: {
    name: 'NcActions',
    template: '<div class="nc-actions-mock"><slot /><slot name="icon" /></div>',
    props: ['ariaLabel'],
  },
}))

vi.mock('@nextcloud/vue/components/NcActionButton', () => ({
  default: {
    name: 'NcActionButton',
    template:
      '<button class="nc-action-button-mock" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
    props: [],
    emits: ['click'],
  },
}))

vi.mock('@nextcloud/vue/components/NcProgressBar', () => ({
  default: {
    name: 'NcProgressBar',
    template: '<div class="nc-progress-bar-mock" :data-value="value" />',
    props: ['value', 'size'],
  },
}))

// Import after mocks
import BBCodeToolbar from './BBCodeToolbar.vue'

describe('BBCodeToolbar', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal('prompt', vi.fn())
  })

  const createWrapper = (props = {}) => {
    return mount(BBCodeToolbar, {
      props: {
        textareaRef: null,
        modelValue: '',
        ...props,
      },
    })
  }

  describe('rendering', () => {
    it('renders the toolbar', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.bbcode-toolbar').exists()).toBe(true)
    })

    it('renders BBCode formatting buttons', () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('.bbcode-button')
      // Should have multiple BBCode buttons (bold, italic, etc.) + emoji + help
      expect(buttons.length).toBeGreaterThan(10)
    })

    it('renders help button', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.bbcode-help-button').exists()).toBe(true)
    })

    it('renders emoji picker trigger', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.emoji-picker-mock').exists()).toBe(true)
    })

    it('renders attachment actions', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.nc-actions-mock').exists()).toBe(true)
    })
  })

  describe('bbcodeButtons computed', () => {
    it('includes bold button', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { bbcodeButtons: Array<{ tag: string }> }
      expect(vm.bbcodeButtons.some((b) => b.tag === 'b')).toBe(true)
    })

    it('includes italic button', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { bbcodeButtons: Array<{ tag: string }> }
      expect(vm.bbcodeButtons.some((b) => b.tag === 'i')).toBe(true)
    })

    it('includes underline button', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { bbcodeButtons: Array<{ tag: string }> }
      expect(vm.bbcodeButtons.some((b) => b.tag === 'u')).toBe(true)
    })

    it('includes strikethrough button', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { bbcodeButtons: Array<{ tag: string }> }
      expect(vm.bbcodeButtons.some((b) => b.tag === 's')).toBe(true)
    })

    it('includes code button', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { bbcodeButtons: Array<{ tag: string }> }
      expect(vm.bbcodeButtons.some((b) => b.tag === 'code')).toBe(true)
    })

    it('includes quote button', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { bbcodeButtons: Array<{ tag: string }> }
      expect(vm.bbcodeButtons.some((b) => b.tag === 'quote')).toBe(true)
    })

    it('includes url button', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { bbcodeButtons: Array<{ tag: string }> }
      expect(vm.bbcodeButtons.some((b) => b.tag === 'url')).toBe(true)
    })

    it('includes img button', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { bbcodeButtons: Array<{ tag: string }> }
      expect(vm.bbcodeButtons.some((b) => b.tag === 'img')).toBe(true)
    })

    it('includes youtube button', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { bbcodeButtons: Array<{ tag: string }> }
      expect(vm.bbcodeButtons.some((b) => b.tag === 'youtube')).toBe(true)
    })

    it('includes list button', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { bbcodeButtons: Array<{ tag: string }> }
      expect(vm.bbcodeButtons.some((b) => b.tag === 'list')).toBe(true)
    })

    it('includes color button with hasValue', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as {
        bbcodeButtons: Array<{ tag: string; hasValue?: boolean }>
      }
      const colorButton = vm.bbcodeButtons.find((b) => b.tag === 'color')
      expect(colorButton).toBeDefined()
      expect(colorButton!.hasValue).toBe(true)
    })

    it('includes spoiler button', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { bbcodeButtons: Array<{ tag: string }> }
      expect(vm.bbcodeButtons.some((b) => b.tag === 'spoiler')).toBe(true)
    })
  })

  describe('help dialog', () => {
    it('opens help dialog when help button is clicked', async () => {
      const wrapper = createWrapper()

      expect(wrapper.find('.bbcode-help-dialog-mock').exists()).toBe(false)

      await wrapper.find('.bbcode-help-button').trigger('click')

      expect(wrapper.find('.bbcode-help-dialog-mock').exists()).toBe(true)
    })

    it('closes help dialog when showHelp is set to false', async () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { showHelp: boolean }

      vm.showHelp = true
      await flushPromises()
      expect(wrapper.find('.bbcode-help-dialog-mock').exists()).toBe(true)

      vm.showHelp = false
      await flushPromises()
      expect(wrapper.find('.bbcode-help-dialog-mock').exists()).toBe(false)
    })
  })

  describe('getEditorState', () => {
    it('returns null when textareaRef is null', () => {
      const wrapper = createWrapper({ textareaRef: null })
      const vm = wrapper.vm as unknown as { getEditorState: () => unknown }

      expect(vm.getEditorState()).toBeNull()
    })

    it('returns editor state for textarea element', () => {
      const textarea = document.createElement('textarea')
      textarea.value = 'Hello world'
      textarea.selectionStart = 0
      textarea.selectionEnd = 5

      const wrapper = createWrapper({ textareaRef: textarea })
      const vm = wrapper.vm as unknown as {
        getEditorState: () => {
          value: string
          start: number
          end: number
          selectedText: string
        } | null
      }

      const state = vm.getEditorState()
      expect(state).not.toBeNull()
      expect(state!.value).toBe('Hello world')
      expect(state!.start).toBe(0)
      expect(state!.end).toBe(5)
      expect(state!.selectedText).toBe('Hello')
    })
  })

  describe('isTextarea', () => {
    it('returns true for textarea elements', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { isTextarea: (el: HTMLElement) => boolean }

      const textarea = document.createElement('textarea')
      expect(vm.isTextarea(textarea)).toBe(true)
    })

    it('returns false for div elements', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { isTextarea: (el: HTMLElement) => boolean }

      const div = document.createElement('div')
      expect(vm.isTextarea(div)).toBe(false)
    })
  })

  describe('insertBBCode', () => {
    it('does nothing when textareaRef is null', async () => {
      const wrapper = createWrapper({ textareaRef: null })
      const vm = wrapper.vm as unknown as {
        insertBBCode: (button: { tag: string; template: string }) => Promise<void>
      }

      await vm.insertBBCode({ tag: 'b', template: '[b]{text}[/b]' })

      expect(wrapper.emitted('insert')).toBeFalsy()
    })

    it('emits insert event with new text for simple BBCode', async () => {
      const textarea = document.createElement('textarea')
      textarea.value = 'Hello world'
      textarea.selectionStart = 0
      textarea.selectionEnd = 5

      const wrapper = createWrapper({ textareaRef: textarea })
      const vm = wrapper.vm as unknown as {
        insertBBCode: (button: { tag: string; template: string; label: string }) => Promise<void>
      }

      await vm.insertBBCode({ tag: 'b', template: '[b]{text}[/b]', label: 'Bold' })

      expect(wrapper.emitted('insert')).toBeTruthy()
      const emitted = wrapper.emitted('insert')![0]![0] as { text: string; cursorPos: number }
      expect(emitted.text).toBe('[b]Hello[/b] world')
    })

    it('prompts for value when button has hasValue', async () => {
      const mockPrompt = vi.fn().mockReturnValue('red')
      vi.stubGlobal('prompt', mockPrompt)

      const textarea = document.createElement('textarea')
      textarea.value = 'Hello'
      textarea.selectionStart = 0
      textarea.selectionEnd = 5

      const wrapper = createWrapper({ textareaRef: textarea })
      const vm = wrapper.vm as unknown as {
        insertBBCode: (button: {
          tag: string
          template: string
          label: string
          hasValue: boolean
          placeholder: string
        }) => Promise<void>
      }

      await vm.insertBBCode({
        tag: 'color',
        template: '[color={value}]{text}[/color]',
        label: 'Color',
        hasValue: true,
        placeholder: 'red',
      })

      expect(mockPrompt).toHaveBeenCalled()
      expect(wrapper.emitted('insert')).toBeTruthy()
    })

    it('does nothing when prompt is cancelled for hasValue button', async () => {
      const mockPrompt = vi.fn().mockReturnValue(null)
      vi.stubGlobal('prompt', mockPrompt)

      const textarea = document.createElement('textarea')
      textarea.value = 'Hello'
      textarea.selectionStart = 0
      textarea.selectionEnd = 5

      const wrapper = createWrapper({ textareaRef: textarea })
      const vm = wrapper.vm as unknown as {
        insertBBCode: (button: {
          tag: string
          template: string
          label: string
          hasValue: boolean
          placeholder: string
        }) => Promise<void>
      }

      await vm.insertBBCode({
        tag: 'color',
        template: '[color={value}]{text}[/color]',
        label: 'Color',
        hasValue: true,
        placeholder: 'red',
      })

      expect(wrapper.emitted('insert')).toBeFalsy()
    })

    it('prompts for content when no selection and promptForContent is true', async () => {
      const mockPrompt = vi.fn().mockReturnValue('http://example.com/image.png')
      vi.stubGlobal('prompt', mockPrompt)

      const textarea = document.createElement('textarea')
      textarea.value = ''
      textarea.selectionStart = 0
      textarea.selectionEnd = 0

      const wrapper = createWrapper({ textareaRef: textarea })
      const vm = wrapper.vm as unknown as {
        insertBBCode: (button: {
          tag: string
          template: string
          label: string
          promptForContent: boolean
          contentPlaceholder: string
        }) => Promise<void>
      }

      await vm.insertBBCode({
        tag: 'img',
        template: '[img]{text}[/img]',
        label: 'Image',
        promptForContent: true,
        contentPlaceholder: 'http://example.com/image.png',
      })

      expect(mockPrompt).toHaveBeenCalled()
      expect(wrapper.emitted('insert')).toBeTruthy()
    })
  })

  describe('handleEmojiSelect', () => {
    it('emits insert event with emoji', async () => {
      const textarea = document.createElement('textarea')
      textarea.value = 'Hello '
      textarea.selectionStart = 6
      textarea.selectionEnd = 6

      const wrapper = createWrapper({ textareaRef: textarea })
      const vm = wrapper.vm as unknown as { handleEmojiSelect: (emoji: string) => void }

      vm.handleEmojiSelect('😀')

      expect(wrapper.emitted('insert')).toBeTruthy()
      const emitted = wrapper.emitted('insert')![0]![0] as { text: string; cursorPos: number }
      expect(emitted.text).toBe('Hello 😀')
      expect(emitted.cursorPos).toBe(8) // After emoji
    })

    it('does nothing when textareaRef is null', () => {
      const wrapper = createWrapper({ textareaRef: null })
      const vm = wrapper.vm as unknown as { handleEmojiSelect: (emoji: string) => void }

      vm.handleEmojiSelect('😀')

      expect(wrapper.emitted('insert')).toBeFalsy()
    })
  })

  describe('upload dialog', () => {
    it('initializes with upload dialog closed', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { uploadDialog: boolean }
      expect(vm.uploadDialog).toBe(false)
    })

    it('closeUploadDialog resets upload state', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as {
        uploadDialog: boolean
        uploadProgress: number
        uploadFileName: string
        uploadError: string | null
        closeUploadDialog: () => void
      }

      vm.uploadDialog = true
      vm.uploadProgress = 50
      vm.uploadFileName = 'test.pdf'
      vm.uploadError = 'Some error'

      vm.closeUploadDialog()

      expect(vm.uploadDialog).toBe(false)
      expect(vm.uploadProgress).toBe(0)
      expect(vm.uploadFileName).toBe('')
      expect(vm.uploadError).toBeNull()
    })
  })

  describe('strings', () => {
    it('has correct translation keys', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { strings: Record<string, string> }

      expect(vm.strings.helpLabel).toBe('BBCode help')
      expect(vm.strings.emojiLabel).toBe('Insert emoji')
      expect(vm.strings.attachmentLabel).toBe('Attachment')
      expect(vm.strings.pickFileLabel).toBe('Pick file from Nextcloud')
      expect(vm.strings.uploadFileLabel).toBe('Upload file to Nextcloud')
    })
  })
})
