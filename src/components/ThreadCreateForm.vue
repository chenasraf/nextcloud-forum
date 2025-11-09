<template>
  <div class="thread-create-form">
    <div class="form-header">
      <div class="user-info">
        <NcAvatar v-if="userId" :user="userId" :size="40" />
        <NcAvatar v-else :display-name="displayName" :size="40" />
        <span class="user-name">{{ displayName }}</span>
      </div>
    </div>

    <div class="form-body">
      <NcTextField v-model="title" :label="strings.titleLabel" :placeholder="strings.titlePlaceholder"
        :disabled="submitting" @keydown.enter="focusContent" class="title-input" />

      <NcTextArea v-model="content" :placeholder="strings.contentPlaceholder" :rows="6" :disabled="submitting"
        @keydown.ctrl.enter="submitThread" @keydown.meta.enter="submitThread" class="content-textarea"
        ref="contentTextarea" />

      <div class="form-footer">
        <div class="form-footer-left">
          <NcButton type="tertiary" @click="showHelp = true">
            <template #icon>
              <HelpCircleIcon :size="20" />
            </template>
            {{ strings.help }}
          </NcButton>
        </div>
        <div class="form-footer-right">
          <NcButton @click="cancel" :disabled="submitting">
            {{ strings.cancel }}
          </NcButton>
          <NcButton @click="submitThread" :disabled="!canSubmit || submitting" type="primary">
            <template v-if="submitting">
              <NcLoadingIcon :size="20" />
            </template>
            {{ strings.submit }}
          </NcButton>
        </div>
      </div>
    </div>

    <!-- BBCode Help Dialog -->
    <BBCodeHelpDialog v-model:open="showHelp" />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import HelpCircleIcon from '@icons/HelpCircle.vue'
import BBCodeHelpDialog from './BBCodeHelpDialog.vue'
import { t } from '@nextcloud/l10n'
import { useCurrentUser } from '@/composables/useCurrentUser'

export default defineComponent({
  name: 'ThreadCreateForm',
  components: {
    NcAvatar,
    NcButton,
    NcLoadingIcon,
    NcTextArea,
    NcTextField,
    HelpCircleIcon,
    BBCodeHelpDialog,
  },
  emits: ['submit', 'cancel'],
  setup() {
    const { userId, displayName } = useCurrentUser()

    return {
      userId,
      displayName,
    }
  },
  data() {
    return {
      title: '',
      content: '',
      submitting: false,
      showHelp: false,
      strings: {
        titleLabel: t('forum', 'Title'),
        titlePlaceholder: t('forum', 'Enter thread title...'),
        contentPlaceholder: t('forum', 'Write your first post...'),
        cancel: t('forum', 'Cancel'),
        submit: t('forum', 'Create Thread'),
        confirmCancel: t('forum', 'Are you sure you want to discard this thread?'),
        help: t('forum', 'BBCode Help'),
      },
    }
  },
  computed: {
    canSubmit(): boolean {
      return this.title.trim().length > 0 && this.content.trim().length > 0
    },
    hasContent(): boolean {
      return this.title.trim().length > 0 || this.content.trim().length > 0
    },
  },
  methods: {
    async submitThread(): Promise<void> {
      if (!this.canSubmit || this.submitting) {
        return
      }

      this.submitting = true
      this.$emit('submit', {
        title: this.title.trim(),
        content: this.content.trim(),
      })
    },

    clear(): void {
      this.title = ''
      this.content = ''
      this.submitting = false
    },

    setSubmitting(value: boolean): void {
      this.submitting = value
    },

    cancel(): void {
      // Only confirm if there's content to discard
      if (this.hasContent) {
        // eslint-disable-next-line no-alert
        if (!confirm(this.strings.confirmCancel)) {
          return
        }
      }

      this.title = ''
      this.content = ''
      this.$emit('cancel')
    },

    focusContent(): void {
      // Move focus to content area when Enter is pressed in title field
      const textarea = this.$refs.contentTextarea as any
      if (textarea?.$el?.querySelector('textarea')) {
        textarea.$el.querySelector('textarea').focus()
      }
    },
  },
})
</script>

<style scoped lang="scss">
.thread-create-form {
  padding: 16px;
  background: var(--color-main-background);
}

.form-header {
  margin-bottom: 16px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-name {
  font-weight: 600;
  color: var(--color-main-text);
  font-size: 1rem;
}

.form-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.title-input {
  :global(.input-field__input) {
    font-size: 1.1rem;
    font-weight: 500;
  }
}

.content-textarea {
  min-height: 8rem;
  resize: vertical;

  :global(.textarea__main-wrapper),
  textarea {
    min-height: calc(var(--default-clickable-area) * 3);
    height: unset !important;
  }
}

.form-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.form-footer-left {
  flex: 1;
}

.form-footer-right {
  display: flex;
  gap: 8px;
}

.hint {
  font-size: 0.85rem;
  color: var(--color-text-maxcontrast);
  font-style: italic;
}
</style>
