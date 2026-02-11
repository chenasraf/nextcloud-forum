<template>
  <NcDialog :name="strings.title" :open="open" @update:open="handleClose" size="small">
    <div class="move-category-dialog">
      <p class="dialog-description">{{ strings.description }}</p>

      <!-- Loading state -->
      <div v-if="loading" class="loading-state">
        <NcLoadingIcon :size="32" />
        <span class="loading-text">{{ strings.loading }}</span>
      </div>

      <!-- Error state -->
      <div v-else-if="error" class="error-state">
        <span class="error-text">{{ error }}</span>
      </div>

      <!-- Category selection -->
      <div v-else class="category-selection">
        <NcSelect
          v-model="selectedCategory"
          :options="categoryOptions"
          :placeholder="strings.selectPlaceholder"
          label="name"
          track-by="id"
          :clearable="false"
          class="category-select"
        />

        <!-- Error when header is selected -->
        <NcNoteCard v-if="selectedCategory && selectedCategory.isHeader" type="error" class="mt-12">
          {{ strings.headerSelectedError }}
        </NcNoteCard>

        <!-- Warning when same category is selected -->
        <NcNoteCard
          v-if="
            selectedCategory &&
            !selectedCategory.isHeader &&
            selectedCategory.id === currentCategoryId
          "
          type="warning"
          class="mt-12"
        >
          {{ strings.sameCategoryWarning }}
        </NcNoteCard>
      </div>
    </div>

    <template #actions>
      <NcButton @click="handleClose">
        {{ strings.cancel }}
      </NcButton>
      <NcButton
        variant="primary"
        @click="handleMove"
        :disabled="
          !selectedCategory ||
          selectedCategory.isHeader ||
          moving ||
          selectedCategory.id === currentCategoryId
        "
      >
        <template #icon>
          <NcLoadingIcon v-if="moving" :size="20" />
        </template>
        {{ strings.move }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { t } from '@nextcloud/l10n'
import { useCategories } from '@/composables/useCategories'
import type { Category } from '@/types'

interface CategoryOption {
  id: number
  name: string
  isHeader?: boolean
}

export default defineComponent({
  name: 'MoveCategoryDialog',
  components: {
    NcDialog,
    NcButton,
    NcLoadingIcon,
    NcSelect,
    NcNoteCard,
  },
  props: {
    open: {
      type: Boolean,
      required: true,
    },
    currentCategoryId: {
      type: Number as PropType<number>,
      required: true,
    },
  },
  emits: ['update:open', 'move'],
  setup() {
    const { categoryHeaders, fetchCategories } = useCategories()

    return {
      categoryHeaders,
      fetchCategories,
    }
  },
  data() {
    return {
      loading: false,
      error: null as string | null,
      selectedCategory: null as CategoryOption | null,
      moving: false,

      strings: {
        title: t('forum', 'Move thread to category'),
        description: t('forum', 'Select the category to move this thread to:'),
        selectPlaceholder: t('forum', 'Select a category …'),
        loading: t('forum', 'Loading categories …'),
        cancel: t('forum', 'Cancel'),
        move: t('forum', 'Move'),
        headerSelectedError: t(
          'forum',
          'Cannot move to a category header. Please select a category instead.',
        ),
        sameCategoryWarning: t('forum', 'This thread is already in this category.'),
      },
    }
  },
  computed: {
    categoryOptions(): CategoryOption[] {
      const options: CategoryOption[] = []

      for (const header of this.categoryHeaders) {
        // Add header as a disabled option
        if (header.categories && header.categories.length > 0) {
          options.push({
            id: -header.id, // Negative ID to distinguish from categories
            name: header.name,
            isHeader: true,
          })

          // Add categories under this header
          for (const category of header.categories) {
            options.push({
              id: category.id,
              name: `  ${category.name}`,
              isHeader: false,
            })
          }
        }
      }

      return options
    },
  },
  watch: {
    open: {
      immediate: true,
      handler(newValue) {
        if (newValue) {
          this.moving = false
          this.loadCategories()
          this.selectedCategory = null
        }
      },
    },
  },
  methods: {
    async loadCategories() {
      try {
        this.loading = true
        this.error = null
        await this.fetchCategories()
      } catch (e) {
        console.error('Failed to load categories:', e)
        this.error = t('forum', 'Failed to load categories')
      } finally {
        this.loading = false
      }
    },

    handleClose() {
      this.$emit('update:open', false)
    },

    handleMove() {
      if (!this.selectedCategory || this.selectedCategory.isHeader) {
        return
      }

      this.moving = true
      this.$emit('move', this.selectedCategory.id)
    },

    reset() {
      this.moving = false
      this.selectedCategory = null
    },
  },
})
</script>

<style scoped lang="scss">
.move-category-dialog {
  padding: 16px 0;
}

.dialog-description {
  margin: 0 0 16px 0;
  font-size: 0.95rem;
  color: var(--color-text-lighter);
  line-height: 1.5;
}

.loading-state,
.error-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 32px 16px;
  text-align: center;
}

.loading-text {
  font-size: 0.9rem;
  color: var(--color-text-maxcontrast);
}

.error-text {
  font-size: 0.9rem;
  color: var(--color-error);
}

.category-selection {
  margin-bottom: 16px;

  .category-select {
    width: 100%;
  }

  .mt-12 {
    margin-top: 12px;
  }
}

.category-option {
  padding: 4px 0;
}

.category-header {
  font-weight: 600;
  color: var(--color-text-maxcontrast);
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.category-name {
  color: var(--color-main-text);
}
</style>
