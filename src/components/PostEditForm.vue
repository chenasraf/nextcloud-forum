<template>
  <div class="post-edit-form">
    <BBCodeEditor
      v-model="content"
      :placeholder="strings.placeholder"
      :rows="6"
      :disabled="submitting"
      min-height="8rem"
      @keydown.ctrl.enter="submitEdit"
      @keydown.meta.enter="submitEdit"
      ref="editor"
    />

    <div class="edit-footer">
      <NcButton @click="cancel" :disabled="submitting">
        {{ strings.cancel }}
      </NcButton>
      <NcButton @click="submitEdit" :disabled="!canSubmit || submitting" variant="primary">
        <template v-if="submitting" #icon>
          <NcLoadingIcon :size="20" />
        </template>
        {{ strings.save }}
      </NcButton>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import BBCodeEditor from './BBCodeEditor.vue'
import { t } from '@nextcloud/l10n'

export default defineComponent({
  name: 'PostEditForm',
  components: {
    NcButton,
    NcLoadingIcon,
    BBCodeEditor,
  },
  props: {
    initialContent: {
      type: String,
      required: true,
    },
  },
  emits: ['submit', 'cancel'],
  data() {
    return {
      content: this.initialContent,
      submitting: false,
      strings: {
        placeholder: t('forum', 'Edit your reply …'),
        cancel: t('forum', 'Cancel'),
        save: t('forum', 'Save'),
        confirmCancel: t('forum', 'Are you sure you want to discard your changes?'),
      },
    }
  },
  computed: {
    canSubmit(): boolean {
      return this.content.trim().length > 0 && this.content !== this.initialContent
    },
    hasChanges(): boolean {
      return this.content !== this.initialContent
    },
  },
  methods: {
    async submitEdit(): Promise<void> {
      if (!this.canSubmit || this.submitting) {
        return
      }

      this.submitting = true
      this.$emit('submit', this.content.trim())
    },

    setSubmitting(value: boolean): void {
      this.submitting = value
    },

    cancel(): void {
      // Only confirm if there are changes
      if (this.hasChanges) {
        // eslint-disable-next-line no-alert
        if (!confirm(this.strings.confirmCancel)) {
          return
        }
      }

      this.$emit('cancel')
    },

    focus(): void {
      // Focus the editor
      const editor = this.$refs.editor as any
      if (editor?.focus) {
        editor.focus()
      }
    },
  },
})
</script>

<style scoped lang="scss">
.post-edit-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.edit-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 12px;
}

.hint {
  font-size: 0.85rem;
  color: var(--color-text-maxcontrast);
  font-style: italic;
}
</style>
