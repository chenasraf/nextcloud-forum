<template>
  <div class="post-edit-form">
    <NcTextArea v-model="content" :placeholder="strings.placeholder" :rows="6" :disabled="submitting"
      @keydown.ctrl.enter="submitEdit" @keydown.meta.enter="submitEdit" class="edit-textarea" ref="textarea" />

    <div class="edit-footer">
      <div class="edit-footer-left">
        <span class="hint">{{ strings.hint }}</span>
      </div>
      <div class="edit-footer-right">
        <NcButton @click="cancel" :disabled="submitting">
          {{ strings.cancel }}
        </NcButton>
        <NcButton @click="submitEdit" :disabled="!canSubmit || submitting" type="primary">
          <template v-if="submitting">
            <NcLoadingIcon :size="20" />
          </template>
          {{ strings.save }}
        </NcButton>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import { t } from '@nextcloud/l10n'

export default defineComponent({
  name: 'PostEditForm',
  components: {
    NcButton,
    NcLoadingIcon,
    NcTextArea,
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
        placeholder: t('forum', 'Edit your post...'),
        hint: t('forum', 'Ctrl+Enter to save'),
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
.post-edit-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.edit-textarea {
  min-height: 8rem;
  resize: vertical;

  :global(.textarea__main-wrapper),
  textarea {
    min-height: calc(var(--default-clickable-area) * 3);
    height: unset !important;
  }
}

.edit-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.edit-footer-left {
  flex: 1;
}

.edit-footer-right {
  display: flex;
  gap: 8px;
}

.hint {
  font-size: 0.85rem;
  color: var(--color-text-maxcontrast);
  font-style: italic;
}
</style>
