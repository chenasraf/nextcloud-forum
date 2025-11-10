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
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import BBCodeToolbar from './BBCodeToolbar.vue'

export default defineComponent({
  name: 'BBCodeEditor',
  components: {
    NcTextArea,
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
    }
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
</style>
