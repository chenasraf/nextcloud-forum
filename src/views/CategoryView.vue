<template>
  <div class="category-view">
    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <NcButton @click="goBack">
          <template #icon>
            <ArrowLeftIcon :size="20" />
          </template>
          {{ strings.back }}
        </NcButton>
      </div>

      <div class="toolbar-right">
        <NcButton @click="refresh" :disabled="loading" :aria-label="strings.refresh">
          <template #icon>
            <RefreshIcon :size="20" />
          </template>
        </NcButton>
        <NcButton @click="createThread" :disabled="loading" variant="primary">
          <template #icon>
            <MessagePlusIcon :size="20" />
          </template>
          {{ strings.newThread }}
        </NcButton>
      </div>
    </div>

    <!-- Category Header -->
    <div v-if="category && !loading" class="category-header mt-16">
      <h2 class="category-name">{{ category.name }}</h2>
      <p v-if="category.description" class="category-description">{{ category.description }}</p>
    </div>

    <!-- Loading state -->
    <div class="center mt-16" v-if="loading">
      <NcLoadingIcon :size="32" />
      <span class="muted ml-8">{{ strings.loading }}</span>
    </div>

    <!-- Error state -->
    <NcEmptyContent v-else-if="error" :title="strings.errorTitle" :description="error" class="mt-16">
      <template #action>
        <NcButton @click="refresh">
          <template #icon>
            <RefreshIcon :size="20" />
          </template>
          {{ strings.retry }}
        </NcButton>
      </template>
    </NcEmptyContent>

    <!-- Empty state -->
    <NcEmptyContent v-else-if="threads.length === 0" :title="strings.emptyTitle" :description="strings.emptyDesc"
      class="mt-16">
      <template #action>
        <NcButton @click="createThread" variant="primary">
          <template #icon>
            <MessagePlusIcon :size="20" />
          </template>
          {{ strings.newThread }}
        </NcButton>
      </template>
    </NcEmptyContent>

    <!-- Threads list -->
    <section v-else class="mt-16">
      <div class="threads-list">
        <ThreadCard v-for="thread in sortedThreads" :key="thread.id" :thread="thread"
          :is-unread="isThreadUnread(thread)" @click="navigateToThread(thread)" />
      </div>

      <!-- Pagination info -->
      <div v-if="threads.length >= limit" class="pagination-info mt-16">
        <p class="muted">{{ strings.showingThreads(threads.length) }}</p>
      </div>
    </section>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ThreadCard from '@/components/ThreadCard.vue'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import RefreshIcon from '@icons/Refresh.vue'
import MessagePlusIcon from '@icons/MessagePlus.vue'
import type { Category, Thread } from '@/types'
import { ocs } from '@/axios'
import { t, n } from '@nextcloud/l10n'

export default defineComponent({
  name: 'CategoryView',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    ThreadCard,
    ArrowLeftIcon,
    RefreshIcon,
    MessagePlusIcon,
  },
  data() {
    return {
      loading: false,
      category: null as Category | null,
      threads: [] as Thread[],
      readMarkers: {} as Record<number, { lastReadPostId: number; readAt: number }>,
      error: null as string | null,
      limit: 50,
      offset: 0,

      strings: {
        back: t('forum', 'Back to Categories'),
        refresh: t('forum', 'Refresh'),
        newThread: t('forum', 'New Thread'),
        loading: t('forum', 'Loading…'),
        errorTitle: t('forum', 'Error loading category'),
        emptyTitle: t('forum', 'No threads yet'),
        emptyDesc: t('forum', 'Be the first to start a discussion in this category.'),
        retry: t('forum', 'Retry'),
        showingThreads: (count: number) =>
          n('forum', 'Showing %n thread', 'Showing %n threads', count),
      },
    }
  },
  computed: {
    categoryId(): number | null {
      return this.$route.params.id ? parseInt(this.$route.params.id as string) : null
    },
    categorySlug(): string | null {
      return (this.$route.params.slug as string) || null
    },
    sortedThreads(): Thread[] {
      // Sort pinned threads first, then by updatedAt descending
      return [...this.threads].sort((a, b) => {
        if (a.isPinned !== b.isPinned) {
          return a.isPinned ? -1 : 1
        }
        return b.updatedAt - a.updatedAt
      })
    },
  },
  created() {
    this.refresh()
  },
  methods: {
    async refresh() {
      try {
        this.loading = true
        this.error = null

        // Fetch category details
        await this.fetchCategory()

        // Fetch threads
        if (this.category) {
          await this.fetchThreads()
          // Fetch read markers after threads are loaded
          await this.fetchReadMarkers()
        }
      } catch (e) {
        console.error('Failed to refresh', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    async fetchCategory() {
      try {
        let resp
        if (this.categorySlug) {
          resp = await ocs.get<Category>(`/categories/slug/${this.categorySlug}`)
        } else if (this.categoryId) {
          resp = await ocs.get<Category>(`/categories/${this.categoryId}`)
        } else {
          throw new Error(t('forum', 'No category ID or slug provided'))
        }
        this.category = resp.data
      } catch (e) {
        console.error('Failed to fetch category', e)
        throw new Error(t('forum', 'Category not found'))
      }
    },

    async fetchThreads() {
      try {
        const resp = await ocs.get<Thread[]>(`/categories/${this.category!.id}/threads`, {
          params: {
            limit: this.limit,
            offset: this.offset,
          },
        })
        this.threads = resp.data || []
      } catch (e) {
        console.error('Failed to fetch threads', e)
        throw new Error(t('forum', 'Failed to load threads'))
      }
    },

    async fetchReadMarkers() {
      try {
        if (this.threads.length === 0) {
          return
        }

        const threadIds = this.threads.map((t) => t.id).join(',')
        const resp = await ocs.get<
          Record<number, { threadId: number; lastReadPostId: number; readAt: number }>
        >('/read-markers', {
          params: { threadIds },
        })
        this.readMarkers = resp.data || {}
      } catch (e) {
        // Silently fail - read markers are not critical
        console.debug('Failed to fetch read markers', e)
      }
    },

    isThreadUnread(thread: Thread): boolean {
      const marker = this.readMarkers[thread.id]
      if (!marker) {
        // No read marker means thread is unread
        return true
      }
      // Thread is unread if last post ID > last read post ID
      return thread.lastPostId ? thread.lastPostId > marker.lastReadPostId : false
    },

    navigateToThread(thread: Thread) {
      this.$router.push(`/t/${thread.slug}`)
    },

    createThread() {
      if (this.category) {
        this.$router.push(`/c/${this.category.slug || this.category.id}/new`)
      }
    },

    goBack(): void {
      // Always navigate to home, not browser history
      this.$router.push('/')
    },
  },
})
</script>

<style scoped lang="scss">
.category-view {
  .muted {
    color: var(--color-text-maxcontrast);
    opacity: 0.7;
  }

  .mt-8 {
    margin-top: 8px;
  }

  .mt-12 {
    margin-top: 12px;
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

  .toolbar {
    margin-top: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;

    .toolbar-left,
    .toolbar-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }
  }

  .category-header {
    padding: 20px;
    background: var(--color-background-hover);
    border-radius: 8px;
    border: 1px solid var(--color-border);
  }

  .category-name {
    margin: 0 0 8px 0;
    font-size: 1.75rem;
    font-weight: 600;
    color: var(--color-main-text);
  }

  .category-description {
    margin: 0;
    font-size: 1rem;
    color: var(--color-text-lighter);
    line-height: 1.5;
  }

  .threads-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .pagination-info {
    text-align: center;
    padding: 12px;
  }
}
</style>
