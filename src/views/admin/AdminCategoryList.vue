<template>
  <PageWrapper>
    <template #toolbar>
      <AppToolbar>
        <template #right>
          <NcButton @click="createHeader">
            <template #icon>
              <PlusIcon :size="20" />
            </template>
            {{ strings.createHeader }}
          </NcButton>
          <NcButton variant="primary" @click="createCategory">
            <template #icon>
              <PlusIcon :size="20" />
            </template>
            {{ strings.createCategory }}
          </NcButton>
        </template>
      </AppToolbar>
    </template>

    <div class="admin-category-list">
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
          <NcButton @click="refresh">{{ strings.retry }}</NcButton>
        </template>
      </NcEmptyContent>

      <!-- Category list -->
      <div v-else class="category-list">
        <!-- Categories by Header -->
        <section class="categories-section">
          <template v-for="(header, headerIndex) in categoryHeaders" :key="header.id">
            <div class="header-row">
              <div class="header-sort-buttons">
                <NcButton
                  v-if="headerIndex > 0"
                  variant="tertiary"
                  @click="moveHeaderUp(headerIndex)"
                  :aria-label="strings.moveUp"
                  :title="strings.moveUp"
                >
                  <template #icon>
                    <ChevronUpIcon :size="20" />
                  </template>
                </NcButton>
                <NcButton
                  v-if="headerIndex < categoryHeaders.length - 1"
                  variant="tertiary"
                  @click="moveHeaderDown(headerIndex)"
                  :aria-label="strings.moveDown"
                  :title="strings.moveDown"
                >
                  <template #icon>
                    <ChevronDownIcon :size="20" />
                  </template>
                </NcButton>
              </div>
              <div class="header-info">
                <h3>{{ header.name }}</h3>
                <span v-if="header.description" class="muted">{{ header.description }}</span>
                <span class="muted category-count">{{
                  strings.categoriesCount(header.categories?.length || 0)
                }}</span>
              </div>
              <div class="header-actions">
                <NcButton @click="editHeaderById(header.id)">
                  <template #icon>
                    <PencilIcon :size="20" />
                  </template>
                  {{ strings.edit }}
                </NcButton>
                <NcButton
                  variant="error"
                  :disabled="categoryHeaders.length <= 1"
                  @click="confirmDeleteHeader(header)"
                >
                  <template #icon>
                    <DeleteIcon :size="20" />
                  </template>
                  {{ strings.delete }}
                </NcButton>
              </div>
            </div>

            <div v-if="header.categories && header.categories.length > 0" class="categories-table">
              <div
                v-for="(category, index) in header.categories"
                :key="category.id"
                class="category-row"
              >
                <div class="category-sort-buttons">
                  <NcButton
                    v-if="index > 0"
                    variant="tertiary"
                    @click="moveCategoryUp(header.id, index)"
                    :aria-label="strings.moveUp"
                    :title="strings.moveUp"
                  >
                    <template #icon>
                      <ChevronUpIcon :size="20" />
                    </template>
                  </NcButton>
                  <NcButton
                    v-if="index < header.categories.length - 1"
                    variant="tertiary"
                    @click="moveCategoryDown(header.id, index)"
                    :aria-label="strings.moveDown"
                    :title="strings.moveDown"
                  >
                    <template #icon>
                      <ChevronDownIcon :size="20" />
                    </template>
                  </NcButton>
                </div>
                <div class="category-info">
                  <div class="category-name">{{ category.name }}</div>
                  <div v-if="category.description" class="category-desc muted">
                    {{ category.description }}
                  </div>
                  <div class="category-meta muted">
                    <span>Slug: {{ category.slug }}</span>
                    <span>•</span>
                    <span>{{ strings.threadsCount(category.threadCount || 0) }}</span>
                    <span>•</span>
                    <span>{{ strings.postsCount(category.postCount || 0) }}</span>
                  </div>
                </div>
                <div class="category-actions">
                  <NcButton @click="editCategory(category.id)">
                    <template #icon>
                      <PencilIcon :size="20" />
                    </template>
                    {{ strings.edit }}
                  </NcButton>
                  <NcButton variant="error" @click="confirmDelete(category)">
                    <template #icon>
                      <DeleteIcon :size="20" />
                    </template>
                    {{ strings.delete }}
                  </NcButton>
                </div>
              </div>
            </div>
            <div v-else class="no-categories muted">
              {{ strings.noCategories }}
            </div>
          </template>
        </section>
      </div>

      <!-- Delete confirmation dialog -->
      <NcDialog
        v-if="deleteDialog.show"
        :name="strings.deleteDialogTitle"
        @close="deleteDialog.show = false"
      >
        <div class="delete-dialog-content">
          <p>{{ strings.deleteConfirmMessage(deleteDialog.category?.name || '') }}</p>

          <div v-if="deleteDialog.threadCount > 0" class="thread-warning">
            <InformationIcon :size="20" />
            <span>{{ strings.threadWarning(deleteDialog.threadCount) }}</span>
          </div>

          <div v-if="deleteDialog.threadCount > 0" class="migration-options">
            <h4>{{ strings.whatToDoWithThreads }}</h4>

            <div class="radio-group">
              <NcCheckboxRadioSwitch
                v-model="deleteDialog.action"
                value="migrate"
                type="radio"
                name="delete-action"
              >
                {{ strings.migrateThreads }}
              </NcCheckboxRadioSwitch>

              <div v-if="deleteDialog.action === 'migrate'" class="category-select">
                <label>{{ strings.selectTargetCategory }}</label>
                <NcSelect
                  v-model="selectedTargetCategory"
                  :options="targetCategoryOptions"
                  :placeholder="strings.selectCategory"
                  label="label"
                  track-by="id"
                />
              </div>
            </div>

            <div class="radio-group">
              <NcCheckboxRadioSwitch
                v-model="deleteDialog.action"
                value="delete"
                type="radio"
                name="delete-action"
              >
                {{ strings.softDeleteThreads }}
              </NcCheckboxRadioSwitch>
              <p class="help-text muted">{{ strings.softDeleteHelp }}</p>
            </div>
          </div>
        </div>

        <template #actions>
          <NcButton @click="deleteDialog.show = false">
            {{ strings.cancel }}
          </NcButton>
          <NcButton
            variant="error"
            :disabled="
              deleteDialog.action === 'migrate' &&
              deleteDialog.threadCount > 0 &&
              !selectedTargetCategory
            "
            @click="executeDelete"
          >
            {{ strings.deleteCategory }}
          </NcButton>
        </template>
      </NcDialog>

      <!-- Header Edit/Create Dialog -->
      <HeaderEditDialog
        :open="headerDialog.show"
        :header-id="headerDialog.id"
        :name="headerDialog.name"
        :description="headerDialog.description"
        :sort-order="headerDialog.sortOrder"
        @update:open="headerDialog.show = $event"
        @saved="handleHeaderSaved"
      />

      <!-- Header Delete Confirmation Dialog -->
      <NcDialog
        v-if="deleteHeaderDialog.show"
        :name="strings.deleteHeaderTitle"
        @close="deleteHeaderDialog.show = false"
      >
        <div class="delete-dialog-content">
          <p>{{ strings.deleteHeaderMessage(deleteHeaderDialog.header?.name || '') }}</p>

          <div v-if="deleteHeaderDialog.categoryCount > 0" class="thread-warning">
            <InformationIcon :size="20" />
            <span>{{ strings.headerCategoryWarning(deleteHeaderDialog.categoryCount) }}</span>
          </div>

          <div v-if="deleteHeaderDialog.categoryCount > 0" class="migration-options">
            <h4>{{ strings.whatToDoWithCategories }}</h4>

            <div class="radio-group">
              <NcCheckboxRadioSwitch
                v-model="deleteHeaderDialog.action"
                value="migrate"
                type="radio"
                name="delete-header-action"
              >
                {{ strings.migrateCategories }}
              </NcCheckboxRadioSwitch>

              <div v-if="deleteHeaderDialog.action === 'migrate'" class="category-select">
                <label>{{ strings.selectTargetHeader }}</label>
                <NcSelect
                  v-model="selectedTargetHeader"
                  :options="targetHeaderOptions"
                  :placeholder="strings.selectHeader"
                  label="label"
                  track-by="id"
                />
              </div>
            </div>

            <div class="radio-group">
              <NcCheckboxRadioSwitch
                v-model="deleteHeaderDialog.action"
                value="delete"
                type="radio"
                name="delete-header-action"
              >
                {{ strings.deleteCategories }}
              </NcCheckboxRadioSwitch>
              <p class="help-text muted">{{ strings.deleteCategoriesHelp }}</p>
            </div>
          </div>
        </div>

        <template #actions>
          <NcButton @click="deleteHeaderDialog.show = false">
            {{ strings.cancel }}
          </NcButton>
          <NcButton
            variant="error"
            :disabled="
              deleteHeaderDialog.action === 'migrate' &&
              deleteHeaderDialog.categoryCount > 0 &&
              !selectedTargetHeader
            "
            @click="executeDeleteHeader"
          >
            {{ strings.deleteHeader }}
          </NcButton>
        </template>
      </NcDialog>
    </div>
  </PageWrapper>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import PageWrapper from '@/components/PageWrapper.vue'
