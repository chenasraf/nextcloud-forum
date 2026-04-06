<template>
  <NcDialog
    :name="
      currentView === 'list'
        ? strings.title
        : editingTemplate
          ? strings.editTemplate
          : strings.addTemplate
    "
    size="normal"
    :open="open"
    close-on-click-outside
    @update:open="handleClose"
  >
    <!-- List View -->
    <div v-if="currentView === 'list'" class="template-modal">
      <!-- Loading state -->
      <div v-if="loading" class="loading-state">
        <NcLoadingIcon :size="32" />
        <span class="loading-text">{{ strings.loading }}</span>
      </div>

      <!-- Error state -->
      <div v-else-if="error" class="error-state">
        <span class="error-text">{{ error }}</span>
      </div>

      <!-- Empty state -->
      <div v-else-if="templates.length === 0" class="empty-state">
        <TextBoxIcon :size="48" class="empty-icon" />
        <span class="empty-text">{{ strings.noTemplates }}</span>
        <NcButton variant="primary" @click="openCreate">
          <template #icon>
            <PlusIcon :size="20" />
          </template>
          {{ strings.addTemplate }}
        </NcButton>
      </div>

      <!-- Template list -->
      <div v-else class="template-list">
        <div v-for="tpl in paginatedTemplates" :key="tpl.id" class="template-item">
          <div class="template-item-body">
            <div class="template-item-header">
              <span class="template-name">{{ tpl.name }}</span>
              <span class="template-visibility">{{ visibilityLabel(tpl.visibility) }}</span>
            </div>
            <p class="template-preview">{{ truncate(tpl.content, 120) }}</p>
          </div>
          <div class="template-actions">
            <NcButton variant="primary" @click="insertTemplate(tpl)">
              <template #icon>
                <ArrowDownIcon :size="20" />
              </template>
              {{ strings.insert }}
            </NcButton>
            <NcButton
              variant="tertiary"
              :aria-label="strings.edit"
              :title="strings.edit"
              @click="openEdit(tpl)"
            >
              <template #icon>
                <PencilIcon :size="20" />
              </template>
            </NcButton>
            <NcButton
              variant="tertiary"
              :aria-label="strings.delete"
              :title="strings.delete"
              @click="deleteTemplate(tpl)"
            >
              <template #icon>
                <DeleteIcon :size="20" />
              </template>
            </NcButton>
          </div>
        </div>

        <Pagination
          :current-page="currentPage"
          :max-pages="totalPages"
          @update:current-page="currentPage = $event"
        />
      </div>
    </div>

    <!-- Edit View -->
    <div v-else class="template-modal template-edit">
      <div class="edit-field">
        <NcTextField
          v-model="form.name"
          :label="strings.nameLabel"
          :placeholder="strings.namePlaceholder"
        />
      </div>

      <div class="edit-field">
        <label class="field-label">{{ strings.visibilityLabel }}</label>
        <div class="visibility-options">
          <NcCheckboxRadioSwitch
            v-for="opt in visibilityOptions"
            :key="opt.value"
            v-model="form.visibility"
            :value="opt.value"
            name="visibility"
            type="radio"
          >
            {{ opt.label }}
          </NcCheckboxRadioSwitch>
        </div>
      </div>

      <div class="edit-field">
        <label class="field-label">{{ strings.contentLabel }}</label>
        <BBCodeEditor
          v-model="form.content"
          :placeholder="strings.contentPlaceholder"
          :rows="6"
          min-height="8rem"
        />
      </div>
    </div>

    <template #actions>
      <template v-if="currentView === 'list'">
        <NcButton v-if="templates.length > 0" @click="openCreate">
          <template #icon>
            <PlusIcon :size="20" />
          </template>
          {{ strings.addTemplate }}
        </NcButton>
      </template>
      <template v-else>
        <NcButton @click="cancelEdit">
          {{ strings.cancel }}
        </NcButton>
        <NcButton variant="primary" :disabled="!canSave || saving" @click="saveTemplate">
          <template v-if="saving" #icon>
            <NcLoadingIcon :size="20" />
          </template>
          {{ strings.save }}
        </NcButton>
      </template>
    </template>
  </NcDialog>
