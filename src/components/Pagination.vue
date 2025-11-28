<template>
  <nav v-if="maxPages > 1" class="pagination" :aria-label="strings.pagination">
    <!-- First page button -->
    <NcButton
      variant="tertiary"
      :disabled="currentPage === 1"
      :aria-label="strings.firstPage"
      :title="strings.firstPage"
      @click="goToPage(1)"
    >
      <template #icon>
        <PageFirstIcon :size="20" />
      </template>
    </NcButton>

    <!-- Previous page button -->
    <NcButton
      variant="tertiary"
      :disabled="currentPage === 1"
      :aria-label="strings.previousPage"
      :title="strings.previousPage"
      @click="goToPage(currentPage - 1)"
    >
      <template #icon>
        <ChevronLeftIcon :size="20" />
      </template>
    </NcButton>

    <!-- Page numbers -->
    <div class="pagination-pages">
      <template v-for="(item, index) in pageItems" :key="index">
        <span v-if="item === 'ellipsis'" class="pagination-ellipsis">…</span>
        <NcButton
          v-else
          :variant="item === currentPage ? 'primary' : 'tertiary'"
          :aria-label="strings.goToPage(item)"
          :aria-current="item === currentPage ? 'page' : undefined"
          @click="goToPage(item)"
        >
          {{ item }}
        </NcButton>
      </template>
    </div>

    <!-- Next page button -->
    <NcButton
      variant="tertiary"
      :disabled="currentPage === maxPages"
      :aria-label="strings.nextPage"
      :title="strings.nextPage"
      @click="goToPage(currentPage + 1)"
    >
      <template #icon>
        <ChevronRightIcon :size="20" />
      </template>
    </NcButton>

    <!-- Last page button -->
    <NcButton
      variant="tertiary"
      :disabled="currentPage === maxPages"
      :aria-label="strings.lastPage"
      :title="strings.lastPage"
      @click="goToPage(maxPages)"
    >
      <template #icon>
        <PageLastIcon :size="20" />
      </template>
    </NcButton>
  </nav>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import PageFirstIcon from '@icons/PageFirst.vue'
import PageLastIcon from '@icons/PageLast.vue'
import ChevronLeftIcon from '@icons/ChevronLeft.vue'
import ChevronRightIcon from '@icons/ChevronRight.vue'
import { t } from '@nextcloud/l10n'

export default defineComponent({
  name: 'Pagination',
  components: {
    NcButton,
    PageFirstIcon,
    PageLastIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
  },
  props: {
    currentPage: {
      type: Number,
      required: true,
    },
    maxPages: {
      type: Number,
      required: true,
    },
  },
  emits: ['update:currentPage'],
  data() {
    return {
      strings: {
        pagination: t('forum', 'Pagination'),
        firstPage: t('forum', 'First page'),
        previousPage: t('forum', 'Previous page'),
        nextPage: t('forum', 'Next page'),
        lastPage: t('forum', 'Last page'),
        goToPage: (page: number) => t('forum', 'Go to page {page}', { page }),
      },
    }
  },
  computed: {
    /**
     * Calculate which page numbers and ellipses to show.
     * Shows first 3 pages, last 3 pages, and 2 pages on each side of current page.
     * Ellipses are shown when there are gaps.
     */
    pageItems(): (number | 'ellipsis')[] {
      const items: (number | 'ellipsis')[] = []
      const current = this.currentPage
      const max = this.maxPages

      // Edge case: 10 or fewer pages - show all
      if (max <= 10) {
        for (let i = 1; i <= max; i++) {
          items.push(i)
        }
        return items
      }

      // First 3 pages
      const firstPages = [1, 2, 3]
      // Last 3 pages
      const lastPages = [max - 2, max - 1, max]
      // Pages around current (current - 2 to current + 2)
      const aroundCurrent: number[] = []
      for (let i = current - 2; i <= current + 2; i++) {
        if (i > 0 && i <= max) {
          aroundCurrent.push(i)
        }
      }

      // Combine all pages and remove duplicates
      const allPages = new Set([...firstPages, ...aroundCurrent, ...lastPages])
      const sortedPages = Array.from(allPages).sort((a, b) => a - b)

      // Build items array with ellipses for gaps
      let prevPage = 0
      for (const page of sortedPages) {
        if (prevPage > 0 && page - prevPage > 1) {
          items.push('ellipsis')
        }
        items.push(page)
        prevPage = page
      }

      return items
    },
  },
  methods: {
    goToPage(page: number): void {
      if (page >= 1 && page <= this.maxPages && page !== this.currentPage) {
        this.$emit('update:currentPage', page)
      }
    },
  },
})
</script>

<style scoped lang="scss">
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  flex-wrap: wrap;
}

.pagination-pages {
  display: flex;
  align-items: center;
  gap: 2px;
}

.pagination-ellipsis {
  padding: 0 8px;
  color: var(--color-text-maxcontrast);
  user-select: none;
}
</style>
