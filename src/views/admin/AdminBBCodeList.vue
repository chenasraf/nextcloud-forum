<template>
  <div class="admin-bbcode-list">
    <div class="page-header">
      <div>
        <h2>{{ strings.title }}</h2>
        <p class="muted">{{ strings.subtitle }}</p>
      </div>
      <div class="header-actions">
        <NcButton type="primary" @click="createBBCode">
          <template #icon>
            <PlusIcon :size="20" />
          </template>
          {{ strings.createBBCode }}
        </NcButton>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="center mt-16">
      <NcLoadingIcon :size="32" />
      <span class="muted ml-8">{{ strings.loading }}</span>
    </div>

    <!-- Error state -->
    <NcEmptyContent v-else-if="error" :title="strings.errorTitle" :description="error" class="mt-16">
      <template #action>
        <NcButton @click="refresh">{{ strings.retry }}</NcButton>
      </template>
    </NcEmptyContent>

    <!-- BBCode list -->
    <div v-else class="bbcode-list">
      <!-- Enabled BBCodes Section -->
      <section class="bbcodes-section">
        <div class="section-header">
          <h3>{{ strings.enabledTitle }}</h3>
          <p class="muted">{{ strings.enabledSubtitle }}</p>
        </div>

        <div v-if="enabledBBCodes.length > 0" class="bbcodes-table">
          <div v-for="bbcode in enabledBBCodes" :key="`bbcode-${bbcode.id}`" class="bbcode-row">
            <div class="bbcode-info">
              <div class="bbcode-header">
                <div class="bbcode-tag">[{{ bbcode.tag }}]</div>
                <div v-if="bbcode.parseInner" class="badge badge-info">{{ strings.parseInner }}</div>
              </div>
              <div v-if="bbcode.description" class="bbcode-desc muted">{{ bbcode.description }}</div>
              <div class="bbcode-replacement">
                <span class="label muted">{{ strings.replacement }}:</span>
                <code>{{ bbcode.replacement }}</code>
              </div>
            </div>
            <div class="bbcode-actions">
              <NcButton @click="editBBCode(bbcode)">
                <template #icon>
                  <PencilIcon :size="20" />
                </template>
                {{ strings.edit }}
              </NcButton>
              <NcButton @click="toggleEnabled(bbcode)">
                <template #icon>
                  <EyeOffIcon :size="20" />
                </template>
                {{ strings.disable }}
              </NcButton>
              <NcButton type="error" @click="confirmDelete(bbcode)">
                <template #icon>
                  <DeleteIcon :size="20" />
                </template>
                {{ strings.delete }}
              </NcButton>
            </div>
          </div>
        </div>
        <div v-else class="no-bbcodes muted">
          {{ strings.noEnabledBBCodes }}
        </div>
      </section>

      <!-- Disabled BBCodes Section -->
      <section v-if="disabledBBCodes.length > 0" class="bbcodes-section">
        <div class="section-header">
          <h3>{{ strings.disabledTitle }}</h3>
          <p class="muted">{{ strings.disabledSubtitle }}</p>
        </div>

        <div class="bbcodes-table">
          <div v-for="bbcode in disabledBBCodes" :key="`bbcode-${bbcode.id}`" class="bbcode-row disabled">
            <div class="bbcode-info">
              <div class="bbcode-header">
                <div class="bbcode-tag">[{{ bbcode.tag }}]</div>
                <div v-if="bbcode.parseInner" class="badge badge-info">{{ strings.parseInner }}</div>
              </div>
              <div v-if="bbcode.description" class="bbcode-desc muted">{{ bbcode.description }}</div>
              <div class="bbcode-replacement">
                <span class="label muted">{{ strings.replacement }}:</span>
                <code>{{ bbcode.replacement }}</code>
              </div>
            </div>
            <div class="bbcode-actions">
              <NcButton @click="editBBCode(bbcode)">
                <template #icon>
                  <PencilIcon :size="20" />
                </template>
                {{ strings.edit }}
              </NcButton>
              <NcButton type="primary" @click="toggleEnabled(bbcode)">
                <template #icon>
                  <EyeIcon :size="20" />
                </template>
                {{ strings.enable }}
              </NcButton>
              <NcButton type="error" @click="confirmDelete(bbcode)">
                <template #icon>
                  <DeleteIcon :size="20" />
                </template>
                {{ strings.delete }}
              </NcButton>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Delete confirmation dialog -->
    <NcDialog
      v-if="deleteDialog.show"
      :name="strings.deleteDialogTitle"
      @close="deleteDialog.show = false"
    >
      <div class="delete-dialog-content">
        <p>{{ strings.deleteConfirmMessage(deleteDialog.bbcode?.tag || '') }}</p>
        <p class="muted">{{ strings.deleteWarning }}</p>
      </div>

      <template #actions>
        <NcButton @click="deleteDialog.show = false">
          {{ strings.cancel }}
        </NcButton>
        <NcButton type="error" @click="executeDelete">
          {{ strings.deleteBBCode }}
        </NcButton>
      </template>
    </NcDialog>

    <!-- BBCode Edit/Create Dialog -->
    <NcDialog
      v-if="editDialog.show"
      :name="editDialog.isEditing ? strings.editBBCodeTitle : strings.createBBCodeTitle"
      @close="editDialog.show = false"
    >
      <div class="bbcode-dialog-content">
        <div class="form-group">
          <NcTextField
            v-model="editDialog.tag"
            :label="strings.tag"
            :placeholder="strings.tagPlaceholder"
            :required="true"
          />
          <p class="help-text muted">{{ strings.tagHelp }}</p>
        </div>

        <div class="form-group">
          <NcTextArea
            v-model="editDialog.replacement"
            :label="strings.replacementLabel"
            :placeholder="strings.replacementPlaceholder"
            :rows="3"
            :required="true"
          />
          <p class="help-text muted">{{ strings.replacementHelp }}</p>
        </div>

        <div class="form-group">
          <NcTextArea
            v-model="editDialog.description"
            :label="strings.description"
            :placeholder="strings.descriptionPlaceholder"
            :rows="2"
          />
        </div>

        <div class="form-group">
          <NcCheckboxRadioSwitch
            v-model="editDialog.enabled"
            type="switch"
          >
            {{ strings.enabledLabel }}
          </NcCheckboxRadioSwitch>
        </div>

        <div class="form-group">
          <NcCheckboxRadioSwitch
            v-model="editDialog.parseInner"
            type="switch"
          >
            {{ strings.parseInnerLabel }}
          </NcCheckboxRadioSwitch>
          <p class="help-text muted">{{ strings.parseInnerHelp }}</p>
        </div>
      </div>

      <template #actions>
        <NcButton @click="editDialog.show = false">
          {{ strings.cancel }}
        </NcButton>
        <NcButton
          type="primary"
          :disabled="!editDialog.tag.trim() || !editDialog.replacement.trim()"
          @click="saveBBCode"
        >
          <template v-if="editDialog.submitting" #icon>
            <NcLoadingIcon :size="20" />
          </template>
          {{ editDialog.isEditing ? strings.update : strings.create }}
        </NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import PlusIcon from '@icons/Plus.vue'
