<template>
  <div class="admin-category-edit">
    <div class="page-header">
      <div class="header-actions">
        <NcButton @click="goBack">
          <template #icon>
            <ArrowLeftIcon :size="20" />
          </template>
          {{ strings.back }}
        </NcButton>
      </div>
      <div>
        <h2>{{ isEditing ? strings.editCategory : strings.createCategory }}</h2>
        <p class="muted">{{ strings.subtitle }}</p>
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

    <!-- Form -->
    <div v-else class="category-form">
      <section class="form-section">
        <h3>{{ strings.basicInfo }}</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>{{ strings.categoryHeader }} *</label>
            <div class="header-select-row">
              <NcSelect
                v-model="selectedHeader"
                :options="headerOptions"
                :placeholder="strings.selectHeader"
                label="label"
                track-by="id"
                class="header-select"
              />
              <NcButton @click="createNewHeader">
                <template #icon>
                  <PlusIcon :size="20" />
                </template>
                {{ strings.newHeader }}
              </NcButton>
              <NcButton v-if="selectedHeader" @click="editHeader">
                <template #icon>
                  <PencilIcon :size="20" />
                </template>
                {{ strings.editHeader }}
              </NcButton>
            </div>
          </div>

          <div class="form-group">
            <NcTextField
              v-model="formData.name"
              :label="strings.name"
              :placeholder="strings.namePlaceholder"
              :required="true"
            />
          </div>

          <div class="form-group">
            <NcTextField
              v-model="formData.slug"
              :label="strings.slug"
              :placeholder="strings.slugPlaceholder"
              :required="true"
            />
            <p class="help-text muted">{{ strings.slugHelp }}</p>
          </div>

          <div class="form-group">
            <NcTextArea
              v-model="formData.description"
              :label="strings.description"
              :placeholder="strings.descriptionPlaceholder"
              :rows="3"
            />
          </div>

          <div class="form-group">
            <NcTextField
              v-model.number="formData.sortOrder"
              :label="strings.sortOrder"
              :placeholder="strings.sortOrderPlaceholder"
              type="number"
            />
            <p class="help-text muted">{{ strings.sortOrderHelp }}</p>
          </div>
        </div>
      </section>

      <!-- Actions -->
      <div class="form-actions">
        <NcButton @click="goBack">{{ strings.cancel }}</NcButton>
        <NcButton type="primary" :disabled="!canSubmit || submitting" @click="submitForm">
          <template v-if="submitting" #icon>
            <NcLoadingIcon :size="20" />
          </template>
          {{ isEditing ? strings.update : strings.create }}
        </NcButton>
      </div>
    </div>

    <!-- Header Edit/Create Dialog -->
    <NcDialog
      v-if="headerDialog.show"
      :name="headerDialog.isEditing ? strings.editHeaderTitle : strings.createHeaderTitle"
      @close="headerDialog.show = false"
    >
      <div class="header-dialog-content">
        <div class="form-group">
          <NcTextField
            v-model="headerDialog.name"
            :label="strings.headerName"
            :placeholder="strings.headerNamePlaceholder"
            :required="true"
          />
        </div>

        <div class="form-group">
          <NcTextArea
            v-model="headerDialog.description"
            :label="strings.headerDescription"
            :placeholder="strings.headerDescriptionPlaceholder"
            :rows="2"
          />
        </div>

        <div class="form-group">
          <NcTextField
            v-model.number="headerDialog.sortOrder"
            :label="strings.headerSortOrder"
            :placeholder="strings.sortOrderPlaceholder"
            type="number"
          />
          <p class="help-text muted">{{ strings.sortOrderHelp }}</p>
        </div>
      </div>

      <template #actions>
        <NcButton @click="headerDialog.show = false">
          {{ strings.cancel }}
        </NcButton>
        <NcButton
          type="primary"
          :disabled="!headerDialog.name.trim()"
          @click="saveHeader"
        >
          <template v-if="headerDialog.submitting" #icon>
            <NcLoadingIcon :size="20" />
          </template>
          {{ headerDialog.isEditing ? strings.update : strings.create }}
        </NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import PlusIcon from '@icons/Plus.vue'
