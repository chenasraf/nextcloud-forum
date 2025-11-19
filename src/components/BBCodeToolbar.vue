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

    <NcEmojiPicker @select="handleEmojiSelect">
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
    </NcEmojiPicker>

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
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmojiPicker from '@nextcloud/vue/components/NcEmojiPicker'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
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
import EmoticonIcon from '@icons/Emoticon.vue'
import HelpCircleIcon from '@icons/HelpCircle.vue'
import BBCodeHelpDialog from './BBCodeHelpDialog.vue'
import { t } from '@nextcloud/l10n'

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
    NcEmojiPicker,
    BBCodeHelpDialog,
    EmoticonIcon,
    HelpCircleIcon,
  },
  props: {
    textareaRef: {
      type: Object as PropType<HTMLTextAreaElement | null>,
      default: null,
    },
  },
  emits: ['insert'],
  data() {
    return {
      showHelp: false,
      strings: {
        helpLabel: t('forum', 'BBCode Help'),
        emojiLabel: t('forum', 'Insert emoji'),
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
        {
          tag: 'attachment',
          label: 'Attachment',
          icon: PaperclipIcon,
          template: '[attachment]{text}[/attachment]',
          handler: this.handleAttachment,
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

      if (!this.textareaRef) {
        return
      }

      const textarea = this.textareaRef
      const start = textarea.selectionStart
      const end = textarea.selectionEnd
      const selectedText = textarea.value.substring(start, end)
      const beforeText = textarea.value.substring(0, start)
      const afterText = textarea.value.substring(end)

      let insertText = ''
      let value = ''
      let contentText = selectedText

      // If the button requires a value (like url, color, size, font), prompt the user
      if (button.hasValue) {
        // eslint-disable-next-line no-alert
        value = prompt(`Enter ${button.label} value:`, button.placeholder || '') || ''
        if (!value) {
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

      // Generate the BBCode text
      insertText = button.template
        .replace('{value}', value)
        .replace('{text}', contentText || button.placeholder || '')

      // Calculate new cursor position
      const newText = beforeText + insertText + afterText
      const cursorPos = beforeText.length + insertText.length

      // Emit the insert event so the parent can update the model
      this.$emit('insert', {
        text: newText,
        cursorPos,
        selectedText,
      })

      // Focus the textarea after insertion
      this.$nextTick(() => {
        textarea.focus()
        textarea.setSelectionRange(cursorPos, cursorPos)
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

        // Extract relative path from the full path
        // File picker returns: /username/files/path/to/file.pdf
        // We need: path/to/file.pdf (relative to user's files directory)
        let relativePath = path

        // Remove the leading /username/files/ part
        const pathParts = path.split('/')
        if (pathParts.length >= 3 && pathParts[2] === 'files') {
          // Remove first 3 parts: ['', 'username', 'files']
          relativePath = pathParts.slice(3).join('/')
        }

        const fileId = relativePath

        const textarea = this.textareaRef
        const start = textarea.selectionStart
        const end = textarea.selectionEnd
        const beforeText = textarea.value.substring(0, start)
        const afterText = textarea.value.substring(end)

        const insertText = `[attachment]${fileId}[/attachment]`
        const newText = beforeText + insertText + afterText
        const cursorPos = beforeText.length + insertText.length

        // Emit the insert event so the parent can update the model
        this.$emit('insert', {
          text: newText,
          cursorPos,
          selectedText: '',
        })

        // Focus the textarea after insertion
        this.$nextTick(() => {
          textarea.focus()
          textarea.setSelectionRange(cursorPos, cursorPos)
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
      if (!this.textareaRef) {
        return
      }

      const textarea = this.textareaRef
      const start = textarea.selectionStart
      const end = textarea.selectionEnd
      const beforeText = textarea.value.substring(0, start)
      const afterText = textarea.value.substring(end)

      const newText = beforeText + emoji + afterText
      const cursorPos = beforeText.length + emoji.length

      // Emit the insert event so the parent can update the model
      this.$emit('insert', {
        text: newText,
        cursorPos,
        selectedText: '',
      })

      // Focus the textarea after insertion
      this.$nextTick(() => {
        textarea.focus()
        textarea.setSelectionRange(cursorPos, cursorPos)
      })
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

.bbcode-help-button {
  margin-left: auto;
}
</style>
