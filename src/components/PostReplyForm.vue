<template>
  <div class="post-reply-form">
    <div v-if="userId" class="reply-header">
      <UserInfo
        :user-id="userId"
        :display-name="displayName"
        :avatar-size="40"
        :clickable="false"
      />
    </div>

    <div class="reply-body">
      <BBCodeEditor
        v-model="content"
        :placeholder="strings.placeholder"
        :rows="4"
        :disabled="submitting"
        min-height="6.125rem"
        @keydown.ctrl.enter="submitReply"
        @keydown.meta.enter="submitReply"
        ref="editor"
      />

      <div class="reply-footer">
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
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import SendIcon from '@icons/Send.vue'
import UserInfo from './UserInfo.vue'
import BBCodeEditor from './BBCodeEditor.vue'
import { t } from '@nextcloud/l10n'
import { useCurrentUser } from '@/composables/useCurrentUser'

export default defineComponent({
  name: 'PostReplyForm',
  components: {
    NcButton,
    NcLoadingIcon,
    SendIcon,
    UserInfo,
    BBCodeEditor,
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
      strings: {
        placeholder: t('forum', 'Write your reply …'),
        cancel: t('forum', 'Cancel'),
        submit: t('forum', 'Submit reply'),
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

    focus(): void {
      // Focus the editor
      const editor = this.$refs.editor as any
      if (editor?.focus) {
        editor.focus()
      }
    },

    setQuotedContent(contentRaw: string): void {
      // Set the textarea content with a quoted version of the provided content
      this.content = `[quote]${contentRaw}[/quote]\n`
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

.reply-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.reply-footer {
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
