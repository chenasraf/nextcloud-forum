<template>
  <PageWrapper>
    <div class="admin-general-settings">
      <PageHeader :title="strings.title" :subtitle="strings.subtitle" />

      <!-- Loading state -->
      <div v-if="loading" class="center mt-16">
        <NcLoadingIcon :size="32" />
        <span class="muted ml-8">{{ strings.loading }}</span>
      </div>

      <!-- Error state -->
      <NcEmptyContent
        v-else-if="error"
        :title="strings.errorTitle"
        :description="error"
        class="mt-16"
      >
        <template #action>
          <NcButton @click="loadSettings">{{ strings.retry }}</NcButton>
        </template>
      </NcEmptyContent>

      <!-- Settings form -->
      <div v-else class="settings-form">
        <div class="form-section">
          <h3>{{ strings.appearanceTitle }}</h3>
          <p class="muted">{{ strings.appearanceDesc }}</p>

          <div class="form-group">
            <label for="forum-title">{{ strings.forumTitle }}</label>
            <NcTextField
              id="forum-title"
              v-model.trim="formData.title"
              :placeholder="strings.forumTitlePlaceholder"
              :maxlength="100"
            />
            <p class="hint">{{ strings.forumTitleHint }}</p>
          </div>

          <div class="form-group">
            <label for="forum-subtitle">{{ strings.forumSubtitle }}</label>
            <NcTextArea
              id="forum-subtitle"
              v-model.trim="formData.subtitle"
              :placeholder="strings.forumSubtitlePlaceholder"
              :rows="3"
              :maxlength="500"
            />
            <p class="hint">{{ strings.forumSubtitleHint }}</p>
          </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
          <NcButton :disabled="saving || !hasChanges" @click="resetForm">
            {{ strings.cancel }}
          </NcButton>
          <NcButton variant="primary" :disabled="saving || !hasChanges" @click="saveSettings">
            <template #icon>
              <NcLoadingIcon v-if="saving" :size="20" />
              <CheckIcon v-else :size="20" />
            </template>
            {{ strings.save }}
          </NcButton>
        </div>

        <!-- Success message -->
        <div v-if="saveSuccess" class="success-message">
          <CheckIcon :size="20" />
          <span>{{ strings.saveSuccess }}</span>
        </div>
      </div>
    </div>
  </PageWrapper>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import PageWrapper from '@/components/PageWrapper.vue'
import PageHeader from '@/components/PageHeader.vue'
import CheckIcon from '@icons/Check.vue'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'

interface Settings {
  title: string
  subtitle: string
}

export default defineComponent({
  name: 'AdminGeneralSettings',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcTextField,
    NcTextArea,
    PageHeader,
    PageWrapper,
    CheckIcon,
  },
  data() {
    return {
      loading: false,
      saving: false,
      saveSuccess: false,
      error: null as string | null,
      originalData: {
        title: '',
        subtitle: '',
      } as Settings,
      formData: {
        title: '',
        subtitle: '',
      } as Settings,

      strings: {
        title: t('forum', 'General settings'),
        subtitle: t('forum', 'Configure general forum settings'),
        loading: t('forum', 'Loading settings …'),
        errorTitle: t('forum', 'Error loading settings'),
        retry: t('forum', 'Retry'),
        appearanceTitle: t('forum', 'Appearance'),
        appearanceDesc: t('forum', 'Customize how your forum looks to users'),
        forumTitle: t('forum', 'Forum title'),
        forumTitlePlaceholder: t('forum', 'Forum'),
        forumTitleHint: t('forum', 'Displayed at the top of the forum home page'),
        forumSubtitle: t('forum', 'Forum subtitle'),
        forumSubtitlePlaceholder: t('forum', 'Welcome to the forum'),
        forumSubtitleHint: t('forum', 'A brief description shown below the title'),
        save: t('forum', 'Save'),
        cancel: t('forum', 'Cancel'),
        saveSuccess: t('forum', 'Settings saved'),
      },
    }
  },
  computed: {
    hasChanges(): boolean {
      return (
        this.formData.title !== this.originalData.title ||
        this.formData.subtitle !== this.originalData.subtitle
      )
    },
  },
  created() {
    this.loadSettings()
  },
  methods: {
    async loadSettings(): Promise<void> {
      try {
        this.loading = true
        this.error = null

        const response = await ocs.get<Settings>('/admin/settings')
        this.originalData = { ...response.data }
        this.formData = { ...response.data }
      } catch (e) {
        console.error('Failed to load settings', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    async saveSettings(): Promise<void> {
      try {
        this.saving = true
        this.saveSuccess = false

        await ocs.put('/admin/settings', this.formData)

        this.originalData = { ...this.formData }
        this.saveSuccess = true

        // Hide success message after 3 seconds
        setTimeout(() => {
          this.saveSuccess = false
        }, 3000)
      } catch (e) {
        console.error('Failed to save settings', e)
        this.error = (e as Error).message || t('forum', 'Failed to save settings')
      } finally {
        this.saving = false
      }
    },

    resetForm(): void {
      this.formData = { ...this.originalData }
      this.saveSuccess = false
    },
  },
})
</script>

<style scoped lang="scss">
.admin-general-settings {
  .muted {
    color: var(--color-text-maxcontrast);
    opacity: 0.7;
  }

  .mt-16 {
    margin-top: 16px;
  }

  .ml-8 {
    margin-left: 8px;
  }

  .center {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .page-header {
    margin-bottom: 24px;

    h2 {
      margin: 0 0 6px 0;
    }
  }

  .settings-form {

    .form-section {
      margin-bottom: 32px;
      padding: 24px;
      background: var(--color-main-background);
      border: 1px solid var(--color-border);
      border-radius: 8px;

      h3 {
        margin: 0 0 8px 0;
        font-size: 1.1rem;
        font-weight: 600;
      }

      >p {
        margin: 0 0 20px 0;
        font-size: 0.9rem;
      }
    }

    .form-group {
      margin-bottom: 24px;

      &:last-child {
        margin-bottom: 0;
      }

      label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--color-main-text);
      }

      .hint {
        margin: 6px 0 0 0;
        font-size: 0.85rem;
        color: var(--color-text-maxcontrast);
      }
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      align-items: center;
    }

    .success-message {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 16px;
      padding: 12px 16px;
      background: var(--color-success-light);
      color: var(--color-success-dark);
      border-radius: 6px;
      font-weight: 500;
    }
  }
}
</style>
