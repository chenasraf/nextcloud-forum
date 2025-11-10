<template>
  <div class="post-reply-form">
    <div class="reply-header">
      <div class="user-info">
        <NcAvatar v-if="userId" :user="userId" :size="40" />
        <NcAvatar v-else :display-name="displayName" :size="40" />
        <span class="user-name">{{ displayName }}</span>
      </div>
    </div>

    <div class="reply-body">
      <NcTextArea
        v-model="content"
        :placeholder="strings.placeholder"
        :rows="4"
        :disabled="submitting"
        @keydown.ctrl.enter="submitReply"
        @keydown.meta.enter="submitReply"
        class="reply-textarea"
        ref="textarea"
      />

      <div class="reply-footer">
        <div class="reply-footer-left">
          <NcButton variant="tertiary" @click="showHelp = true">
            <template #icon>
              <HelpCircleIcon :size="20" />
            </template>
            {{ strings.help }}
          </NcButton>
        </div>
        <div class="reply-footer-right">
          <NcButton @click="cancel" :disabled="submitting || !hasContent">
            {{ strings.cancel }}
          </NcButton>
          <NcButton @click="submitReply" :disabled="!canSubmit || submitting" variant="primary">
            <template #icon>
              <NcLoadingIcon v-if="submitting" :size="20" />
              <SendIcon v-else :size="20" />
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
import HelpCircleIcon from '@icons/HelpCircle.vue'
import SendIcon from '@icons/Send.vue'
import BBCodeHelpDialog from './BBCodeHelpDialog.vue'
import { t } from '@nextcloud/l10n'
import { useCurrentUser } from '@/composables/useCurrentUser'

export default defineComponent({
  name: 'PostReplyForm',
  components: {
    NcAvatar,
    NcButton,
    NcLoadingIcon,
    NcTextArea,
    HelpCircleIcon,
    SendIcon,
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
      content: '',
      submitting: false,
      showHelp: false,
      strings: {
        placeholder: t('forum', 'Write your reply...'),
        cancel: t('forum', 'Cancel'),
        submit: t('forum', 'Post Reply'),
        confirmCancel: t('forum', 'Are you sure you want to discard your reply?'),
        help: t('forum', 'BBCode Help'),
      },
    }
  },
  computed: {
    canSubmit(): boolean {
      return this.content.trim().length > 0
    },
    hasContent(): boolean {
      return this.content.trim().length > 0
    },
  },
  methods: {
    async submitReply(): Promise<void> {
      if (!this.canSubmit || this.submitting) {
        return
      }

      this.submitting = true
      this.$emit('submit', this.content.trim())
    },

    clear(): void {
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

      this.content = ''
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
.post-reply-form {
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 16px;
  background: var(--color-main-background);
  margin-top: 24px;
}

.reply-header {
  margin-bottom: 12px;
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

.reply-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.reply-textarea {
  min-height: 6.125rem;
  resize: vertical;

  :global(.textarea__main-wrapper),
  textarea {
    min-height: calc(var(--default-clickable-area) * 2);
    height: unset !important;
  }
}

.reply-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.reply-footer-left {
  flex: 1;
}

.reply-footer-right {
  display: flex;
  gap: 8px;
}

.hint {
  font-size: 0.85rem;
  color: var(--color-text-maxcontrast);
  font-style: italic;
}
</style>
