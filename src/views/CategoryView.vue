<template>
  <PageWrapper>
    <template #toolbar>
      <AppToolbar>
        <template #left>
          <NcButton @click="goBack">
            <template #icon>
              <ArrowLeftIcon :size="20" />
            </template>
            {{ strings.back }}
          </NcButton>
        </template>

        <template #right>
          <NcButton
            @click="refresh"
            :disabled="loading"
            :aria-label="strings.refresh"
            :title="strings.refresh"
          >
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
        </template>
      </AppToolbar>
    </template>

    <div class="category-view">
      <!-- Category Header -->
      <PageHeader
        v-if="category && !loading"
        :title="category.name"
        :subtitle="category.description || undefined"
        class="mt-16"
      />

      <!-- Loading state -->
      <div class="center mt-16" v-if="loading">
        <NcLoadingIcon :size="32" />
        <span class="muted ml-8">{{ strings.loading }}</span>
      </div>

      <!-- Error state: Category not found -->
      <CategoryNotFound v-else-if="error && error.includes('not found')" />

      <!-- Error state: Other errors -->
      <NcEmptyContent
        v-else-if="error"
        :title="strings.errorTitle"
        :description="error"
        class="mt-16"
      >
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
      <NcEmptyContent
        v-else-if="threads.length === 0"
        :title="strings.emptyTitle"
        :description="strings.emptyDesc"
        class="mt-16"
      >
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
      <section v-else class="mt-16 threads-section">
        <!-- Pagination at top -->
        <Pagination
          v-if="totalPages > 1"
          :current-page="currentPage"
          :max-pages="totalPages"
          class="pagination-top"
          @update:current-page="handlePageChange"
        />

        <!-- Loading state for threads -->
        <div v-if="loadingThreads" class="threads-loading mt-16">
          <NcLoadingIcon :size="32" />
          <span class="muted ml-8">{{ strings.loading }}</span>
        </div>

        <div v-else class="threads-list mt-16">
          <ThreadCard
            v-for="thread in sortedThreads"
            :key="thread.id"
            :thread="thread"
            :is-unread="isThreadUnread(thread)"
            @click="navigateToThread(thread)"
          />
        </div>

        <!-- Pagination at bottom -->
        <Pagination
          v-if="totalPages > 1"
          :current-page="currentPage"
          :max-pages="totalPages"
          class="pagination-bottom mt-16"
          @update:current-page="handlePageChange"
        />
      </section>
    </div>
  </PageWrapper>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AppToolbar from '@/components/AppToolbar'
import PageWrapper from '@/components/PageWrapper'
import PageHeader from '@/components/PageHeader'
import ThreadCard from '@/components/ThreadCard'
import Pagination from '@/components/Pagination'
import CategoryNotFound from '@/views/CategoryNotFound.vue'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import RefreshIcon from '@icons/Refresh.vue'
import MessagePlusIcon from '@icons/MessagePlus.vue'
import type { Category, Thread } from '@/types'
import { ocs } from '@/axios'
import { t, n } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { useCurrentUser } from '@/composables/useCurrentUser'
import { useCategories } from '@/composables/useCategories'

export default defineComponent({
  name: 'CategoryView',
  setup() {
    const { userId } = useCurrentUser()
    const { markCategoryAsRead } = useCategories()
    return { userId, markCategoryAsReadLocal: markCategoryAsRead }
  },
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    AppToolbar,
    PageWrapper,
    PageHeader,
    ThreadCard,
    Pagination,
    CategoryNotFound,
    ArrowLeftIcon,
    RefreshIcon,
    MessagePlusIcon,
  },
  data() {
    return {
      loading: false,
      loadingThreads: false,
      category: null as Category | null,
      threads: [] as Thread[],
      readMarkers: {} as Record<number, { lastReadPostId: number; readAt: number }>,
      error: null as string | null,
      currentPage: 1,
      totalPages: 1,
      perPage: 20,

      strings: {
        back: t('forum', 'Back to categories'),
        refresh: t('forum', 'Refresh'),
        newThread: t('forum', 'New thread'),
        loading: t('forum', 'Loading …'),
        errorTitle: t('forum', 'Error loading category'),
        emptyTitle: t('forum', 'No threads yet'),
        emptyDesc: t('forum', 'Be the first to start a discussion in this category.'),
        retry: t('forum', 'Retry'),
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
          // Mark category as read for authenticated users
          this.markCategoryAsRead()
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

    async fetchThreads(page: number = 1) {
      try {
        interface PaginatedResponse {
          threads: Thread[]
          pagination: {
            page: number
            perPage: number
            total: number
            totalPages: number
          }
        }

        const resp = await ocs.get<PaginatedResponse>(
          `/categories/${this.category!.id}/threads/paginated`,
          {
            params: {
              page,
              perPage: this.perPage,
            },
          },
        )

        const data = resp.data
        if (data) {
          this.threads = data.threads || []
          this.currentPage = data.pagination.page
          this.totalPages = data.pagination.totalPages
        }
      } catch (e) {
        console.error('Failed to fetch threads', e)
        throw new Error(t('forum', 'Failed to load threads'))
      }
    },

    async handlePageChange(newPage: number) {
      if (newPage === this.currentPage) return

      try {
        this.loadingThreads = true
        await this.fetchThreads(newPage)
        // Fetch read markers for the new page's threads
        await this.fetchReadMarkers()

        // Scroll to top of threads list
        await this.$nextTick()
        const threadsList = this.$el.querySelector('.threads-list')
        if (threadsList) {
          threadsList.scrollIntoView({ behavior: 'smooth', block: 'start' })
        }
      } catch (e) {
        console.error('Failed to load page', e)
      } finally {
        this.loadingThreads = false
      }
    },

    async markCategoryAsRead() {
      if (this.userId === null || !this.category) {
        return
      }
      // Update shared state immediately so back navigation shows as read
      this.markCategoryAsReadLocal(this.category.id)
      try {
        await ocs.post('/read-markers', { categoryId: this.category.id })
      } catch (e) {
        console.debug('Failed to mark category as read', e)
      }
    },

    async fetchReadMarkers() {
      try {
        // Guests don't have read markers
        if (this.userId === null) {
          return
        }

        if (this.threads.length === 0) {
          return
        }

        const threadIds = this.threads.map((t) => t.id).join(',')
        const resp = await ocs.get<
          Record<number, { entityId: number; lastReadPostId: number; readAt: number }>
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
      // Guests see everything as read
      if (this.userId === null) {
        return false
      }

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
      // Redirect guests to login
      if (this.userId === null) {
        const returnUrl = generateUrl(
          `/apps/forum/c/${this.category?.slug || this.category?.id}/new`,
        )
        window.location.href = generateUrl(`/login?redirect_url=${encodeURIComponent(returnUrl)}`)
        return
      }

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


  .threads-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .threads-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px;
  }

  .pagination-top,
  .pagination-bottom {
    padding: 8px 0;
  }
}
</style>
