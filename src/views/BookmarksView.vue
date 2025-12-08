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
        </template>
      </AppToolbar>
    </template>

    <div class="bookmarks-view">
      <!-- Page Header -->
      <PageHeader
        v-if="!loading"
        :title="strings.title"
        :subtitle="strings.subtitle"
        class="mt-16"
      />

      <!-- Loading state -->
      <div class="center mt-16" v-if="loading">
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
        <template #icon>
          <BookmarkIcon :size="64" />
        </template>
      </NcEmptyContent>

      <!-- Bookmarked threads list -->
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
            v-for="thread in threads"
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
import AppToolbar from '@/components/AppToolbar.vue'
import PageWrapper from '@/components/PageWrapper.vue'
import PageHeader from '@/components/PageHeader.vue'
import ThreadCard from '@/components/ThreadCard.vue'
import Pagination from '@/components/Pagination.vue'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import RefreshIcon from '@icons/Refresh.vue'
import BookmarkIcon from '@icons/Bookmark.vue'
import type { Thread } from '@/types'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import { useCurrentUser } from '@/composables/useCurrentUser'

export default defineComponent({
  name: 'BookmarksView',
  setup() {
    const { userId } = useCurrentUser()
    return { userId }
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
    ArrowLeftIcon,
    RefreshIcon,
    BookmarkIcon,
  },
  data() {
    return {
      loading: false,
      loadingThreads: false,
      threads: [] as Thread[],
      readMarkers: {} as Record<number, { lastReadPostId: number; readAt: number }>,
      error: null as string | null,
      currentPage: 1,
      totalPages: 1,
      perPage: 20,

      strings: {
        back: t('forum', 'Back to home'),
        refresh: t('forum', 'Refresh'),
        loading: t('forum', 'Loading …'),
        title: t('forum', 'Bookmarks'),
        subtitle: t('forum', 'Your bookmarked threads'),
        errorTitle: t('forum', 'Error loading bookmarks'),
        emptyTitle: t('forum', 'No bookmarks yet'),
        emptyDesc: t('forum', 'Bookmark threads to quickly find them later.'),
        retry: t('forum', 'Retry'),
      },
    }
  },
  created() {
    this.refresh()
  },
  methods: {
    async refresh() {
      try {
        this.loading = true
        this.error = null
        await this.fetchBookmarks()
      } catch (e) {
        console.error('Failed to refresh', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    async fetchBookmarks(page: number = 1) {
      try {
        interface BookmarksResponse {
          threads: Thread[]
          pagination: {
            page: number
            perPage: number
            total: number
            totalPages: number
          }
          readMarkers: Record<number, { threadId: number; lastReadPostId: number; readAt: number }>
        }

        const resp = await ocs.get<BookmarksResponse>('/bookmarks', {
          params: {
            page,
            perPage: this.perPage,
          },
        })

        const data = resp.data
        if (data) {
          this.threads = data.threads || []
          this.currentPage = data.pagination.page
          this.totalPages = data.pagination.totalPages
          this.readMarkers = data.readMarkers || {}
        }
      } catch (e) {
        console.error('Failed to fetch bookmarks', e)
        throw new Error(t('forum', 'Failed to load bookmarks'))
      }
    },

    async handlePageChange(newPage: number) {
      if (newPage === this.currentPage) return

      try {
        this.loadingThreads = true
        await this.fetchBookmarks(newPage)

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

    goBack(): void {
      this.$router.push('/')
    },
  },
})
</script>

<style scoped lang="scss">
.bookmarks-view {
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
