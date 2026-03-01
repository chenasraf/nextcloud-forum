<template>
  <div v-if="categoryHeaders.length > 0" class="permissions-table">
    <div class="table-header">
      <div class="col-category">{{ strings.category }}</div>
      <div class="col-permission">{{ strings.canView }}</div>
      <div class="col-permission">{{ strings.canModerate }}</div>
    </div>

    <template v-for="header in categoryHeaders" :key="`header-${header.id}`">
      <!-- Header row -->
      <div class="table-header-row">
        <div class="header-name">{{ header.name }}</div>
        <div class="header-permission">
          <NcCheckboxRadioSwitch
            :model-value="getHeaderViewState(header.id).checked"
            :indeterminate="getHeaderViewState(header.id).indeterminate"
            :disabled="disableView"
            @update:model-value="toggleHeaderView(header.id)"
          />
        </div>
        <div class="header-permission">
          <NcCheckboxRadioSwitch
            :model-value="getHeaderModerateState(header.id).checked"
            :indeterminate="getHeaderModerateState(header.id).indeterminate"
            :disabled="disableModerate"
            @update:model-value="toggleHeaderModerate(header.id)"
          />
        </div>
      </div>

      <!-- Category rows under this header -->
      <div v-for="category in header.categories" :key="category.id" class="table-row">
        <div class="col-category">
          <span class="category-name">{{ category.name }}</span>
          <span v-if="category.description" class="category-desc muted">
            {{ category.description }}
          </span>
        </div>

        <div class="col-permission">
          <NcCheckboxRadioSwitch
            :model-value="permissions[category.id]?.canView || false"
            :disabled="disableView"
            @update:model-value="updateCategoryView(category.id, $event)"
          >
            {{ strings.allow }}
          </NcCheckboxRadioSwitch>
        </div>

        <div class="col-permission">
          <NcCheckboxRadioSwitch
            :model-value="permissions[category.id]?.canModerate || false"
            :disabled="disableModerate"
            @update:model-value="updateCategoryModerate(category.id, $event)"
          >
            {{ strings.allow }}
          </NcCheckboxRadioSwitch>
        </div>
      </div>
    </template>
  </div>
  <div v-else class="muted">{{ strings.noCategories }}</div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { t } from '@nextcloud/l10n'
import type { CategoryHeader } from '@/types'

export interface CategoryPermission {
  canView: boolean
  canModerate: boolean
}

export default defineComponent({
  name: 'CategoryPermissionsTable',
  components: {
    NcCheckboxRadioSwitch,
  },
  props: {
    categoryHeaders: {
      type: Array as PropType<CategoryHeader[]>,
      required: true,
    },
    permissions: {
      type: Object as PropType<Record<number, CategoryPermission>>,
      required: true,
    },
    disableView: {
      type: Boolean,
      default: false,
    },
    disableModerate: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['update:permissions'],
  data() {
    return {
      strings: {
        category: t('forum', 'Category'),
        canView: t('forum', 'Can view'),
        canModerate: t('forum', 'Can moderate'),
        allow: t('forum', 'Allow'),
        noCategories: t('forum', 'No categories available'),
      },
    }
  },
  methods: {
    ensurePermission(categoryId: number): CategoryPermission {
      if (!this.permissions[categoryId]) {
        this.permissions[categoryId] = {
          canView: false,
          canModerate: false,
        }
      }
      return this.permissions[categoryId]
    },

    getHeaderViewState(headerId: number): { checked: boolean; indeterminate: boolean } {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header || !header.categories || header.categories.length === 0) {
        return { checked: false, indeterminate: false }
      }

      const checkedCount = header.categories.filter(
        (cat) => this.permissions[cat.id]?.canView,
      ).length
      const totalCount = header.categories.length

      if (checkedCount === 0) {
        return { checked: false, indeterminate: false }
      } else if (checkedCount === totalCount) {
        return { checked: true, indeterminate: false }
      } else {
        return { checked: false, indeterminate: true }
      }
    },

    getHeaderModerateState(headerId: number): { checked: boolean; indeterminate: boolean } {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header || !header.categories || header.categories.length === 0) {
        return { checked: false, indeterminate: false }
      }

      const checkedCount = header.categories.filter(
        (cat) => this.permissions[cat.id]?.canModerate,
      ).length
      const totalCount = header.categories.length

      if (checkedCount === 0) {
        return { checked: false, indeterminate: false }
      } else if (checkedCount === totalCount) {
        return { checked: true, indeterminate: false }
      } else {
        return { checked: false, indeterminate: true }
      }
    },

    updateCategoryView(categoryId: number, checked: boolean): void {
      this.ensurePermission(categoryId).canView = checked
      this.$emit('update:permissions', this.permissions)
    },

    updateCategoryModerate(categoryId: number, checked: boolean): void {
      this.ensurePermission(categoryId).canModerate = checked
      this.$emit('update:permissions', this.permissions)
    },

    toggleHeaderView(headerId: number): void {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header || !header.categories) return

      const state = this.getHeaderViewState(headerId)
      const newValue = !state.checked

      header.categories.forEach((cat) => {
        this.ensurePermission(cat.id).canView = newValue
      })
      this.$emit('update:permissions', this.permissions)
    },

    toggleHeaderModerate(headerId: number): void {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header || !header.categories) return

      const state = this.getHeaderModerateState(headerId)
      const newValue = !state.checked

      header.categories.forEach((cat) => {
        this.ensurePermission(cat.id).canModerate = newValue
      })
      this.$emit('update:permissions', this.permissions)
    },
  },
})
</script>

<style scoped lang="scss">
.permissions-table {
  display: flex;
  flex-direction: column;
  gap: 1px;
  background: var(--color-border);
  border-radius: 8px;
  overflow: hidden;

  .table-header,
  .table-row {
    display: grid;
    grid-template-columns: 1fr 150px 150px;
    gap: 16px;
    padding: 16px;
    background: var(--color-main-background);
    align-items: center;
  }

  .table-header {
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-maxcontrast);
    background: var(--color-background-hover);
  }

  .table-header-row {
    display: grid;
    grid-template-columns: 1fr 150px 150px;
    gap: 16px;
    padding: 12px 16px;
    background: var(--color-background-dark);
    align-items: center;

    .header-name {
      font-weight: 600;
      font-size: 1rem;
      color: var(--color-main-text);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .header-permission {
      display: flex;
      align-items: center;
    }
  }

  .table-row {
    &:hover {
      background: var(--color-background-hover);
    }

    .col-category {
      display: flex;
      flex-direction: column;
      gap: 4px;

      .category-name {
        font-weight: 500;
        color: var(--color-main-text);
      }

      .category-desc {
        font-size: 0.85rem;
      }
    }

    .col-permission {
      display: flex;
      align-items: center;
    }
  }
}
</style>
