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
      <textarea
        v-model="content"
        class="reply-textarea"
        :placeholder="strings.placeholder"
        rows="4"
        :disabled="submitting"
        @keydown.ctrl.enter="submitReply"
        @keydown.meta.enter="submitReply"
      ></textarea>

      <div class="reply-footer">
        <div class="reply-footer-left">
          <span class="hint">{{ strings.hint }}</span>
        </div>
        <div class="reply-footer-right">
          <NcButton @click="cancel" :disabled="submitting || !hasContent">
            {{ strings.cancel }}
          </NcButton>
          <NcButton @click="submitReply" :disabled="!canSubmit || submitting">
            <template v-if="submitting">
              <NcLoadingIcon :size="20" />
            </template>
            {{ strings.submit }}
          </NcButton>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { t } from '@nextcloud/l10n'
import { useCurrentUser } from '@/composables/useCurrentUser'

export default defineComponent({
  name: 'PostReplyForm',
  components: {
    NcAvatar,
    NcButton,
    NcLoadingIcon,
  },
  emits: ['submit', 'cancel'],
  setup() {
    const { userId, displayName, fetchCurrentUser } = useCurrentUser()

    // Fetch current user on mount
    fetchCurrentUser()

    return {
      userId,
      displayName,
    }
  },
  data() {
    return {
      content: '',
      submitting: false,
      strings: {
        placeholder: t('forum', 'Write your reply...'),
        hint: t('forum', 'Ctrl+Enter to submit'),
        cancel: t('forum', 'Cancel'),
        submit: t('forum', 'Post Reply'),
        confirmCancel: t('forum', 'Are you sure you want to discard your reply?'),
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
  width: 100%;
  min-height: 100px;
  padding: 12px;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  background: var(--color-main-background);
  color: var(--color-main-text);
  font-family: inherit;
  font-size: 0.95rem;
  line-height: 1.5;
  resize: vertical;
  transition: border-color 0.2s ease;

  &:focus {
    outline: none;
    border-color: var(--color-primary-element);
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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
