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
              {{ strings.autoSubscribeCreatedLabel }}
            </NcCheckboxRadioSwitch>
            <p class="preference-hint">{{ strings.autoSubscribeCreatedHint }}</p>
          </div>

          <div class="preference-item">
            <NcCheckboxRadioSwitch v-model="formData.auto_subscribe_replied_threads">
              {{ strings.autoSubscribeRepliedLabel }}
            </NcCheckboxRadioSwitch>
            <p class="preference-hint">{{ strings.autoSubscribeRepliedHint }}</p>
          </div>
        </div>

        <!-- Files Section -->
        <div class="form-section">
          <h3>{{ strings.filesTitle }}</h3>
          <p class="section-description muted">{{ strings.filesDesc }}</p>

          <div class="preference-item">
            <label class="preference-label">{{ strings.uploadBehaviorLabel }}</label>
            <NcCheckboxRadioSwitch
              v-model="formData.upload_behavior"
              value="configured"
              name="upload_behavior"
              type="radio"
            >
              {{ strings.uploadBehaviorConfigured }}
            </NcCheckboxRadioSwitch>
            <NcCheckboxRadioSwitch
              v-model="formData.upload_behavior"
              value="prompt"
              name="upload_behavior"
              type="radio"
            >
              {{ strings.uploadBehaviorPrompt }}
            </NcCheckboxRadioSwitch>
            <p class="preference-hint">{{ strings.uploadBehaviorHint }}</p>
          </div>

          <template v-if="formData.upload_behavior === 'configured'">
            <div class="preference-item">
              <label class="preference-label">{{ strings.uploadDirectoryLabel }}</label>
              <div class="directory-input-group">
                <NcTextField
                  :model-value="displayedUploadDirectory"
                  :placeholder="strings.uploadDirectoryPlaceholder"
                  :readonly="true"
                  class="directory-input"
                />
                <NcButton @click="browseDirectory">
                  <template #icon>
                    <FolderIcon :size="20" />
                  </template>
                  {{ strings.browse }}
                </NcButton>
              </div>
              <p class="preference-hint">{{ strings.uploadDirectoryHint }}</p>
            </div>

            <div class="preference-item">
              <NcCheckboxRadioSwitch v-model="formData.use_category_upload_path">
                {{ strings.useCategoryUploadPathLabel }}
              </NcCheckboxRadioSwitch>
              <p class="preference-hint">{{ strings.useCategoryUploadPathHint }}</p>
            </div>
          </template>
        </div>

        <!-- Signature Section -->
        <div v-if="signaturesEnabled" class="form-section">
          <h3>{{ strings.signatureTitle }}</h3>
          <p class="section-description muted">{{ strings.signatureDesc }}</p>

          <div class="preference-item">
            <label class="preference-label">{{ strings.signatureLabel }}</label>
            <BBCodeEditor
              v-model="formData.signature"
              :placeholder="strings.signaturePlaceholder"
              :rows="3"
              min-height="5rem"
            />
            <p class="preference-hint">{{ strings.signatureHint }}</p>
          </div>
        </div>

        <!-- Privacy Section -->
        <div v-if="showPrivacySection" class="form-section">
          <h3>{{ strings.privacyTitle }}</h3>
          <p class="section-description muted">{{ strings.privacyDesc }}</p>

          <div class="preference-item">
            <NcCheckboxRadioSwitch v-model="formData.hide_edit_history">
              {{ strings.hideEditHistory }}
            </NcCheckboxRadioSwitch>
            <p class="preference-hint">{{ strings.hideEditHistoryHint }}</p>
          </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
          <NcButton :disabled="saving || !hasChanges" @click="resetForm">
            {{ strings.cancel }}
          </NcButton>
          <NcButton variant="primary" :disabled="saving || !hasChanges" @click="savePreferences">
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
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AppToolbar from '@/components/AppToolbar'
import PageWrapper from '@/components/PageWrapper'
import PageHeader from '@/components/PageHeader'
import BBCodeEditor from '@/components/BBCodeEditor'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import CheckIcon from '@icons/Check.vue'
import FolderIcon from '@icons/Folder.vue'
import { t } from '@nextcloud/l10n'
import { getFilePickerBuilder, FilePickerType } from '@nextcloud/dialogs'
import { usePublicSettings } from '@/composables/usePublicSettings'
import { useUserPreferences, type UserPreferences } from '@/composables/useUserPreferences'

