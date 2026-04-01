<template>
  <NcAppNavigationItem :name="category.name" :to="{ path: `/c/${category.slug}` }" :active="active">
    <template #icon>
      <ForumIcon :size="20" />
    </template>

    <!-- Recursive children -->
    <template v-if="category.children && category.children.length > 0">
      <NavCategoryItem
        v-for="child in category.children"
        :key="`category-${child.id}`"
        :category="child"
        :active="activeCategoryIds.has(child.id)"
        :active-category-ids="activeCategoryIds"
      />
    </template>
  </NcAppNavigationItem>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import ForumIcon from '@icons/Forum.vue'
import type { Category } from '@/types'

export default defineComponent({
  name: 'NavCategoryItem',
  components: {
    NcAppNavigationItem,
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
  },
})
</script>