import PencilIcon from '@icons/Pencil.vue'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import type { Category, CatHeader } from '@/types'

export default defineComponent({
  name: 'AdminCategoryEdit',
  components: {
    NcButton,
    NcDialog,
    NcEmptyContent,
    NcLoadingIcon,
    NcSelect,
    NcTextField,
    NcTextArea,
    ArrowLeftIcon,
    PlusIcon,
    PencilIcon,
  },
  data() {
    return {
      loading: false,
      submitting: false,
      error: null as string | null,
      headers: [] as CatHeader[],
      selectedHeader: null as { id: number; label: string } | null,
      formData: {
        headerId: null as number | null,
        name: '',
        slug: '',
        description: '',
        sortOrder: 0,
      },
      headerDialog: {
        show: false,
        isEditing: false,
        submitting: false,
        id: null as number | null,
        name: '',
        description: '',
        sortOrder: 0,
      },

      strings: {
        back: t('forum', 'Back'),
        createCategory: t('forum', 'Create Category'),
        editCategory: t('forum', 'Edit Category'),
        subtitle: t('forum', 'Configure category details'),
        loading: t('forum', 'Loading…'),
        errorTitle: t('forum', 'Error loading category'),
        retry: t('forum', 'Retry'),
        basicInfo: t('forum', 'Basic Information'),
        categoryHeader: t('forum', 'Category Header'),
        selectHeader: t('forum', '-- Select a header --'),
        name: t('forum', 'Name'),
        namePlaceholder: t('forum', 'Enter category name'),
        slug: t('forum', 'Slug'),
        slugPlaceholder: t('forum', 'category-slug'),
        slugHelp: t('forum', 'URL-friendly identifier (e.g., "general-discussion")'),
        description: t('forum', 'Description'),
        descriptionPlaceholder: t('forum', 'Enter category description (optional)'),
        sortOrder: t('forum', 'Sort Order'),
        sortOrderPlaceholder: t('forum', '0'),
        sortOrderHelp: t('forum', 'Lower numbers appear first'),
        cancel: t('forum', 'Cancel'),
        create: t('forum', 'Create'),
        update: t('forum', 'Update'),
        newHeader: t('forum', 'New'),
        editHeader: t('forum', 'Edit'),
        createHeaderTitle: t('forum', 'Create Category Header'),
        editHeaderTitle: t('forum', 'Edit Category Header'),
        headerName: t('forum', 'Header Name'),
        headerNamePlaceholder: t('forum', 'Enter header name'),
        headerDescription: t('forum', 'Header Description'),
        headerDescriptionPlaceholder: t('forum', 'Enter header description (optional)'),
        headerSortOrder: t('forum', 'Sort Order'),
      },
    }
  },
  computed: {
    isEditing(): boolean {
      return !!this.$route.params.id
    },
    categoryId(): number | null {
      return this.$route.params.id ? parseInt(this.$route.params.id as string) : null
    },
    canSubmit(): boolean {
      return (
        this.selectedHeader !== null &&
        this.formData.name.trim().length > 0 &&
        this.formData.slug.trim().length > 0
      )
    },
    headerOptions(): Array<{ id: number; label: string }> {
      return this.headers.map((header) => ({
        id: header.id,
        label: header.name,
      }))
    },
  },
  watch: {
    selectedHeader(newVal: { id: number; label: string } | null) {
      this.formData.headerId = newVal?.id || null
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

        // Load category headers
        const headersResponse = await ocs.get<CatHeader[]>('/headers')
        this.headers = headersResponse.data || []

        // If editing, load category data
        if (this.isEditing && this.categoryId) {
          await this.loadCategory()
        }
      } catch (e) {
        console.error('Failed to load category', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    async loadCategory(): Promise<void> {
      if (!this.categoryId) return

      const categoryResponse = await ocs.get<Category>(`/categories/${this.categoryId}`)
      const category = categoryResponse.data

      this.formData.headerId = category.headerId
      this.formData.name = category.name
      this.formData.slug = category.slug
      this.formData.description = category.description || ''
      this.formData.sortOrder = category.sortOrder || 0

      // Set selectedHeader based on headerId
      const header = this.headers.find((h) => h.id === category.headerId)
      if (header) {
        this.selectedHeader = {
          id: header.id,
          label: header.name,
        }
      }
    },

    async submitForm(): Promise<void> {
      if (!this.canSubmit) return

      try {
        this.submitting = true

        const categoryData = {
          headerId: this.formData.headerId!,
          name: this.formData.name.trim(),
          slug: this.formData.slug.trim(),
          description: this.formData.description.trim() || null,
          sortOrder: this.formData.sortOrder,
        }

        if (this.isEditing && this.categoryId !== null) {
          // Update existing category
          await ocs.put(`/categories/${this.categoryId}`, categoryData)
        } else {
          // Create new category
          await ocs.post('/categories', categoryData)
        }

        // Navigate back to category list
        this.$router.push('/admin/categories')
      } catch (e) {
        console.error('Failed to save category', e)
        // TODO: Show error notification
      } finally {
        this.submitting = false
      }
    },

    goBack(): void {
      this.$router.push('/admin/categories')
    },

    createNewHeader(): void {
      this.headerDialog.show = true
      this.headerDialog.isEditing = false
      this.headerDialog.id = null
      this.headerDialog.name = ''
      this.headerDialog.description = ''
      this.headerDialog.sortOrder = 0
    },

    editHeader(): void {
      if (!this.selectedHeader) return

      const header = this.headers.find((h) => h.id === this.selectedHeader?.id)
      if (!header) return

      this.headerDialog.show = true
      this.headerDialog.isEditing = true
      this.headerDialog.id = header.id
      this.headerDialog.name = header.name
      this.headerDialog.description = header.description || ''
      this.headerDialog.sortOrder = header.sortOrder || 0
    },

    async saveHeader(): Promise<void> {
      if (!this.headerDialog.name.trim()) return

      try {
        this.headerDialog.submitting = true

        const headerData = {
          name: this.headerDialog.name.trim(),
          description: this.headerDialog.description.trim() || null,
          sortOrder: this.headerDialog.sortOrder,
        }

        let headerId: number

        if (this.headerDialog.isEditing && this.headerDialog.id !== null) {
          // Update existing header
          await ocs.put(`/headers/${this.headerDialog.id}`, headerData)
          headerId = this.headerDialog.id

          // Update in local headers array
          const index = this.headers.findIndex((h) => h.id === headerId)
          if (index !== -1) {
            this.headers[index] = {
              ...this.headers[index],
              name: headerData.name,
              description: headerData.description,
              sortOrder: headerData.sortOrder,
            }
          }
        } else {
          // Create new header
          const response = await ocs.post<CatHeader>('/headers', headerData)
          headerId = response.data.id

          // Add to local headers array
          this.headers.push(response.data)
        }

        // Auto-select the new/updated header
        const header = this.headers.find((h) => h.id === headerId)
        if (header) {
          this.selectedHeader = {
            id: header.id,
            label: header.name,
          }
        }

        this.headerDialog.show = false
      } catch (e) {
        console.error('Failed to save header', e)
        // TODO: Show error notification
      } finally {
        this.headerDialog.submitting = false
      }
    },
  },
})
</script>

<style scoped lang="scss">
.admin-category-edit {
  max-width: 800px;

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

    .header-actions {
      margin-bottom: 12px;
    }

    h2 {
      margin: 0 0 6px 0;
    }
  }

  .category-form {
    display: flex;
    flex-direction: column;
    gap: 32px;

    .form-section {
      h3 {
        margin: 0 0 16px 0;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--color-main-text);
      }
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;

      label {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--color-main-text);
        margin-bottom: 4px;
      }

      .help-text {
        font-size: 0.85rem;
        margin-top: 4px;
      }

      .header-select-row {
        display: flex;
        gap: 8px;
        align-items: flex-start;

        .header-select {
          flex: 1;
        }
      }
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      padding-top: 16px;
      border-top: 1px solid var(--color-border);
    }
  }
}

.header-dialog-content {
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
