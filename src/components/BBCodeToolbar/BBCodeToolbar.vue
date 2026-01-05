<template>
  <div class="bbcode-toolbar">
    <NcButton
      v-for="button in bbcodeButtons"
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

    <NcActions :aria-label="strings.attachmentLabel" class="bbcode-trigger-button">
      <template #icon>
        <PaperclipIcon :size="20" />
      </template>
      <NcActionButton @click="handleAttachment">
        <template #icon>
          <PaperclipIcon :size="20" />
        </template>
        {{ strings.pickFileLabel }}
      </NcActionButton>
      <NcActionButton @click="handleUpload">
        <template #icon>
          <UploadIcon :size="20" />
        </template>
        {{ strings.uploadFileLabel }}
      </NcActionButton>
    </NcActions>

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

    <!-- Upload Progress Dialog -->
    <NcDialog
      :open="uploadDialog"
      :name="uploadError ? strings.uploadError : strings.uploadingFile"
      :can-close="!!uploadError"
      @update:open="uploadDialog = $event"
      size="small"
    >
      <div class="upload-progress">
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
import { defineComponent, type PropType } from 'vue'
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
  extractRelativePathFromFilePicker,
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
import BBCodeHelpDialog from '@/components/BBCodeHelpDialog'
import { t } from '@nextcloud/l10n'
import { webDav, ocs } from '@/axios'

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
    PaperclipIcon,
    UploadIcon,
    EmoticonIcon,
    HelpCircleIcon,
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
  },
  emits: ['insert'],
  data() {
    return {
      showHelp: false,
      uploadDialog: false,
      uploadProgress: 0,
      uploadFileName: '',
      uploadError: null as string | null,
      strings: {
        helpLabel: t('forum', 'BBCode help'),
        emojiLabel: t('forum', 'Insert emoji'),
        attachmentLabel: t('forum', 'Attachment'),
        pickFileLabel: t('forum', 'Pick file from Nextcloud'),
        uploadFileLabel: t('forum', 'Upload file to Nextcloud'),
        uploadingFile: t('forum', 'Uploading file …'),
        uploadError: t('forum', 'Upload failed'),
        close: t('forum', 'Close'),
      },
    }
  },
  computed: {
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
  methods: {
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

        const path = await picker.pick()

        if (!path) {
          return
        }

        const fileId = extractRelativePathFromFilePicker(path)

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

      try {
        // Create a file input element
        const fileInput = document.createElement('input')
        fileInput.type = 'file'
        fileInput.style.display = 'none'

        // Handle file selection
        fileInput.addEventListener('change', async (event) => {
          const target = event.target as HTMLInputElement
          const file = target.files?.[0]

          if (file) {
            await this.uploadFile(file)
          }

          document.body.removeChild(fileInput)
        })

        // Add to DOM and click
        document.body.appendChild(fileInput)
        fileInput.click()
      } catch (error) {
        console.error('Error creating file input:', error)
      }
    },

    async uploadFile(file: File): Promise<void> {
      if (!this.textareaRef) {
        return
      }

      this.uploadFileName = file.name
      this.uploadProgress = 0
      this.uploadError = null
      this.uploadDialog = true

      try {
        // Get upload directory from user preferences
        const prefsResponse = await ocs.get('/user-preferences')
        const uploadDirectory = prefsResponse.data.upload_directory || 'Forum'

        const user = getCurrentUser()
        if (!user) {
          throw new Error('User not authenticated')
        }

        // Ensure directory exists
        await this.ensureDirectoryExists(user.uid, uploadDirectory)

        // Upload file
        const davPath = `/remote.php/dav/files/${user.uid}/${uploadDirectory}/${file.name}`
        await webDav.put(davPath, file, {
          headers: {
            'Content-Type': file.type || 'application/octet-stream',
          },
          onUploadProgress: (progressEvent) => {
            if (progressEvent.total) {
              this.uploadProgress = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            }
          },
        })

        // Insert attachment BBCode
        const state = getEditorState(this.textareaRef, this.modelValue)
        if (!state) {
          return
        }

        // Use the bbcode utility to insert the attachment tag
        const filePath = `${uploadDirectory}/${file.name}`
        const result = insertTextAtSelection(
          editorStateToSelection(state),
          `[attachment]${filePath}[/attachment]`,
        )

        // Emit the insert event
        this.$emit('insert', {
          text: result.text,
          cursorPos: result.cursorPosition,
          selectedText: '',
        })

        // Focus the editor after insertion
        const editorRef = this.textareaRef
        if (editorRef) {
          this.$nextTick(() => {
            editorRef.focus()
            setCursorPosition(editorRef, result.cursorPosition)
          })
        }

        // Close dialog on success
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
  flex-wrap: wrap;
  gap: 4px;
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
