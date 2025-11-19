<template>
  <div class="bbcode-editor-container">
    <BBCodeToolbar :textarea-ref="textareaElement" @insert="handleBBCodeInsert" />
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
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import BBCodeToolbar from './BBCodeToolbar.vue'
import { t } from '@nextcloud/l10n'

export default defineComponent({
  name: 'BBCodeEditor',
  components: {
    NcTextArea,
    NcNoteCard,
    BBCodeToolbar,
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
      default: '6.125rem',
    },
  },
  emits: ['update:modelValue', 'keydown'],
  data() {
    return {
      textareaElement: null as HTMLTextAreaElement | null,
      strings: {
        attachmentDisclaimer: t(
          'forum',
          "{bStart}Please note:{bEnd} Attached files will be visible to anyone in the forum, regardless of the file's sharing settings.",
          { bStart: '<strong>', bEnd: '</strong>' },
          { escape: false },
        ),
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
  },
})
</script>

<style scoped lang="scss">
.bbcode-editor-container {
  background: var(--color-background-hover);
  border: 1px solid var(--color-border);
  border-radius: 6px;
  padding: 4px;
}

.bbcode-editor-textarea {
  resize: vertical;
  margin-top: 0;

  :global(.textarea__main-wrapper),
  textarea {
    min-height: v-bind(minHeight);
    height: unset !important;
  }
}

.attachment-disclaimer {
  margin-top: 8px;
}
</style>
