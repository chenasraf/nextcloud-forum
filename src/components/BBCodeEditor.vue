<template>
  <div
    class="bbcode-editor-container"
    @dragenter="handleDragEnter"
    @dragover="handleDragOver"
    @dragleave="handleDragLeave"
    @drop="handleDrop"
  >
    <BBCodeToolbar ref="toolbar" :textarea-ref="textareaElement" @insert="handleBBCodeInsert" />
    <NcTextArea
      :model-value="modelValue"
      :placeholder="placeholder"
      :rows="rows"
      :disabled="disabled"
      @update:model-value="$emit('update:modelValue', $event)"
      @keydown="$emit('keydown', $event)"
      class="bbcode-editor-textarea"
      ref="textarea"
    />
    <NcNoteCard v-if="hasAttachmentBBCode" type="warning" class="attachment-disclaimer">
      <span v-html="strings.attachmentDisclaimer"></span>
    </NcNoteCard>

    <!-- Drag and Drop Overlay -->
    <div v-if="isDragging" class="drag-overlay">
      <div class="drag-overlay-content">
        <UploadIcon :size="48" />
        <p class="drag-overlay-text">{{ strings.dropFileHere }}</p>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import BBCodeToolbar from './BBCodeToolbar.vue'
import UploadIcon from '@icons/Upload.vue'
import { t } from '@nextcloud/l10n'

export default defineComponent({
  name: 'BBCodeEditor',
  components: {
    NcTextArea,
    NcNoteCard,
    BBCodeToolbar,
    UploadIcon,
  },
  props: {
    modelValue: {
      type: String,
      required: true,
    },
    placeholder: {
      type: String,
      default: '',
    },
    rows: {
      type: Number,
      default: 4,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    minHeight: {
      type: String,
      default: '9.1875rem',
    },
  },
  emits: ['update:modelValue', 'keydown'],
  data() {
    return {
      textareaElement: null as HTMLTextAreaElement | null,
      isDragging: false,
      dragCounter: 0,
      strings: {
        attachmentDisclaimer: t(
          'forum',
          "{bStart}Please note:{bEnd} Attached files will be visible to anyone in the forum, regardless of the file's sharing settings.",
          { bStart: '<strong>', bEnd: '</strong>' },
          { escape: false },
        ),
        dropFileHere: t('forum', 'Drop file here to upload'),
      },
    }
  },
  computed: {
    hasAttachmentBBCode(): boolean {
      return /\[attachment[^\]]*\]/i.test(this.modelValue)
    },
  },
  mounted() {
    this.updateTextareaRef()
  },
  updated() {
    this.updateTextareaRef()
  },
  methods: {
    updateTextareaRef(): void {
      const textarea = this.$refs.textarea as any
      if (textarea?.$el?.querySelector('textarea')) {
        this.textareaElement = textarea.$el.querySelector('textarea')
      }
    },

    handleBBCodeInsert(data: { text: string; cursorPos: number }): void {
      // Update the content with the new text
      this.$emit('update:modelValue', data.text)
      // The cursor position is handled by the BBCodeToolbar component
    },

    focus(): void {
      // Focus the textarea
      const textarea = this.$refs.textarea as any
      if (textarea?.$el?.querySelector('textarea')) {
        textarea.$el.querySelector('textarea').focus()
      }
    },

    handleDragEnter(event: DragEvent): void {
      event.preventDefault()
      event.stopPropagation()

      // Only show overlay for file drags
      if (event.dataTransfer?.types.includes('Files')) {
        this.dragCounter++
        this.isDragging = true
      }
    },

    handleDragOver(event: DragEvent): void {
      event.preventDefault()
      event.stopPropagation()

      // Set the dropEffect to copy
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy'
      }
    },

    handleDragLeave(event: DragEvent): void {
      event.preventDefault()
      event.stopPropagation()

      this.dragCounter--
      if (this.dragCounter === 0) {
        this.isDragging = false
      }
    },

    async handleDrop(event: DragEvent): Promise<void> {
      event.preventDefault()
      event.stopPropagation()

      this.isDragging = false
      this.dragCounter = 0

      const files = event.dataTransfer?.files
      if (!files || files.length === 0) {
        return
      }

      // Get the first file
      const file = files[0]

      // Call the upload method from BBCodeToolbar
      const toolbar = this.$refs.toolbar as any
      if (toolbar && toolbar.uploadFile) {
        await toolbar.uploadFile(file)
      }
    },
  },
})
</script>

<style scoped lang="scss">
.bbcode-editor-container {
  position: relative;
  background: var(--color-background-hover);
  border: 1px solid var(--color-border);
  border-radius: 6px;
  padding: 4px;
}

.bbcode-editor-textarea {
  margin-top: 0;

  :deep(.textarea__main-wrapper) {
    min-height: v-bind(minHeight) !important;
    height: unset !important;
  }

  :deep(textarea) {
    resize: vertical !important;
    min-height: v-bind(minHeight) !important;
  }
}

.attachment-disclaimer {
  margin-top: 8px;
}

.drag-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--color-main-background);
  border: 3px dashed var(--color-primary-element);
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  pointer-events: none;
  opacity: 0.95;

  .drag-overlay-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    color: var(--color-primary-element);

    .drag-overlay-text {
      margin: 0;
      font-size: 1.2rem;
      font-weight: 600;
    }
  }
}
</style>
