<template>
  <NcAppNavigationItem
    :name="category.name"
    :to="{ path: `/c/${category.slug}` }"
    :active="active"
    :allow-collapse="hasChildren"
    :open="isOpen"
    @update:open="$emit('toggle', category.id, $event)"
  >
    <template #icon>
      <ForumIcon :size="20" />
    </template>

    <template v-if="unreadCount > 0" #counter>
      <NcCounterBubble :count="unreadCount" :active="active" :title="unreadLabel" />
    </template>

    <!-- Recursive children -->
    <template v-if="hasChildren">
      <NavCategoryItem
        v-for="child in category.children"
        :key="`category-${child.id}`"
        :category="child"
        :active="activeCategoryIds.has(child.id)"
        :active-category-ids="activeCategoryIds"
        :open-categories="openCategories"
        :show-unread-counts="showUnreadCounts"
        @toggle="(id: number, open: boolean) => $emit('toggle', id, open)"
      />
    </template>
  </NcAppNavigationItem>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { n } from '@nextcloud/l10n'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import ForumIcon from '@icons/Forum.vue'
import { useCategories } from '@/composables/useCategories'
import type { Category } from '@/types'

export default defineComponent({
  name: 'NavCategoryItem',
  components: {
    NcAppNavigationItem,
    NcCounterBubble,
    ForumIcon,
  },
  props: {
    category: {
      type: Object as PropType<Category>,
      required: true,
    },
    active: {
      type: Boolean,
      default: false,
    },
    activeCategoryIds: {
      type: Set as unknown as PropType<Set<number>>,
      default: () => new Set(),
    },
    /** Expanded state per category ID; categories missing from the map are expanded */
    openCategories: {
      type: Object as PropType<Record<number, boolean>>,
      default: () => ({}),
    },
    showUnreadCounts: {
      type: Boolean,
      default: false,
    },
  },
  emits: {
    toggle: (_id: number, _open: boolean) => true,
  },
  setup() {
    const { getUnreadCount } = useCategories()
    return { getUnreadCount }
  },
  computed: {
    hasChildren(): boolean {
      return (this.category.children?.length ?? 0) > 0
    },
    isOpen(): boolean {
      return this.openCategories[this.category.id] !== false
    },
    unreadCount(): number {
      if (!this.showUnreadCounts) {
        return 0
      }
      // A collapsed category hides its subcategories, so it reports their unread threads too
      return this.getUnreadCount(this.category, this.hasChildren && !this.isOpen)
    },
    unreadLabel(): string {
      return n('forum', '%n unread thread', '%n unread threads', this.unreadCount)
    },
  },
})
</script>
