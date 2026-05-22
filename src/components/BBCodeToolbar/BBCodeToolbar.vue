<template>
  <div ref="toolbarRef" class="bbcode-toolbar">
    <NcButton
      v-for="button in visibleButtons"
      :key="button.tag"
      variant="tertiary"
      :aria-label="button.label"
      :title="button.label"
      @click="insertBBCode(button)"
      class="bbcode-button"
    >
      <template #icon>
        <component :is="button.icon" :size="20" />
      </template>
    </NcButton>

    <NcActions
      v-if="overflowButtons.length > 0"
      :aria-label="strings.moreActions"
      class="bbcode-trigger-button"
    >
      <template #icon>
        <DotsHorizontalIcon :size="20" />
      </template>
      <NcActionButton
        v-for="button in overflowButtons"
        :key="button.tag"
        @click="insertBBCode(button)"
      >
        <template #icon>
          <component :is="button.icon" :size="20" />
        </template>
        {{ button.label }}
      </NcActionButton>
    </NcActions>

    <LazyEmojiPicker @select="handleEmojiSelect">
      <NcButton
        variant="tertiary"
        :aria-label="strings.emojiLabel"
        :title="strings.emojiLabel"
        class="bbcode-button"
      >
        <template #icon>
          <EmoticonIcon :size="20" />
        </template>
      </NcButton>
    </LazyEmojiPicker>

    <NcActions v-if="!isGuest" :aria-label="strings.attachmentLabel" class="bbcode-trigger-button">
      <template #icon>
        <PaperclipIcon :size="20" />
      </template>
      <NcActionButton @click="handleAttachment">
        <template #icon>
          <PaperclipIcon :size="20" />
        </template>
        {{ strings.pickFileLabel }}
      </NcActionButton>
      <NcActionButton v-if="uploadBehavior !== 'prompt'" @click="handleUpload">
        <template #icon>
          <UploadIcon :size="20" />
        </template>
        {{ strings.uploadFileLabel }}
      </NcActionButton>
      <NcActionButton @click="handleUploadChooseDestination">
        <template #icon>
          <UploadIcon :size="20" />
        </template>
        {{ strings.uploadFileChooseDestinationLabel }}
      </NcActionButton>
    </NcActions>

    <NcButton
      v-if="!isGuest"
      variant="tertiary"
      :aria-label="strings.templateLabel"
      :title="strings.templateLabel"
      @click="showTemplates = true"
      class="bbcode-button"
    >
      <template #icon>
        <TextBoxIcon :size="20" />
      </template>
    </NcButton>

    <div class="toolbar-spacer"></div>

    <NcButton
      variant="tertiary"
      :aria-label="strings.helpLabel"
      :title="strings.helpLabel"
      @click="showHelp = true"
      class="bbcode-button bbcode-help-button"
    >
      <template #icon>
        <HelpCircleIcon :size="20" />
      </template>
    </NcButton>

    <!-- BBCode Help Dialog -->
    <BBCodeHelpDialog v-model:open="showHelp" />

    <!-- Template Modal -->
    <TemplateModal
      v-model:open="showTemplates"
      :editor-context="editorContext"
      @insert="handleTemplateInsert"
    />

    <!-- Upload Progress Dialog -->
    <NcDialog
      :open="uploadDialog"
      :name="uploadError ? strings.uploadError : strings.uploadingFile"
      :can-close="!!uploadError"
      close-on-click-outside
      @update:open="uploadDialog = $event"
      size="small"
    >
      <div class="upload-progress" aria-live="polite">
        <p class="upload-filename">{{ uploadFileName }}</p>
        <template v-if="uploadError">
          <p class="upload-error-message">{{ uploadError }}</p>
        </template>
        <template v-else>
          <NcProgressBar :value="uploadProgress" size="medium" />
          <p class="upload-percentage">{{ uploadProgress }}%</p>
        </template>
      </div>
      <template v-if="uploadError" #actions>
        <NcButton @click="closeUploadDialog">
          {{ strings.close }}
        </NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script lang="ts">