import PageHeader from '@/components/PageHeader.vue'
import AppToolbar from '@/components/AppToolbar.vue'
import HeaderEditDialog from '@/components/HeaderEditDialog.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import ChevronUpIcon from '@icons/ChevronUp.vue'
import ChevronDownIcon from '@icons/ChevronDown.vue'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import PlusIcon from '@icons/Plus.vue'
import PencilIcon from '@icons/Pencil.vue'
import DeleteIcon from '@icons/Delete.vue'
import InformationIcon from '@icons/Information.vue'
import { ocs } from '@/axios'
import { t, n } from '@nextcloud/l10n'
import { useCategories } from '@/composables/useCategories'
import type { CategoryHeader, Category, CatHeader } from '@/types'

export default defineComponent({
  name: 'AdminCategoryList',
  components: {
    PageWrapper,
    PageHeader,
    AppToolbar,
    HeaderEditDialog,
    NcButton,
    NcCheckboxRadioSwitch,
    NcDialog,
    NcEmptyContent,
    NcLoadingIcon,
    NcSelect,
    NcTextField,
    NcTextArea,
    PlusIcon,
    PencilIcon,
    DeleteIcon,
    InformationIcon,
    ChevronUpIcon,
    ChevronDownIcon,
  },
  setup() {
    const { categoryHeaders, loading, error, refresh: refreshCategories } = useCategories()
    return {
      categoryHeaders,
      loading,
      error,
      refreshCategories,
    }
  },
  data() {
    return {
      selectedTargetCategory: null as { id: number; label: string } | null,
      deleteDialog: {
        show: false,
        category: null as Category | null,
        threadCount: 0,
        action: 'migrate' as 'migrate' | 'delete',
        targetCategoryId: null as number | null,
      },
      headerDialog: {
        show: false,
        id: null as number | null,
        name: '',
        description: '',
        sortOrder: 0,
      },
      selectedTargetHeader: null as { id: number; label: string } | null,
      deleteHeaderDialog: {
        show: false,
        header: null as CatHeader | null,
        categoryCount: 0,
        action: 'migrate' as 'migrate' | 'delete',
        targetHeaderId: null as number | null,
      },

      strings: {
        title: t('forum', 'Categories'),
        subtitle: t('forum', 'Manage forum categories and organization'),
        loading: t('forum', 'Loading …'),
        errorTitle: t('forum', 'Error loading categories'),
        retry: t('forum', 'Retry'),
        createCategory: t('forum', 'Create category'),
        edit: t('forum', 'Edit'),
        delete: t('forum', 'Delete'),
        noCategories: t('forum', 'No categories in this header'),
        deleteDialogTitle: t('forum', 'Delete category'),
        deleteConfirmMessage: (name: string) =>
          t('forum', `Are you sure you want to delete the category "{name}"?`, { name }),
        threadWarning: (count: number) =>
          n(
            'forum',
            'This category contains %n thread.',
            'This category contains %n threads.',
            count,
          ),
        whatToDoWithThreads: t('forum', 'What should happen to the threads?'),
        migrateThreads: t('forum', 'Move threads to another category'),
        softDeleteThreads: t('forum', 'Delete all threads (soft delete)'),
        softDeleteHelp: t('forum', 'Threads will be hidden but not permanently deleted'),
        selectTargetCategory: t('forum', 'Select target category'),
        selectCategory: t('forum', '-- Select a category --'),
        cancel: t('forum', 'Cancel'),
        deleteCategory: t('forum', 'Delete category'),
        createHeader: t('forum', 'Create header'),
        categoriesCount: (count: number) => n('forum', '%n category', '%n categories', count),
        threadsCount: (count: number) => n('forum', '%n thread', '%n threads', count),
        postsCount: (count: number) => n('forum', '%n reply', '%n replies', count),
        deleteHeaderTitle: t('forum', 'Delete header'),
        deleteHeaderMessage: (name: string) =>
          t('forum', `Are you sure you want to delete the header "{name}"?`, { name }),
        headerCategoryWarning: (count: number) =>
          n(
            'forum',
            'This header contains %n category.',
            'This header contains %n categories.',
            count,
          ),
        deleteHeaderHelp: t('forum', 'This action cannot be undone'),
        deleteHeader: t('forum', 'Delete header'),
        whatToDoWithCategories: t('forum', 'What should happen to the categories?'),
        migrateCategories: t('forum', 'Move categories to another header'),
        deleteCategories: t('forum', 'Delete all categories'),
        deleteCategoriesHelp: t(
          'forum',
          'All categories and their threads will be permanently deleted',
        ),
        selectTargetHeader: t('forum', 'Select target header'),
        selectHeader: t('forum', '-- Select a header --'),
        moveUp: t('forum', 'Move up'),
        moveDown: t('forum', 'Move down'),
      },
    }
  },
  computed: {
    targetCategoryOptions(): Array<{ id: number; label: string; disabled?: boolean }> {
      const options: Array<{ id: number; label: string; disabled?: boolean }> = []

      this.categoryHeaders.forEach((header) => {
        if (header.categories) {
          header.categories.forEach((cat) => {
            options.push({
              id: cat.id,
              label: `${header.name} / ${cat.name}`,
              disabled: cat.id === this.deleteDialog.category?.id,
            })
          })
        }
      })

      return options
    },
    targetHeaderOptions(): Array<{ id: number; label: string; disabled?: boolean }> {
      return this.categoryHeaders
        .filter((header) => header.id !== this.deleteHeaderDialog.header?.id)
        .map((header) => ({
          id: header.id,
          label: header.name,
        }))
    },
  },
  watch: {
    selectedTargetCategory(newVal: { id: number; label: string } | null) {
      this.deleteDialog.targetCategoryId = newVal?.id || null
    },
    selectedTargetHeader(newVal: { id: number; label: string } | null) {
      this.deleteHeaderDialog.targetHeaderId = newVal?.id || null
    },
  },
  created() {
    this.refresh()
  },
  methods: {
    async refresh(): Promise<void> {
      await this.refreshCategories()
    },

    createCategory(): void {
      this.$router.push('/admin/categories/create')
    },

    editCategory(categoryId: number): void {
      this.$router.push(`/admin/categories/${categoryId}/edit`)
    },

    async confirmDelete(category: Category): Promise<void> {
      // Fetch thread count for this category
      try {
        const response = await ocs.get<{ count: number }>(`/categories/${category.id}/thread-count`)
        this.deleteDialog.threadCount = response.data?.count || category.threadCount || 0
      } catch (e) {
        console.error('Failed to fetch thread count', e)
        this.deleteDialog.threadCount = category.threadCount || 0
      }

      this.deleteDialog.category = category
      this.deleteDialog.action = 'migrate'
      this.deleteDialog.targetCategoryId = null
      this.selectedTargetCategory = null
      this.deleteDialog.show = true
    },

    async executeDelete(): Promise<void> {
      if (!this.deleteDialog.category) return

      try {
        const params: Record<string, number | undefined> = {}

        if (this.deleteDialog.action === 'migrate' && this.deleteDialog.targetCategoryId) {
          params.migrateToCategoryId = this.deleteDialog.targetCategoryId
        }

        await ocs.delete(`/categories/${this.deleteDialog.category.id}`, { params })

        this.deleteDialog.show = false
        this.refresh()
      } catch (e) {
        console.error('Failed to delete category', e)
        // TODO: Show error notification
      }
    },

    createHeader(): void {
      this.headerDialog.show = true
      this.headerDialog.id = null
      this.headerDialog.name = ''
      this.headerDialog.description = ''
      // Set sort order to the count of headers (will be last)
      this.headerDialog.sortOrder = this.categoryHeaders.length
    },

    editHeaderById(headerId: number): void {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header) return

      this.headerDialog.show = true
      this.headerDialog.id = header.id
      this.headerDialog.name = header.name
      this.headerDialog.description = header.description || ''
      this.headerDialog.sortOrder = header.sortOrder || 0
    },

    async handleHeaderSaved(): Promise<void> {
      this.headerDialog.show = false
      await this.refresh()
    },

    confirmDeleteHeader(header: CatHeader): void {
      const categoryCount =
        this.categoryHeaders.find((h) => h.id === header.id)?.categories?.length || 0

      this.deleteHeaderDialog.header = header
      this.deleteHeaderDialog.categoryCount = categoryCount
      this.deleteHeaderDialog.action = 'migrate'
      this.deleteHeaderDialog.targetHeaderId = null
      this.selectedTargetHeader = null
      this.deleteHeaderDialog.show = true
    },

    async executeDeleteHeader(): Promise<void> {
      if (!this.deleteHeaderDialog.header) return

      try {
        const params: Record<string, number | undefined> = {}

        if (
          this.deleteHeaderDialog.action === 'migrate' &&
          this.deleteHeaderDialog.targetHeaderId
        ) {
          params.migrateToHeaderId = this.deleteHeaderDialog.targetHeaderId
        }

        await ocs.delete(`/headers/${this.deleteHeaderDialog.header.id}`, { params })

        this.deleteHeaderDialog.show = false
        this.refresh()
      } catch (e) {
        console.error('Failed to delete header', e)
        // TODO: Show error notification
      }
    },

    async moveHeaderUp(index: number): Promise<void> {
      if (index <= 0) return

      // Update sort orders on backend
      await this.updateHeaderSortOrders(index, -1)
    },

    async moveHeaderDown(index: number): Promise<void> {
      if (index >= this.categoryHeaders.length - 1) return

      // Update sort orders on backend
      await this.updateHeaderSortOrders(index, 1)
    },

    async updateHeaderSortOrders(index: number, amount: number): Promise<void> {
      // Swap positions locally
      const temp = this.categoryHeaders[index]
      const swapTarget = this.categoryHeaders[index + amount]
      if (!temp || !swapTarget) return

      this.categoryHeaders[index] = swapTarget
      this.categoryHeaders[index + amount] = temp

      try {
        // Build array of header IDs in their current order
        const sortOrders = this.categoryHeaders.map((header, idx) => ({
          id: header.id,
          sortOrder: idx,
        }))

        await ocs.post('/headers/reorder', { headers: sortOrders })

        // Refresh sidebar categories silently
        await this.refreshCategories(true)
      } catch (e) {
        console.error('Failed to update header sort orders', e)
        // Revert the swap on error
        const revertTemp = this.categoryHeaders[index + amount]
        if (revertTemp && this.categoryHeaders[index]) {
          this.categoryHeaders[index + amount] = this.categoryHeaders[index]
          this.categoryHeaders[index] = revertTemp
        }
      }
    },

    async moveCategoryUp(headerId: number, index: number): Promise<void> {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header || !header.categories || index <= 0) return

      // Update sort orders on backend
      await this.updateCategorySortOrders(headerId, index, -1)
    },

    async moveCategoryDown(headerId: number, index: number): Promise<void> {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header || !header.categories || index >= header.categories.length - 1) return

      // Update sort orders on backend
      await this.updateCategorySortOrders(headerId, index, 1)
    },

    async updateCategorySortOrders(headerId: number, index: number, amount: number): Promise<void> {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header || !header.categories) return

      // Swap positions locally
      const temp = header.categories[index]
      const swapTarget = header.categories[index + amount]
      if (!temp || !swapTarget) return

      header.categories[index] = swapTarget
      header.categories[index + amount] = temp

      try {
        // Build array of category IDs in their current order
        const sortOrders = header.categories.map((category, idx) => ({
          id: category.id,
          sortOrder: idx,
        }))

        await ocs.post('/categories/reorder', { categories: sortOrders })

        // Refresh sidebar categories silently
        await this.refreshCategories(true)
      } catch (e) {
        console.error('Failed to update category sort orders', e)
        // Revert the swap on error
        const revertTemp = header.categories[index + amount]
        const revertCurrent = header.categories[index]
        if (revertTemp && revertCurrent) {
          header.categories[index + amount] = revertCurrent
          header.categories[index] = revertTemp
        }
      }
    },
  },
})
</script>