export default defineComponent({
  name: 'UserPreferencesView',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcCheckboxRadioSwitch,
    NcTextField,
    AppToolbar,
    PageWrapper,
    PageHeader,
    BBCodeEditor,
    ArrowLeftIcon,
    CheckIcon,
    FolderIcon,
  },
  setup() {
    const { settings: publicSettings, fetchPublicSettings } = usePublicSettings()
    fetchPublicSettings()

    const { fetchUserPreferences, savePreferences: savePreferencesToServer } = useUserPreferences()

    return {
      publicSettings,
      fetchUserPreferences,
      savePreferencesToServer,
    }
  },
  data() {
    return {
      loading: false,
      saving: false,
      saveSuccess: false,
      error: null as string | null,
      originalData: {
        auto_subscribe_created_threads: true,
        auto_subscribe_replied_threads: false,
        upload_directory: 'Forum',
        signature: '',
        hide_edit_history: false,
        use_category_upload_path: true,
        upload_behavior: 'configured',
      } as UserPreferences,
      formData: {
        auto_subscribe_created_threads: true,
        auto_subscribe_replied_threads: false,
        upload_directory: 'Forum',
        signature: '',
        hide_edit_history: false,
        use_category_upload_path: true,
        upload_behavior: 'configured',
      } as UserPreferences,

      strings: {
        title: t('forum', 'Preferences'),
        subtitle: t('forum', 'Customize your forum experience'),
        back: t('forum', 'Back'),
        loading: t('forum', 'Loading preferences …'),
        errorTitle: t('forum', 'Error loading preferences'),
        retry: t('forum', 'Retry'),
        subscriptionsTitle: t('forum', 'Notifications'),
        subscriptionsDesc: t('forum', 'Configure how you receive notifications'),
        autoSubscribeCreatedLabel: t('forum', 'Auto-subscribe to threads I create'),
        autoSubscribeCreatedHint: t(
          'forum',
          'When enabled, you will automatically receive notifications for replies to threads you create',
        ),
        autoSubscribeRepliedLabel: t('forum', 'Auto-subscribe to threads I reply to'),
        autoSubscribeRepliedHint: t(
          'forum',
          'When enabled, you will automatically receive notifications for new replies in threads you have replied to',
        ),
        filesTitle: t('forum', 'Files'),
        filesDesc: t('forum', 'Configure file upload settings'),
        uploadDirectoryLabel: t('forum', 'Upload directory'),
        uploadDirectoryPlaceholder: t('forum', 'Pick a folder …'),
        uploadDirectoryHint: t(
          'forum',
          'Files attached to threads or replies will be uploaded to this directory in your Nextcloud files. The path resolves to your own files; other users will see the same folder at its location in their own files.',
        ),
        uploadBehaviorLabel: t('forum', 'Upload behavior'),
        uploadBehaviorConfigured: t('forum', 'Always upload to chosen directory'),
        uploadBehaviorPrompt: t('forum', 'Always prompt for upload location'),
        uploadBehaviorHint: t(
          'forum',
          'Whether attachment uploads use your configured directory or ask you to pick a destination each time',
        ),
        useCategoryUploadPathLabel: t('forum', 'Use category-specific paths when available'),
        useCategoryUploadPathHint: t(
          'forum',
          'When a category has its own attachments folder configured, uploads in that category go there instead of your default upload directory',
        ),
        browse: t('forum', 'Browse'),
        save: t('forum', 'Save'),
        cancel: t('forum', 'Cancel'),
        saveSuccess: t('forum', 'Preferences saved'),
        signatureTitle: t('forum', 'Signature'),
        signatureDesc: t(
          'forum',
          'Your signature appears at the bottom of your threads or replies',
        ),
        signatureLabel: t('forum', 'Signature'),
        signatureHint: t('forum', 'You can use BBCode formatting in your signature'),
        signaturePlaceholder: t('forum', 'Enter your signature …'),
        privacyTitle: t('forum', 'Privacy'),
        privacyDesc: t('forum', 'Control the visibility of your activity'),
        hideEditHistory: t('forum', 'Hide my edit history from other accounts'),
        hideEditHistoryHint: t(
          'forum',
          'When enabled, other accounts cannot view the edit history of your posts. Administration and moderators can always view edit history.',
        ),
      },
    }
  },
  computed: {
    hasChanges(): boolean {
      return (
        this.formData.auto_subscribe_created_threads !==
          this.originalData.auto_subscribe_created_threads ||
        this.formData.auto_subscribe_replied_threads !==
          this.originalData.auto_subscribe_replied_threads ||
        this.formData.upload_directory !== this.originalData.upload_directory ||
        this.formData.upload_directory_folder_id !== this.originalData.upload_directory_folder_id ||
        this.formData.signature !== this.originalData.signature ||
        this.formData.hide_edit_history !== this.originalData.hide_edit_history ||
        this.formData.use_category_upload_path !== this.originalData.use_category_upload_path ||
        this.formData.upload_behavior !== this.originalData.upload_behavior
      )
    },
    displayedUploadDirectory(): string {
      return this.formData.upload_directory_resolved_path ?? this.formData.upload_directory ?? ''
    },
    signaturesEnabled(): boolean {
      return this.publicSettings?.enable_signatures ?? true
    },
    showPrivacySection(): boolean {
      return (
        !!this.publicSettings?.public_edit_history &&
        !!this.publicSettings?.allow_edit_history_user_override
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

        const prefs = await this.fetchUserPreferences(true)
        if (prefs) {
          this.originalData = { ...prefs }
          this.formData = { ...prefs }
        }
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

        const saved = await this.savePreferencesToServer(this.formData)
        if (saved) {
          this.originalData = { ...saved }
          this.formData = { ...saved }
        } else {
          this.originalData = { ...this.formData }
        }
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

    async browseDirectory(): Promise<void> {
      try {
        const picker = getFilePickerBuilder(t('forum', 'Select upload directory'))
          .setMultiSelect(false)
          .setType(FilePickerType.Choose)
          .allowDirectories()
          .build()

        const nodes = await picker.pickNodes()
        const node = Array.isArray(nodes) ? nodes[0] : undefined
        const fileId = node?.fileid
        if (!fileId) {
          return
        }

        const rawPath =
          (node as { path?: string }).path ?? (node as { source?: string }).source ?? ''
        const relPath = rawPath.startsWith('/') ? rawPath.substring(1) : rawPath

        // Store both: the file ID is authoritative; the string path is a
        // human-readable label/fallback that gets cached on the server.
        this.formData.upload_directory_folder_id = fileId
        this.formData.upload_directory = relPath
        this.formData.upload_directory_resolved_path = relPath
      } catch (e) {
        if (e instanceof Error && !e.message.includes('No nodes selected')) {
          console.error('Failed to pick directory', e)
        }
      }
    },
  },
})
</script>

<style scoped lang="scss">
.user-preferences-view {
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

      .preference-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
      }

      .directory-input-group {
        display: flex;
        gap: 8px;
        align-items: center;

        .directory-input {
          flex: 1;
        }
      }

      .preference-hint {
        margin: 8px 0 0 0;
        font-size: 0.85rem;
        color: var(--color-text-maxcontrast);
        line-height: 1.4;
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
