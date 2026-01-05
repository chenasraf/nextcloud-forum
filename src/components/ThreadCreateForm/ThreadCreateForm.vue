<template>
  <div class="thread-create-form">
    <div v-if="userId" class="form-header">
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
        <span v-if="draftStatus" :class="['draft-status', `draft-status--${draftStatus}`]">
          <ContentSaveIcon v-if="draftStatus === 'saving'" :size="16" class="saving-icon" />
          <ContentSaveCheckIcon v-else-if="draftStatus === 'saved'" :size="16" />
          <ContentSaveAlertIcon v-else-if="draftStatus === 'dirty'" :size="16" />
          {{ draftStatusText }}
        </span>
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
import { defineComponent, type PropType } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import CheckIcon from '@icons/Check.vue'
import ContentSaveIcon from '@icons/ContentSave.vue'
import ContentSaveCheckIcon from '@icons/ContentSaveCheck.vue'
import ContentSaveAlertIcon from '@icons/ContentSaveAlert.vue'
import UserInfo from '@/components/UserInfo'
import BBCodeEditor from '@/components/BBCodeEditor'
import { t } from '@nextcloud/l10n'
import { useCurrentUser } from '@/composables/useCurrentUser'

export type DraftStatus = 'saving' | 'saved' | 'dirty' | null

export default defineComponent({
  name: 'ThreadCreateForm',
  components: {
    NcButton,
    NcLoadingIcon,
    NcTextField,
    CheckIcon,
    ContentSaveIcon,
    ContentSaveCheckIcon,
    ContentSaveAlertIcon,
    UserInfo,
    BBCodeEditor,
  },
  emits: ['submit', 'cancel', 'update:title', 'update:content'],
  props: {
    draftStatus: {
      type: String as PropType<DraftStatus>,
      default: null,
    },
  },
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
        titlePlaceholder: t('forum', 'Enter thread title …'),
        contentPlaceholder: t('forum', 'Write your thread content …'),
        cancel: t('forum', 'Cancel'),
        submit: t('forum', 'Create thread'),
        confirmCancel: t('forum', 'Are you sure you want to discard this thread?'),
        draftSaving: t('forum', 'Saving draft …'),
        draftSaved: t('forum', 'Draft saved'),
        draftDirty: t('forum', 'Unsaved changes'),
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
    draftStatusText(): string {
      if (this.draftStatus === 'saving') {
        return this.strings.draftSaving
      }
      if (this.draftStatus === 'saved') {
        return this.strings.draftSaved
      }
      if (this.draftStatus === 'dirty') {
        return this.strings.draftDirty
      }
      return ''
    },
  },
  watch: {
    title(newVal: string) {
      this.$emit('update:title', newVal)
    },
    content(newVal: string) {
      this.$emit('update:content', newVal)
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

    setTitle(value: string): void {
      this.title = value
    },

    setContent(value: string): void {
      this.content = value
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
  gap: 12px;
}

.draft-status {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.85rem;
  margin-right: auto;

  &--saving {
    color: var(--color-text-maxcontrast);

    .saving-icon {
      animation: pulse 1s ease-in-out infinite;
    }
  }

  &--saved {
    color: var(--color-success-text);
  }

  &--dirty {
    color: var(--color-warning-text);
  }
}

@keyframes pulse {
  0%,
  100% {
    opacity: 0.4;
  }
  50% {
    opacity: 1;
  }
}

.hint {
  font-size: 0.85rem;
  color: var(--color-text-maxcontrast);
  font-style: italic;
}
</style>