import { defineAsyncComponent, defineComponent, type PropType } from 'vue'
const TemplateModal = defineAsyncComponent(() => import('@/components/TemplateModal'))
import TextBoxIcon from '@icons/TextBox.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import LazyEmojiPicker from '@/components/LazyEmojiPicker'
import { getFilePickerBuilder, FilePickerType } from '@nextcloud/dialogs'
import {
  applyBBCodeTemplate,
  insertTextAtSelection,
  getEditorState,
  setCursorPosition,
  editorStateToSelection,
} from '@/utils/bbcode'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import FormatBoldIcon from '@icons/FormatBold.vue'
import FormatItalicIcon from '@icons/FormatItalic.vue'
import FormatStrikethroughIcon from '@icons/FormatStrikethrough.vue'
import FormatUnderlineIcon from '@icons/FormatUnderline.vue'
import CodeTagsIcon from '@icons/CodeTags.vue'
import EmailIcon from '@icons/Email.vue'
import LinkIcon from '@icons/Link.vue'
import ImageIcon from '@icons/Image.vue'
import FormatQuoteCloseIcon from '@icons/FormatQuoteClose.vue'
import YoutubeIcon from '@icons/Youtube.vue'
import FormatFontIcon from '@icons/FormatFont.vue'
import FormatSizeIcon from '@icons/FormatSize.vue'
import FormatColorFillIcon from '@icons/FormatColorFill.vue'
import FormatAlignLeftIcon from '@icons/FormatAlignLeft.vue'
import FormatAlignCenterIcon from '@icons/FormatAlignCenter.vue'
import FormatAlignRightIcon from '@icons/FormatAlignRight.vue'
import EyeOffIcon from '@icons/EyeOff.vue'
import FormatListBulletedIcon from '@icons/FormatListBulleted.vue'
import PaperclipIcon from '@icons/Paperclip.vue'
import UploadIcon from '@icons/Upload.vue'
import EmoticonIcon from '@icons/Emoticon.vue'
import HelpCircleIcon from '@icons/HelpCircle.vue'
import DotsHorizontalIcon from '@icons/DotsHorizontal.vue'
import BBCodeHelpDialog from '@/components/BBCodeHelpDialog'
import { t } from '@nextcloud/l10n'
import { webDav } from '@/axios'
import { useUserPreferences } from '@/composables/useUserPreferences'

interface BBCodeButton {
  tag: string
  label: string
  icon: any
  template: string
  hasValue?: boolean
  placeholder?: string
  promptForContent?: boolean
  contentPlaceholder?: string
  handler?: () => Promise<void>
}

