<template>
  <div class="permissions-wrapper">
    <NcNoteCard type="info">
      <ul class="permissions-info-list">
        <li v-html="strings.infoView" />
        <li v-html="strings.infoPost" />
        <li v-html="strings.infoReply" />
        <li v-html="strings.infoModerate" />
      </ul>
    </NcNoteCard>

    <div v-if="categoryHeaders.length > 0" class="permissions-table">
      <div class="table-header">
        <div class="col-category">{{ strings.category }}</div>
        <div class="col-permission">{{ strings.canView }}</div>
        <div class="col-permission">{{ strings.canPost }}</div>
        <div class="col-permission">{{ strings.canReply }}</div>
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
            >
              {{ strings.allowAll }}
            </NcCheckboxRadioSwitch>
          </div>
          <div class="header-permission">
            <NcCheckboxRadioSwitch
              :model-value="getHeaderPostState(header.id).checked"
              :indeterminate="getHeaderPostState(header.id).indeterminate"
              :disabled="disablePost"
              @update:model-value="toggleHeaderPost(header.id)"
            >
              {{ strings.allowAll }}
            </NcCheckboxRadioSwitch>
          </div>
          <div class="header-permission">
            <NcCheckboxRadioSwitch
              :model-value="getHeaderReplyState(header.id).checked"
              :indeterminate="getHeaderReplyState(header.id).indeterminate"
              :disabled="disableReply"
              @update:model-value="toggleHeaderReply(header.id)"
            >
              {{ strings.allowAll }}
            </NcCheckboxRadioSwitch>
          </div>
          <div class="header-permission">
            <NcCheckboxRadioSwitch
              :model-value="getHeaderModerateState(header.id).checked"
              :indeterminate="getHeaderModerateState(header.id).indeterminate"
              :disabled="disableModerate"
              @update:model-value="toggleHeaderModerate(header.id)"
            >
              {{ strings.allowAll }}
            </NcCheckboxRadioSwitch>
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
              :model-value="permissions[category.id]?.canPost || false"
              :disabled="disablePost"
              @update:model-value="updateCategoryPost(category.id, $event)"
            >
              {{ strings.allow }}
            </NcCheckboxRadioSwitch>
          </div>

          <div class="col-permission">
            <NcCheckboxRadioSwitch
              :model-value="permissions[category.id]?.canReply || false"
              :disabled="disableReply"
              @update:model-value="updateCategoryReply(category.id, $event)"
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
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { t } from '@nextcloud/l10n'
import type { CategoryHeader } from '@/types'

export interface CategoryPermission {
  canView: boolean
  canPost: boolean
  canReply: boolean
  canModerate: boolean
}

export default defineComponent({
  name: 'CategoryPermissionsTable',
  components: {
    NcCheckboxRadioSwitch,
    NcNoteCard,
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
    disablePost: {
      type: Boolean,
      default: false,
    },
    disableReply: {
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
        canPost: t('forum', 'Can post'),
        canReply: t('forum', 'Can reply'),
        canModerate: t('forum', 'Can moderate'),
        allow: t('forum', 'Allow'),
        allowAll: t('forum', 'Allow All'),
        noCategories: t('forum', 'No categories available'),
        infoView: t(
          'forum',
          '{bStart}View:{bEnd} Allows seeing the category and its threads.',
          { bStart: '<strong>', bEnd: '</strong>' },
          { escape: false },
        ),
        infoPost: t(
          'forum',
          '{bStart}Post:{bEnd} Allows creating new threads in the category.',
          { bStart: '<strong>', bEnd: '</strong>' },
          { escape: false },
        ),
        infoReply: t(
          'forum',
          '{bStart}Reply:{bEnd} Allows replying to existing threads in the category.',
          { bStart: '<strong>', bEnd: '</strong>' },
          { escape: false },
        ),
        infoModerate: t(
          'forum',
          '{bStart}Moderate:{bEnd} Allows editing and deleting posts, pinning, locking, and moving threads in the category.',
          { bStart: '<strong>', bEnd: '</strong>' },
          { escape: false },
        ),
      },
    }
  },
  methods: {
    ensurePermission(categoryId: number): CategoryPermission {
      if (!this.permissions[categoryId]) {
        this.permissions[categoryId] = {
          canView: false,
          canPost: false,
          canReply: false,
          canModerate: false,
        }
      }
      return this.permissions[categoryId]
    },

    getHeaderState(
      headerId: number,
      key: keyof CategoryPermission,
    ): { checked: boolean; indeterminate: boolean } {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header || !header.categories || header.categories.length === 0) {
        return { checked: false, indeterminate: false }
      }

      const checkedCount = header.categories.filter((cat) => this.permissions[cat.id]?.[key]).length
      const totalCount = header.categories.length

      if (checkedCount === 0) {
        return { checked: false, indeterminate: false }
      } else if (checkedCount === totalCount) {
        return { checked: true, indeterminate: false }
      } else {
        return { checked: false, indeterminate: true }
      }
    },

    getHeaderViewState(headerId: number) {
      return this.getHeaderState(headerId, 'canView')
    },
    getHeaderPostState(headerId: number) {
      return this.getHeaderState(headerId, 'canPost')
    },
    getHeaderReplyState(headerId: number) {
      return this.getHeaderState(headerId, 'canReply')
    },
    getHeaderModerateState(headerId: number) {
      return this.getHeaderState(headerId, 'canModerate')
    },

    updateCategoryView(categoryId: number, checked: boolean): void {
      this.updatePermission(categoryId, 'canView', checked)
    },
    updateCategoryPost(categoryId: number, checked: boolean): void {
      this.updatePermission(categoryId, 'canPost', checked)
    },
    updateCategoryReply(categoryId: number, checked: boolean): void {
      this.updatePermission(categoryId, 'canReply', checked)
    },
    updateCategoryModerate(categoryId: number, checked: boolean): void {
      this.updatePermission(categoryId, 'canModerate', checked)
    },

    toggleHeader(headerId: number, key: keyof CategoryPermission): void {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header || !header.categories) return

      const newValue = !this.getHeaderState(headerId, key).checked
      header.categories.forEach((cat) => {
        this.ensurePermission(cat.id)[key] = newValue
      })
      this.$emit('update:permissions', this.permissions)
    },

    toggleHeaderView(headerId: number): void {
      this.toggleHeader(headerId, 'canView')
    },
    toggleHeaderPost(headerId: number): void {
      this.toggleHeader(headerId, 'canPost')
    },
    toggleHeaderReply(headerId: number): void {
      this.toggleHeader(headerId, 'canReply')
    },
    toggleHeaderModerate(headerId: number): void {
      this.toggleHeader(headerId, 'canModerate')
    },

    updatePermission(categoryId: number, key: keyof CategoryPermission, checked: boolean): void {
      this.ensurePermission(categoryId)[key] = checked
      this.$emit('update:permissions', this.permissions)
    },
  },
})
</script>

<style scoped lang="scss">
.permissions-wrapper {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.permissions-info-list {
  margin: 0;
  padding-left: 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.permissions-table {
  display: flex;
  flex-direction: column;
  gap: 1px;
  background: var(--color-border);
  border-radius: 8px;
  overflow: hidden;
  --perm-column-width: 125px;

  .table-header,
  .table-row {
    display: grid;
    grid-template-columns: 1fr repeat(4, var(--perm-column-width));
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
    grid-template-columns: 1fr repeat(4, var(--perm-column-width));
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