<style scoped lang="scss">
.admin-category-list {
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

  .category-list {
    .categories-section {
      display: flex;
      flex-direction: column;
    }

    .header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      padding: 16px;
      background: var(--color-main-background);
      border: 1px solid var(--color-border);
      border-radius: 8px;
      margin-bottom: 12px;
      margin-top: 32px;

      &:first-child {
        margin-top: 0;
      }

      &:hover {
        background: var(--color-background-hover);
      }

      .header-sort-buttons {
        display: flex;
        flex-direction: column;
        gap: 4px;
      }

      .header-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 6px;

        h3 {
          margin: 0;
          font-size: 1.3rem;
          font-weight: 600;
        }

        span {
          font-size: 0.9rem;
        }

        .category-count {
          font-size: 0.85rem;
        }
      }

      .header-actions {
        display: flex;
        gap: 8px;
      }
    }

    .categories-table {
      display: flex;
      flex-direction: column;
      gap: 1px;
      background: var(--color-border);
      border-radius: 8px;
      overflow: hidden;

      .category-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 16px;
        background: var(--color-main-background);

        &:hover {
          background: var(--color-background-hover);
        }

        .category-sort-buttons {
          display: flex;
          flex-direction: column;
          gap: 4px;
        }

        .category-info {
          flex: 1;
          display: flex;
          flex-direction: column;
          gap: 6px;

          .category-name {
            font-weight: 600;
            font-size: 1.05rem;
          }

          .category-desc {
            font-size: 0.9rem;
          }

          .category-meta {
            display: flex;
            gap: 8px;
            font-size: 0.85rem;
          }
        }

        .category-actions {
          display: flex;
          gap: 8px;
        }
      }
    }

    .no-categories {
      padding: 16px;
      text-align: center;
      font-style: italic;
    }
  }
}

.delete-dialog-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 8px 0;

  .thread-warning {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: var(--color-warning);
    border-radius: 6px;
    color: var(--color-main-text);
  }

  .migration-options {
    display: flex;
    flex-direction: column;
    gap: 12px;

    h4 {
      margin: 0;
      font-size: 1rem;
    }

    .radio-group {
      display: flex;
      flex-direction: column;
      gap: 8px;

      .category-select {
        margin-left: 32px;
        display: flex;
        flex-direction: column;
        gap: 6px;

        label {
          font-weight: 500;
          font-size: 0.9rem;
          margin-bottom: 4px;
        }
      }

      .help-text {
        margin-left: 32px;
        font-size: 0.85rem;
      }
    }
  }
}
</style>