import PencilIcon from '@icons/Pencil.vue'
import DeleteIcon from '@icons/Delete.vue'
import EyeIcon from '@icons/Eye.vue'
import EyeOffIcon from '@icons/EyeOff.vue'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'

interface BBCode {
  id: number
  tag: string
  replacement: string
  description: string | null
  enabled: boolean
  parseInner: boolean
  createdAt: number
}

export default defineComponent({
  name: 'AdminBBCodeList',
  components: {
    NcButton,
    NcCheckboxRadioSwitch,
    NcDialog,
    NcEmptyContent,
    NcLoadingIcon,
    NcTextField,
    NcTextArea,
    PlusIcon,
    PencilIcon,
    DeleteIcon,
    EyeIcon,
    EyeOffIcon,
  },
  data() {
    return {
      loading: false,
      error: null as string | null,
      bbcodes: [] as BBCode[],
      deleteDialog: {
        show: false,
        bbcode: null as BBCode | null,
      },
      editDialog: {
        show: false,
        isEditing: false,
        submitting: false,
        id: null as number | null,
        tag: '',
        replacement: '',
        description: '',
        enabled: true,
        parseInner: true,
      },

      strings: {
        title: t('forum', 'BBCode Management'),
        subtitle: t('forum', 'Manage custom BBCode tags for post formatting'),
        loading: t('forum', 'Loading…'),
        errorTitle: t('forum', 'Error loading BBCodes'),
        retry: t('forum', 'Retry'),
        createBBCode: t('forum', 'Create BBCode'),
        edit: t('forum', 'Edit'),
        delete: t('forum', 'Delete'),
        enable: t('forum', 'Enable'),
        disable: t('forum', 'Disable'),
        enabledTitle: t('forum', 'Enabled BBCodes'),
        enabledSubtitle: t('forum', 'These BBCode tags are currently active'),
        disabledTitle: t('forum', 'Disabled BBCodes'),
        disabledSubtitle: t('forum', 'These BBCode tags are currently inactive'),
        noEnabledBBCodes: t('forum', 'No enabled BBCodes'),
        parseInner: t('forum', 'Parses Inner'),
        replacement: t('forum', 'Replacement'),
        deleteDialogTitle: t('forum', 'Delete BBCode'),
        deleteConfirmMessage: (tag: string) => t('forum', `Are you sure you want to delete the BBCode tag "[{tag}]"?`, { tag }),
        deleteWarning: t('forum', 'This action cannot be undone.'),
        cancel: t('forum', 'Cancel'),
        deleteBBCode: t('forum', 'Delete BBCode'),
        createBBCodeTitle: t('forum', 'Create BBCode'),
        editBBCodeTitle: t('forum', 'Edit BBCode'),
        tag: t('forum', 'Tag'),
        tagPlaceholder: t('forum', 'e.g., b, i, url, color'),
        tagHelp: t('forum', 'The BBCode tag name (without brackets)'),
        replacementLabel: t('forum', 'HTML Replacement'),
        replacementPlaceholder: t('forum', 'e.g., <strong>{content}</strong>'),
        replacementHelp: t('forum', 'Use {content} for the tag content and {paramName} for parameters'),
        description: t('forum', 'Description'),
        descriptionPlaceholder: t('forum', 'Brief description of what this BBCode does'),
        enabledLabel: t('forum', 'Enabled'),
        parseInnerLabel: t('forum', 'Parse Inner Content'),
        parseInnerHelp: t('forum', 'If enabled, BBCode tags inside this tag will also be parsed'),
        update: t('forum', 'Update'),
        create: t('forum', 'Create'),
      },
    }
  },
  computed: {
    enabledBBCodes(): BBCode[] {
      return this.bbcodes.filter((bb) => bb.enabled)
    },
    disabledBBCodes(): BBCode[] {
      return this.bbcodes.filter((bb) => !bb.enabled)
    },
  },
  created() {
    this.refresh()
  },
  methods: {
    async refresh(): Promise<void> {
      try {
        this.loading = true
        this.error = null

        const response = await ocs.get<BBCode[]>('/bbcodes')
        this.bbcodes = response.data || []
      } catch (e) {
        console.error('Failed to load BBCodes', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    createBBCode(): void {
      this.editDialog.show = true
      this.editDialog.isEditing = false
      this.editDialog.id = null
      this.editDialog.tag = ''
      this.editDialog.replacement = ''
      this.editDialog.description = ''
      this.editDialog.enabled = true
      this.editDialog.parseInner = true
    },

    editBBCode(bbcode: BBCode): void {
      this.editDialog.show = true
      this.editDialog.isEditing = true
      this.editDialog.id = bbcode.id
      this.editDialog.tag = bbcode.tag
      this.editDialog.replacement = bbcode.replacement
      this.editDialog.description = bbcode.description || ''
      this.editDialog.enabled = bbcode.enabled
      this.editDialog.parseInner = bbcode.parseInner
    },

    async saveBBCode(): Promise<void> {
      if (!this.editDialog.tag.trim() || !this.editDialog.replacement.trim()) return

      try {
        this.editDialog.submitting = true

        const bbcodeData = {
          tag: this.editDialog.tag.trim(),
          replacement: this.editDialog.replacement.trim(),
          description: this.editDialog.description.trim() || null,
          enabled: this.editDialog.enabled,
          parseInner: this.editDialog.parseInner,
        }

        if (this.editDialog.isEditing && this.editDialog.id !== null) {
          // Update existing BBCode
          await ocs.put(`/bbcodes/${this.editDialog.id}`, bbcodeData)
        } else {
          // Create new BBCode
          await ocs.post('/bbcodes', bbcodeData)
        }

        this.editDialog.show = false
        this.refresh()
      } catch (e) {
        console.error('Failed to save BBCode', e)
        // TODO: Show error notification
      } finally {
        this.editDialog.submitting = false
      }
    },

    async toggleEnabled(bbcode: BBCode): Promise<void> {
      try {
        await ocs.put(`/bbcodes/${bbcode.id}`, {
          enabled: !bbcode.enabled,
        })
        this.refresh()
      } catch (e) {
        console.error('Failed to toggle BBCode', e)
        // TODO: Show error notification
      }
    },

    confirmDelete(bbcode: BBCode): void {
      this.deleteDialog.bbcode = bbcode
      this.deleteDialog.show = true
    },

    async executeDelete(): Promise<void> {
      if (!this.deleteDialog.bbcode) return

      try {
        await ocs.delete(`/bbcodes/${this.deleteDialog.bbcode.id}`)
        this.deleteDialog.show = false
        this.refresh()
      } catch (e) {
        console.error('Failed to delete BBCode', e)
        // TODO: Show error notification
      }
    },
  },
})
</script>

<style scoped lang="scss">
.admin-bbcode-list {
  max-width: 1200px;

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
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;

    h2 {
      margin: 0 0 6px 0;
    }

    .header-actions {
      display: flex;
      gap: 8px;
    }
  }

  .bbcode-list {
    display: flex;
    flex-direction: column;
    gap: 48px;

    .section-header {
      margin-bottom: 16px;

      h3 {
        margin: 0 0 6px 0;
        font-size: 1.4rem;
        font-weight: 600;
      }

      p {
        font-size: 0.9rem;
      }
    }

    .bbcodes-section {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .bbcodes-table {
      display: flex;
      flex-direction: column;
      gap: 1px;
      background: var(--color-border);
      border-radius: 8px;
      overflow: hidden;

      .bbcode-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 16px;
        background: var(--color-main-background);

        &:hover {
          background: var(--color-background-hover);
        }

        &.disabled {
          opacity: 0.6;
        }

        .bbcode-info {
          flex: 1;
          display: flex;
          flex-direction: column;
          gap: 8px;

          .bbcode-header {
            display: flex;
            align-items: center;
            gap: 12px;

            .bbcode-tag {
              font-family: monospace;
              font-weight: 600;
              font-size: 1.1rem;
              color: var(--color-primary-element);
            }

            .badge {
              padding: 2px 8px;
              border-radius: 12px;
              font-size: 0.75rem;
              font-weight: 500;
              text-transform: uppercase;

              &.badge-info {
                background: var(--color-primary-element-light);
                color: var(--color-primary-element);
              }
            }
          }

          .bbcode-desc {
            font-size: 0.9rem;
          }

          .bbcode-replacement {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;

            .label {
              font-weight: 500;
            }

            code {
              font-family: monospace;
              background: var(--color-background-dark);
              padding: 2px 6px;
              border-radius: 4px;
              font-size: 0.85rem;
            }
          }
        }

        .bbcode-actions {
          display: flex;
          gap: 8px;
        }
      }
    }

    .no-bbcodes {
      padding: 16px;
      text-align: center;
      font-style: italic;
    }
  }
}

.delete-dialog-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 8px 0;
}

.bbcode-dialog-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 8px 0;

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;

    .help-text {
      font-size: 0.85rem;
      margin-top: 4px;
      color: var(--color-text-maxcontrast);
    }
  }
}
</style>