</template>

<script lang="ts">
import { defineAsyncComponent, defineComponent, type PropType } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PlusIcon from '@icons/Plus.vue'
import PencilIcon from '@icons/Pencil.vue'
import DeleteIcon from '@icons/Delete.vue'
import TextBoxIcon from '@icons/TextBox.vue'
import ArrowDownIcon from '@icons/ArrowDown.vue'
import Pagination from '@/components/Pagination'
import { t } from '@nextcloud/l10n'
import { ocs } from '@/axios'
import type { Template } from '@/types'

type EditorContext = 'thread' | 'reply' | null

export default defineComponent({
  name: 'TemplateModal',
  components: {
    NcDialog,
    NcButton,
    NcLoadingIcon,
    NcTextField,
    NcCheckboxRadioSwitch,
    Pagination,
    // Async import to break circular dependency: BBCodeToolbar → TemplateModal → BBCodeEditor → BBCodeToolbar
    BBCodeEditor: defineAsyncComponent(() => import('@/components/BBCodeEditor')),
    PlusIcon,
    PencilIcon,
    DeleteIcon,
    TextBoxIcon,
    ArrowDownIcon,
  },
  props: {
    open: {
      type: Boolean,
      required: true,
    },
    editorContext: {
      type: String as PropType<EditorContext>,
      default: null,
    },
  },
  emits: ['update:open', 'insert'],
  data() {
    return {
      currentView: 'list' as 'list' | 'edit',
      templates: [] as Template[],
      currentPage: 1,
      perPage: 10,
      loading: false,
      saving: false,
      error: null as string | null,
      editingTemplate: null as Template | null,
      form: {
        name: '',
        content: '',
        visibility: 'both' as string,
      },
      strings: {
        title: t('forum', 'Templates'),
        addTemplate: t('forum', 'Add template'),
        editTemplate: t('forum', 'Edit template'),
        noTemplates: t('forum', 'No templates yet'),
        loading: t('forum', 'Loading templates …'),
        nameLabel: t('forum', 'Name'),
        namePlaceholder: t('forum', 'Template name'),
        contentLabel: t('forum', 'Content'),
        contentPlaceholder: t('forum', 'Template content (BBCode) …'),
        visibilityLabel: t('forum', 'Show in:'),
        cancel: t('forum', 'Cancel'),
        save: t('forum', 'Save'),
        edit: t('forum', 'Edit'),
        delete: t('forum', 'Delete'),
        confirmDelete: t('forum', 'Are you sure you want to delete this template?'),
        threads: t('forum', 'Threads'),
        replies: t('forum', 'Replies'),
        both: t('forum', 'Both'),
        threadsAndReplies: t('forum', 'Threads & replies'),
        neither: t('forum', 'Neither (disabled)'),
        insert: t('forum', 'Insert'),
      },
    }
  },
  computed: {
    totalPages(): number {
      return Math.max(1, Math.ceil(this.templates.length / this.perPage))
    },
    paginatedTemplates(): Template[] {
      const start = (this.currentPage - 1) * this.perPage
      return this.templates.slice(start, start + this.perPage)
    },
    canSave(): boolean {
      return this.form.name.trim().length > 0 && this.form.content.trim().length > 0
    },
    visibilityOptions(): Array<{ value: string; label: string }> {
      return [
        { value: 'both', label: this.strings.both },
        { value: 'threads', label: this.strings.threads },
        { value: 'replies', label: this.strings.replies },
        { value: 'neither', label: this.strings.neither },
      ]
    },
  },
  watch: {
    open: {
      immediate: true,
      handler(newValue: boolean) {
        if (newValue) {
          this.currentView = 'list'
          this.currentPage = 1
          this.fetchTemplates()
        }
      },
    },
  },
  methods: {
    visibilityLabel(visibility: string): string {
      const map: Record<string, string> = {
        threads: this.strings.threads,
        replies: this.strings.replies,
        both: this.strings.threadsAndReplies,
        neither: this.strings.neither,
      }
      return map[visibility] || visibility
    },

    truncate(text: string, maxLength: number): string {
      if (text.length <= maxLength) {
        return text
      }
      return text.slice(0, maxLength) + ' …'
    },

    async fetchTemplates(): Promise<void> {
      try {
        this.loading = true
        this.error = null

        const params: Record<string, string> = {}
        if (this.editorContext) {
          params.visibility = this.editorContext === 'thread' ? 'threads' : 'replies'
        }

        const response = await ocs.get<Template[]>('/templates', { params })
        this.templates = response.data || []
      } catch (e) {
        console.error('Failed to fetch templates:', e)
        this.error = t('forum', 'Failed to load templates')
      } finally {
        this.loading = false
      }
    },

    insertTemplate(tpl: Template): void {
      this.$emit('insert', tpl.content)
      this.$emit('update:open', false)
    },

    openCreate(): void {
      this.editingTemplate = null
      this.form.name = ''
      this.form.content = ''
      this.form.visibility = 'both'
      this.currentView = 'edit'
    },

    openEdit(tpl: Template): void {
      this.editingTemplate = tpl
      this.form.name = tpl.name
      this.form.content = tpl.content
      this.form.visibility = tpl.visibility
      this.currentView = 'edit'
    },

    cancelEdit(): void {
      this.currentView = 'list'
      this.editingTemplate = null
    },

    async saveTemplate(): Promise<void> {
      if (!this.canSave || this.saving) {
        return
      }

      try {
        this.saving = true

        if (this.editingTemplate) {
          await ocs.put(`/templates/${this.editingTemplate.id}`, {
            name: this.form.name.trim(),
            content: this.form.content.trim(),
            visibility: this.form.visibility,
          })
        } else {
          await ocs.post('/templates', {
            name: this.form.name.trim(),
            content: this.form.content.trim(),
            visibility: this.form.visibility,
          })
        }

        this.currentView = 'list'
        this.editingTemplate = null
        await this.fetchTemplates()
      } catch (e) {
        console.error('Failed to save template:', e)
      } finally {
        this.saving = false
      }
    },

    async deleteTemplate(tpl: Template): Promise<void> {
      // eslint-disable-next-line no-alert
      if (!confirm(this.strings.confirmDelete)) {
        return
      }

      try {
        await ocs.delete(`/templates/${tpl.id}`)
        await this.fetchTemplates()
        if (this.currentPage > this.totalPages) {
          this.currentPage = this.totalPages
        }
      } catch (e) {
        console.error('Failed to delete template:', e)
      }
    },

    handleClose(value: boolean): void {
      this.$emit('update:open', value)
    },
  },
})
</script>

<style scoped lang="scss">
.template-modal {
  padding: 16px;
  min-height: 200px;
}

.template-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.template-item {
  padding: 12px;
  background: var(--color-background-hover);
  border: 1px solid var(--color-border);
  border-radius: 6px;
}

.template-item-body {
  margin-bottom: 8px;
}

.template-item-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.template-name {
  font-weight: 600;
  font-size: 0.95rem;
  color: var(--color-main-text);
}

.template-visibility {
  font-size: 0.8rem;
  color: var(--color-text-maxcontrast);
  padding: 2px 6px;
  background: var(--color-background-dark);
  border-radius: 3px;
}

.template-preview {
  margin: 0;
  font-size: 0.85rem;
  color: var(--color-text-lighter);
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.template-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 4px;
}

.template-edit {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.edit-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.field-label {
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--color-main-text);
}

.visibility-options {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.loading-state,
.error-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 32px 16px;
  text-align: center;
}

.empty-icon {
  color: var(--color-text-maxcontrast);
}

.loading-text,
.empty-text {
  font-size: 0.9rem;
  color: var(--color-text-maxcontrast);
}

.error-text {
  font-size: 0.9rem;
  color: var(--color-error);
}
</style>
