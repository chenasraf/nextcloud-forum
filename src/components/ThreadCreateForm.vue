<template>
  <div class="thread-create-form">
    <div class="form-header">
      <UserInfo
        :user-id="userId"
        :display-name="displayName"
        :avatar-size="40"
        :clickable="false"
      />
    </div>

    <div class="form-body">
      <NcTextField
        v-model="title"
        :label="strings.titleLabel"
        :placeholder="strings.titlePlaceholder"
        :disabled="submitting"
        @keydown.enter="focusContent"
        class="title-input"
      />

      <BBCodeEditor
        v-model="content"
        :placeholder="strings.contentPlaceholder"
        :rows="6"
        :disabled="submitting"
        min-height="8rem"
        @keydown.ctrl.enter="submitThread"
        @keydown.meta.enter="submitThread"
        ref="editor"
      />

      <div class="form-footer">
        <NcButton @click="cancel" :disabled="submitting">
          {{ strings.cancel }}
        </NcButton>
        <NcButton @click="submitThread" :disabled="!canSubmit || submitting" variant="primary">
          <template #icon>
            <NcLoadingIcon v-if="submitting" :size="20" />
            <CheckIcon v-else :size="20" />
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
import NcTextField from '@nextcloud/vue/components/NcTextField'
import CheckIcon from '@icons/Check.vue'
import UserInfo from './UserInfo.vue'
import BBCodeEditor from './BBCodeEditor.vue'
import { t } from '@nextcloud/l10n'
import { useCurrentUser } from '@/composables/useCurrentUser'

export default defineComponent({
  name: 'ThreadCreateForm',
  components: {
    NcButton,
    NcLoadingIcon,
    NcTextField,
    CheckIcon,
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
      title: '',
      content: '',
      submitting: false,
      strings: {
        titleLabel: t('forum', 'Title'),
        titlePlaceholder: t('forum', 'Enter thread title...'),
        contentPlaceholder: t('forum', 'Write your first post...'),
        cancel: t('forum', 'Cancel'),
        submit: t('forum', 'Create Thread'),
        confirmCancel: t('forum', 'Are you sure you want to discard this thread?'),
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
      const editor = this.$refs.editor as any
      if (editor?.focus) {
        editor.focus()
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

.form-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
}

.hint {
  font-size: 0.85rem;
  color: var(--color-text-maxcontrast);
  font-style: italic;
}
</style>
