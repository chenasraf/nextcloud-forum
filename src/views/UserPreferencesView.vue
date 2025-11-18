<template>
  <PageWrapper>
    <template #toolbar>
      <AppToolbar>
        <template #left>
          <NcButton @click="goBack">
            <template #icon>
              <ArrowLeftIcon :size="20" />
            </template>
            {{ strings.back }}
          </NcButton>
        </template>
      </AppToolbar>
    </template>

    <div class="user-preferences-view">
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
          <NcButton @click="loadPreferences">{{ strings.retry }}</NcButton>
        </template>
      </NcEmptyContent>

      <!-- Preferences form -->
      <div v-else class="preferences-form">
        <!-- Thread Subscriptions Section -->
        <div class="form-section">
          <h3>{{ strings.subscriptionsTitle }}</h3>
          <p class="section-description muted">{{ strings.subscriptionsDesc }}</p>

          <div class="preference-item">
            <NcCheckboxRadioSwitch v-model="formData.auto_subscribe_created_threads">
              {{ strings.autoSubscribeLabel }}
            </NcCheckboxRadioSwitch>
            <p class="preference-hint">{{ strings.autoSubscribeHint }}</p>
          </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
          <NcButton variant="primary" :disabled="saving || !hasChanges" @click="savePreferences">
            <template #icon>
              <NcLoadingIcon v-if="saving" :size="20" />
              <CheckIcon v-else :size="20" />
            </template>
            {{ strings.save }}
          </NcButton>
          <NcButton :disabled="saving || !hasChanges" @click="resetForm">
            {{ strings.cancel }}
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
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import AppToolbar from '@/components/AppToolbar.vue'
import PageWrapper from '@/components/PageWrapper.vue'
import PageHeader from '@/components/PageHeader.vue'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import CheckIcon from '@icons/Check.vue'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'

interface UserPreferences {
  auto_subscribe_created_threads: boolean
}

export default defineComponent({
  name: 'UserPreferencesView',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcCheckboxRadioSwitch,
    AppToolbar,
    PageWrapper,
    PageHeader,
    ArrowLeftIcon,
    CheckIcon,
  },
  data() {
    return {
      loading: false,
      saving: false,
      saveSuccess: false,
      error: null as string | null,
      originalData: {
        auto_subscribe_created_threads: true,
      } as UserPreferences,
      formData: {
        auto_subscribe_created_threads: true,
      } as UserPreferences,

      strings: {
        title: t('forum', 'Preferences'),
        subtitle: t('forum', 'Customize your forum experience'),
        back: t('forum', 'Back'),
        loading: t('forum', 'Loading preferences…'),
        errorTitle: t('forum', 'Error loading preferences'),
        retry: t('forum', 'Retry'),
        subscriptionsTitle: t('forum', 'Notifications'),
        subscriptionsDesc: t('forum', 'Configure how you receive notifications'),
        autoSubscribeLabel: t('forum', 'Auto-subscribe to threads I create'),
        autoSubscribeHint: t(
          'forum',
          'When enabled, you will automatically receive notifications for replies to threads you create',
        ),
        save: t('forum', 'Save'),
        cancel: t('forum', 'Cancel'),
        saveSuccess: t('forum', 'Preferences saved successfully'),
      },
    }
  },
  computed: {
    hasChanges(): boolean {
      return (
        this.formData.auto_subscribe_created_threads !==
        this.originalData.auto_subscribe_created_threads
      )
    },
  },
  created() {
    this.loadPreferences()
  },
  methods: {
    async loadPreferences(): Promise<void> {
      try {
        this.loading = true
        this.error = null

        const response = await ocs.get<UserPreferences>('/user-preferences')
        this.originalData = { ...response.data }
        this.formData = { ...response.data }
      } catch (e) {
        console.error('Failed to load preferences', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    async savePreferences(): Promise<void> {
      try {
        this.saving = true
        this.saveSuccess = false

        await ocs.put('/user-preferences', this.formData)

        this.originalData = { ...this.formData }
        this.saveSuccess = true

        // Hide success message after 3 seconds
        setTimeout(() => {
          this.saveSuccess = false
        }, 3000)
      } catch (e) {
        console.error('Failed to save preferences', e)
        this.error = (e as Error).message || t('forum', 'Failed to save preferences')
      } finally {
        this.saving = false
      }
    },

    resetForm(): void {
      this.formData = { ...this.originalData }
      this.saveSuccess = false
    },

    goBack(): void {
      this.$router.back()
    },
  },
})
</script>

<style scoped lang="scss">
.user-preferences-view {
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

  .preferences-form {

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

      .section-description {
        margin: 0 0 20px 0;
        font-size: 0.9rem;
      }
    }

    .preference-item {
      padding: 12px 0;

      .preference-hint {
        margin: 8px 0 0 32px;
        font-size: 0.85rem;
        color: var(--color-text-maxcontrast);
        line-height: 1.4;
      }
    }

    .form-actions {
      display: flex;
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