export default defineComponent({
  name: 'BBCodeToolbar',
  components: {
    NcButton,
    NcActions,
    NcActionButton,
    NcDialog,
    NcProgressBar,
    LazyEmojiPicker,
    BBCodeHelpDialog,
    TemplateModal,
    TextBoxIcon,
    PaperclipIcon,
    UploadIcon,
    EmoticonIcon,
    HelpCircleIcon,
    DotsHorizontalIcon,
  },
  props: {
    textareaRef: {
      type: Object as PropType<HTMLTextAreaElement | HTMLElement | null>,
      default: null,
    },
    modelValue: {
      type: String,
      default: '',
    },
    editorContext: {
      type: String as PropType<'thread' | 'reply' | null>,
      default: null,
    },
    categoryUploadPath: {
      type: String as PropType<string | null>,
      default: null,
    },
  },
  emits: ['insert'],
  setup() {
    const { preferences, uploadBehavior, fetchUserPreferences } = useUserPreferences()
    // Prime the shared cache; safe to call from many places (deduped internally).
    fetchUserPreferences()
    return {
      userPrefs: preferences,
      uploadBehavior,
    }
  },
  data() {
    return {
      showHelp: false,
      showTemplates: false,
      uploadDialog: false,
      uploadProgress: 0,
      uploadFileName: '',
      uploadError: null as string | null,
      visibleCount: 18,
      resizeObserver: null as ResizeObserver | null,
      strings: {
        helpLabel: t('forum', 'BBCode help'),
        emojiLabel: t('forum', 'Insert emoji'),
        attachmentLabel: t('forum', 'Attachment'),
        pickFileLabel: t('forum', 'Pick file from Nextcloud'),
        uploadFileLabel: t('forum', 'Upload file to Nextcloud'),
        uploadFileChooseDestinationLabel: t(
          'forum',
          'Upload file to Nextcloud (choose destination)',
        ),
        uploadingFile: t('forum', 'Uploading file …'),
        uploadError: t('forum', 'Upload failed'),
        close: t('forum', 'Close'),
        moreActions: t('forum', 'More formatting options'),
        templateLabel: t('forum', 'Insert template'),
      },
    }
  },
  computed: {
    visibleButtons(): BBCodeButton[] {
      return this.bbcodeButtons.slice(0, this.visibleCount)
    },
    overflowButtons(): BBCodeButton[] {
      return this.bbcodeButtons.slice(this.visibleCount)
    },
    isGuest(): boolean {
      return getCurrentUser() === null
    },
    bbcodeButtons(): BBCodeButton[] {
      return [
        {
          tag: 'b',
          label: 'Bold',
          icon: FormatBoldIcon,
          template: '[b]{text}[/b]',
        },
        {
          tag: 'i',
          label: 'Italic',
          icon: FormatItalicIcon,
          template: '[i]{text}[/i]',
        },
        {
          tag: 'u',
          label: 'Underline',
          icon: FormatUnderlineIcon,
          template: '[u]{text}[/u]',
        },
        {
          tag: 's',
          label: 'Strikethrough',
          icon: FormatStrikethroughIcon,
          template: '[s]{text}[/s]',
        },
        {
          tag: 'code',
          label: 'Code',
          icon: CodeTagsIcon,
          template: '[code]{text}[/code]',
        },
        {
          tag: 'quote',
          label: 'Quote',
          icon: FormatQuoteCloseIcon,
          template: '[quote]{text}[/quote]',
        },
        {
          tag: 'url',
          label: 'Link',
          icon: LinkIcon,
          template: '[url={value}]{text}[/url]',
          hasValue: true,
          placeholder: 'http://example.com',
          promptForContent: true,
          contentPlaceholder: 'Link text',
        },
        {
          tag: 'email',
          label: 'Email',
          icon: EmailIcon,
          template: '[email]{text}[/email]',
          promptForContent: true,
          contentPlaceholder: 'test@example.com',
        },
        {
          tag: 'img',
          label: 'Image',
          icon: ImageIcon,
          template: '[img]{text}[/img]',
          promptForContent: true,
          contentPlaceholder: 'http://example.com/image.png',
        },
        {
          tag: 'youtube',
          label: 'YouTube',
          icon: YoutubeIcon,
          template: '[youtube]{text}[/youtube]',
          promptForContent: true,
          contentPlaceholder: 'video-id',
        },
        {
          tag: 'list',
          label: 'List',
          icon: FormatListBulletedIcon,
          template: '[list]\n[*]{text}\n[/list]',
        },
        {
          tag: 'color',
          label: 'Color',
          icon: FormatColorFillIcon,
          template: '[color={value}]{text}[/color]',
          hasValue: true,
          placeholder: 'red',
        },
        {
          tag: 'size',
          label: 'Size',
          icon: FormatSizeIcon,
          template: '[size={value}]{text}[/size]',
          hasValue: true,
          placeholder: '12',
        },
        {
          tag: 'font',
          label: 'Font',
          icon: FormatFontIcon,
          template: '[font={value}]{text}[/font]',
          hasValue: true,
          placeholder: 'Arial',
        },
        {
          tag: 'left',
          label: 'Align Left',
          icon: FormatAlignLeftIcon,
          template: '[left]{text}[/left]',
        },
        {
          tag: 'center',
          label: 'Align Center',
          icon: FormatAlignCenterIcon,
          template: '[center]{text}[/center]',
        },
        {
          tag: 'right',
          label: 'Align Right',
          icon: FormatAlignRightIcon,
          template: '[right]{text}[/right]',
        },
        {
          tag: 'spoiler',
          label: 'Spoiler',
          icon: EyeOffIcon,
          template: '[spoiler="{value}"]{text}[/spoiler]',
          hasValue: true,
          placeholder: 'Spoiler title',
          promptForContent: true,
          contentPlaceholder: 'Spoiler content',
        },
      ]
    },
  },
  mounted() {
    this.resizeObserver = new ResizeObserver(() => {
      this.calculateVisibleButtons()
    })
    this.$nextTick(() => {
      const el = this.$refs.toolbarRef as HTMLElement | undefined
      if (el) {
        this.resizeObserver!.observe(el)
        this.calculateVisibleButtons()
      }
    })
  },
  beforeUnmount() {
    if (this.resizeObserver) {
      this.resizeObserver.disconnect()
      this.resizeObserver = null
    }
  },
  methods: {
    calculateVisibleButtons(): void {
      const el = this.$refs.toolbarRef as HTMLElement | undefined
      if (!el) {
        return
      }

      const containerWidth = el.clientWidth
      const buttonWidth = 30
      const gap = 4
      const totalButtons = this.bbcodeButtons.length
      // Fixed elements: emoji + attachment + template + help buttons + spacer min + gaps
      const fixedWidth = 4 * (buttonWidth + gap) + 8
      const overflowTriggerWidth = buttonWidth + gap

      const availableForBBCode = containerWidth - fixedWidth
      const allBBCodeWidth = totalButtons * (buttonWidth + gap)

      if (allBBCodeWidth <= availableForBBCode) {
        this.visibleCount = totalButtons
      } else {
        const availableWithOverflow = availableForBBCode - overflowTriggerWidth
        const maxVisible = Math.max(0, Math.floor(availableWithOverflow / (buttonWidth + gap)))
        this.visibleCount = maxVisible
      }
    },

    async insertBBCode(button: BBCodeButton): Promise<void> {
      // If button has a custom handler, use it instead
      if (button.handler) {
        await button.handler()
        return
      }

      const state = getEditorState(this.textareaRef, this.modelValue)
      if (!state || !this.textareaRef) {
        return
      }

      const { selectedText } = state

      let promptValue = ''
      let contentText = selectedText

      // If the button requires a value (like url, color, size, font), prompt the user
      if (button.hasValue) {
        // eslint-disable-next-line no-alert
        promptValue = prompt(`Enter ${button.label} value:`, button.placeholder || '') || ''
        if (!promptValue) {
          return
        }
      }

      // If no text is selected and button needs content prompt, ask for it
      if (!selectedText && button.promptForContent) {
        // eslint-disable-next-line no-alert
        contentText =
          prompt(`Enter ${button.label} content:`, button.contentPlaceholder || '') || ''
        if (!contentText) {
          return
        }
      }

      // Use the bbcode utility to apply the template
      const result = applyBBCodeTemplate(editorStateToSelection(state), {
        template: button.template,
        value: promptValue,
        fallbackText: contentText || button.placeholder || '',
      })

      // Emit the insert event so the parent can update the model
      this.$emit('insert', {
        text: result.text,
        cursorPos: result.cursorPosition,
        selectedText,
      })

      // Focus and set cursor position after insertion
      // Use $nextTick + requestAnimationFrame to ensure DOM has fully updated
      const editorRef = this.textareaRef
      this.$nextTick(() => {
        requestAnimationFrame(() => {
          editorRef.focus()
          setCursorPosition(editorRef, result.cursorPosition)
        })
      })
    },

    async handleAttachment(): Promise<void> {
      if (!this.textareaRef) {
        return
      }

      try {
        const picker = getFilePickerBuilder(t('forum', 'Pick a file to attach'))
          .setMultiSelect(false)
          .setType(1) // TYPE_FILE
          .build()

        const nodes = await picker.pickNodes()
        const node = Array.isArray(nodes) ? nodes[0] : undefined
        const fileId = node?.fileid

        if (!fileId) {
          return
        }

        const state = getEditorState(this.textareaRef, this.modelValue)
        if (!state) {
          return
        }

        // Use the bbcode utility to insert the attachment tag
        const result = insertTextAtSelection(
          editorStateToSelection(state),
          `[attachment]${fileId}[/attachment]`,
        )

        // Emit the insert event so the parent can update the model
        this.$emit('insert', {
          text: result.text,
          cursorPos: result.cursorPosition,
          selectedText: '',
        })

        // Focus the editor after insertion
        const editorRef = this.textareaRef
        this.$nextTick(() => {
          editorRef.focus()
          setCursorPosition(editorRef, result.cursorPosition)
        })
      } catch (error) {
        // Silently ignore if user canceled the dialog
        // The file picker throws "No nodes selected" when canceled, which is expected behavior
        if (
          error instanceof Error &&
          error.message &&
          !error.message.includes('No nodes selected')
        ) {
          console.error('Error picking file:', error)
        }
        // Otherwise, user simply canceled - no need to log
      }
    },

    handleEmojiSelect(emoji: string): void {
      const state = getEditorState(this.textareaRef, this.modelValue)
      if (!state || !this.textareaRef) {
        return
      }

      // Use the bbcode utility to insert the emoji
      const result = insertTextAtSelection(editorStateToSelection(state), emoji)

      // Emit the insert event so the parent can update the model
      this.$emit('insert', {
        text: result.text,
        cursorPos: result.cursorPosition,
        selectedText: '',
      })

      // Focus the editor after insertion
      const editorRef = this.textareaRef
      this.$nextTick(() => {
        editorRef.focus()
        setCursorPosition(editorRef, result.cursorPosition)
      })
    },

    async handleUpload(): Promise<void> {
      if (!this.textareaRef) {
        return
      }

      const file = await this.pickLocalFile()
      if (file) {
        await this.uploadFile(file)
      }
    },

    async handleUploadChooseDestination(): Promise<void> {
      if (!this.textareaRef) {
        return
      }

      const destination = await this.promptForUploadDestination()
      if (!destination) {
        return
      }

      const file = await this.pickLocalFile()
      if (!file) {
        return
      }

      await this.uploadFileTo(file, destination, { allowFallback: false })
    },

    pickLocalFile(): Promise<File | null> {
      return new Promise((resolve) => {
        const fileInput = document.createElement('input')
        fileInput.type = 'file'
        fileInput.style.display = 'none'

        let resolved = false
        const cleanup = () => {
          if (fileInput.parentNode) {
            document.body.removeChild(fileInput)
          }
        }

        fileInput.addEventListener('change', (event) => {
          const target = event.target as HTMLInputElement
          const file = target.files?.[0] ?? null
          resolved = true
          cleanup()
          resolve(file)
        })

        // If the user cancels there's no reliable event in all browsers,
        // so resolve with null on focus restored without a change firing.
        const onFocus = () => {
          window.removeEventListener('focus', onFocus)
          setTimeout(() => {
            if (!resolved) {
              cleanup()
              resolve(null)
            }
          }, 300)
        }
        window.addEventListener('focus', onFocus)

        document.body.appendChild(fileInput)
        fileInput.click()
      })
    },

    /**
     * Default upload path: respect the user preference for category-specific
     * paths when the editor has one wired up, otherwise the user's own dir.
     * Falls back from the category path to the default on a 403/404.
     *
     * When the user prefers `upload_behavior: 'prompt'`, this opens the
     * destination picker before each upload — the picked path is used as
     * destination with no fallback (the user picked it explicitly).
     */
    async uploadFile(file: File): Promise<void> {
      if (!this.textareaRef) {
        return
      }

      try {
        // Ensure the shared prefs cache is populated before reading from it.
        await useUserPreferences().fetchUserPreferences()
        const {
          upload_directory,
          upload_directory_resolved_path,
          use_category_upload_path,
          upload_behavior,
        } = this.userPrefs
        // Prefer the server-resolved path (file-ID based, per-user) and fall
        // back to the legacy string for installs that haven't migrated yet.
        const defaultPath = upload_directory_resolved_path || upload_directory

        if (upload_behavior === 'prompt') {
          const destination = await this.promptForUploadDestination()
          if (!destination) {
            return
          }
          await this.uploadFileTo(file, destination, { allowFallback: false })
          return
        }

        const usingCategoryPath = !!(use_category_upload_path && this.categoryUploadPath)
        const primaryPath = usingCategoryPath ? (this.categoryUploadPath as string) : defaultPath

        await this.uploadFileTo(file, primaryPath, {
          allowFallback: usingCategoryPath ? defaultPath : false,
        })
      } catch (error) {
        console.error('Error uploading file:', error)
        this.uploadError =
          error instanceof Error ? error.message : t('forum', 'Failed to upload file')
      }
    },

    async promptForUploadDestination(): Promise<string | null> {
      try {
        const picker = getFilePickerBuilder(t('forum', 'Select upload destination'))
          .setMultiSelect(false)
          .setType(FilePickerType.Choose)
          .allowDirectories()
          .build()

        const path = await picker.pick()
        if (!path) {
          return null
        }
        return path.startsWith('/') ? path.substring(1) : path
      } catch (error) {
        if (
          error instanceof Error &&
          error.message &&
          !error.message.includes('No nodes selected')
        ) {
          console.error('Error picking destination:', error)
        }
        return null
      }
    },

    async uploadFileTo(
      file: File,
      destination: string,
      options: { allowFallback: string | false },
    ): Promise<void> {
      if (!this.textareaRef) {
        return
      }

      this.uploadFileName = file.name
      this.uploadProgress = 0
      this.uploadError = null
      this.uploadDialog = true

      const user = getCurrentUser()
      if (!user) {
        this.uploadError = t('forum', 'User not authenticated')
        return
      }

      const attempt = async (path: string) => {
        await this.ensureDirectoryExists(user.uid, path)
        const davPath = `/remote.php/dav/files/${user.uid}/${path}/${file.name}`
        return webDav.put(davPath, file, {
          headers: {
            'Content-Type': file.type || 'application/octet-stream',
          },
          onUploadProgress: (progressEvent) => {
            if (progressEvent.total) {
              this.uploadProgress = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            }
          },
        })
      }

      try {
        let uploadResponse
        try {
          uploadResponse = await attempt(destination)
        } catch (error) {
          const status =
            (error as { response?: { status?: number }; status?: number })?.response?.status ??
            (error as { status?: number })?.status
          if (
            options.allowFallback &&
            (status === 403 || status === 404) &&
            options.allowFallback !== destination
          ) {
            // Silent fallback to the user's own default directory.
            this.uploadProgress = 0
            uploadResponse = await attempt(options.allowFallback)
          } else {
            throw error
          }
        }

        const state = getEditorState(this.textareaRef, this.modelValue)
        if (!state) {
          return
        }

        // The OC-FileId header is the federated form (zero-padded numeric ID
        // + instance ID, e.g. "00000220oco7mnbdyixw") — extract the leading
        // numeric portion.
        const fileIdHeader =
          uploadResponse?.headers?.['oc-fileid'] ?? uploadResponse?.headers?.['OC-FileId']
        const numericId = fileIdHeader ? String(fileIdHeader).match(/^0*(\d+)/)?.[1] : undefined
        const fileRef = numericId ?? `${destination}/${file.name}`
        const result = insertTextAtSelection(
          editorStateToSelection(state),
          `[attachment]${fileRef}[/attachment]`,
        )

        this.$emit('insert', {
          text: result.text,
          cursorPos: result.cursorPosition,
          selectedText: '',
        })

        const editorRef = this.textareaRef
        if (editorRef) {
          this.$nextTick(() => {
            editorRef.focus()
            setCursorPosition(editorRef, result.cursorPosition)
          })
        }

        this.uploadDialog = false
      } catch (error) {
        console.error('Error uploading file:', error)
        this.uploadError =
          error instanceof Error ? error.message : t('forum', 'Failed to upload file')
      }
    },

    async ensureDirectoryExists(userId: string, path: string): Promise<void> {
      // Try to create the directory
      // If it already exists, the request will fail but that's ok
      const davPath = `/remote.php/dav/files/${userId}/${path}`
      try {
        await webDav.request({
          method: 'MKCOL',
          url: davPath,
        })
      } catch (error) {
        // Ignore errors - directory might already exist
        // We'll find out when we try to upload the file
      }
    },

    handleTemplateInsert(content: string): void {
      const state = getEditorState(this.textareaRef, this.modelValue)
      if (!state || !this.textareaRef) {
        return
      }

      const result = insertTextAtSelection(editorStateToSelection(state), content)

      this.$emit('insert', {
        text: result.text,
        cursorPos: result.cursorPosition,
        selectedText: '',
      })

      const editorRef = this.textareaRef
      this.$nextTick(() => {
        editorRef.focus()
        setCursorPosition(editorRef, result.cursorPosition)
      })
    },

    closeUploadDialog(): void {
      this.uploadDialog = false
      this.uploadError = null
      this.uploadProgress = 0
      this.uploadFileName = ''
    },
  },
})
</script>

<style scoped lang="scss">
.bbcode-toolbar {
  display: flex;
  flex-wrap: nowrap;
  gap: 4px;
  overflow: hidden;
}

.toolbar-spacer {
  flex: 1;
  min-width: 8px;
}

.bbcode-button {
  min-width: auto !important;
  padding: 4px 8px !important;

  &:hover {
    background-color: var(--color-background-dark) !important;
  }
}

.bbcode-trigger-button {
  min-width: auto !important;

  :deep(.v-popper) {
    height: 100%;
    display: flex;
  }

  :deep(button:hover:not(:disabled)) {
    background-color: var(--color-background-dark) !important;
  }
}

.bbcode-help-button {
  margin-left: auto;
}

.upload-progress {
  padding: 20px;
  text-align: center;

  .upload-filename {
    margin: 0 0 16px 0;
    font-weight: 500;
    word-break: break-word;
  }

  .upload-error-message {
    margin: 0;
    padding: 16px;
    background-color: var(--color-error-light);
    color: var(--color-error-dark);
    border-radius: 6px;
    word-break: break-word;
  }

  .upload-percentage {
    margin: 12px 0 0 0;
    font-size: 0.9rem;
    color: var(--color-text-maxcontrast);
  }
}
</style>
