<template>
  <div class="thread-view">
    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <NcButton @click="goBack">
          <template #icon>
            <ArrowLeftIcon :size="20" />
          </template>
          {{ thread?.categoryName ? strings.backToCategory(thread.categoryName) : strings.back }}
        </NcButton>
      </div>

      <div class="toolbar-right">
        <NcButton @click="refresh" :disabled="loading" :aria-label="strings.refresh">
          <template #icon>
            <RefreshIcon :size="20" />
          </template>
        </NcButton>
        <NcButton @click="replyToThread" :disabled="loading || thread?.isLocked" variant="primary">
          <template #icon>
            <ReplyIcon :size="20" />
          </template>
          {{ strings.reply }}
        </NcButton>
      </div>
    </div>

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

    <!-- Thread Header -->
    <div v-else-if="thread" class="thread-header mt-16">
      <div class="thread-title-section">
        <h2 class="thread-title">
          <span v-if="thread.isPinned" class="badge badge-pinned" :title="strings.pinned">
            <PinIcon :size="20" />
          </span>
          <span v-if="thread.isLocked" class="badge badge-locked" :title="strings.locked">
            <LockIcon :size="20" />
          </span>
          {{ thread.title }}
        </h2>
        <div class="thread-meta">
          <span class="meta-item">
            <span class="meta-label">{{ strings.by }}</span>
            <span class="meta-value" :class="{ 'deleted-user': thread.authorIsDeleted }">
              {{ thread.authorDisplayName || thread.authorId }}
            </span>
          </span>
          <span class="meta-divider">·</span>
          <span class="meta-item">
            <NcDateTime v-if="thread.createdAt" :timestamp="thread.createdAt * 1000" />
          </span>
          <span class="meta-divider">·</span>
          <span class="meta-item">
            <span class="stat-icon">
              <EyeIcon :size="16" />
            </span>
            <span class="stat-label">{{ strings.views(thread.viewCount) }}</span>
          </span>
        </div>
      </div>
    </div>

    <!-- Posts list -->
    <section v-if="!loading && !error && posts.length > 0" class="mt-16">
      <div class="posts-list">
        <PostCard
          v-for="(post, index) in posts"
          :key="post.id"
          :ref="(el) => setPostCardRef(el, post.id)"
          :post="post"
          :is-first-post="index === 0"
          :is-unread="isPostUnread(post)"
          @reply="handleReply"
          @update="handleUpdate"
          @delete="handleDelete"
        />
      </div>

      <!-- Pagination info -->
      <div v-if="posts.length >= limit" class="pagination-info mt-16">
        <p class="muted">{{ strings.showingPosts(posts.length) }}</p>
      </div>
    </section>

    <!-- Empty posts state (thread exists but no posts) -->
    <NcEmptyContent
      v-else-if="!loading && !error && thread && posts.length === 0"
      :title="strings.emptyPostsTitle"
      :description="strings.emptyPostsDesc"
      class="mt-16"
    >
      <template #action>
        <NcButton @click="replyToThread" variant="primary">
          <template #icon>
            <ReplyIcon :size="20" />
          </template>
          {{ strings.reply }}
        </NcButton>
      </template>
    </NcEmptyContent>

    <!-- Reply form -->
    <PostReplyForm
      v-if="!loading && !error && thread && !thread.isLocked"
      ref="replyForm"
      @submit="handleSubmitReply"
      @cancel="handleCancelReply"
    />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import PostCard from '@/components/PostCard.vue'
import PostReplyForm from '@/components/PostReplyForm.vue'
import PinIcon from '@icons/Pin.vue'
import LockIcon from '@icons/Lock.vue'
import EyeIcon from '@icons/Eye.vue'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import RefreshIcon from '@icons/Refresh.vue'
import ReplyIcon from '@icons/Reply.vue'
import type { Post } from '@/types'
import { ocs } from '@/axios'
import { t, n } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { useCurrentThread } from '@/composables/useCurrentThread'

export default defineComponent({
  name: 'ThreadView',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcDateTime,
    PostCard,
    PostReplyForm,
    PinIcon,
    LockIcon,
    EyeIcon,
    ArrowLeftIcon,
    RefreshIcon,
    ReplyIcon,
  },
  setup() {
    const { currentThread: thread, fetchThread } = useCurrentThread()

    return {
      thread,
      fetchThread,
    }
  },
  data() {
    return {
      loading: false,
      posts: [] as Post[],
      lastReadPostId: null as number | null,
      error: null as string | null,
      limit: 50,
      offset: 0,
      postCardRefs: new Map<number, any>(),

      strings: {
        back: t('forum', 'Back'),
        backToCategory: (categoryName: string) => t('forum', 'Back to {category}', { category: categoryName }),
        refresh: t('forum', 'Refresh'),
        reply: t('forum', 'Reply'),
        loading: t('forum', 'Loading…'),
        errorTitle: t('forum', 'Error loading thread'),
        emptyPostsTitle: t('forum', 'No posts yet'),
        emptyPostsDesc: t('forum', 'Be the first to post in this thread.'),
        retry: t('forum', 'Retry'),
        by: t('forum', 'by'),
        views: (count: number) => n('forum', '%n view', '%n views', count),
        pinned: t('forum', 'Pinned thread'),
        locked: t('forum', 'Locked thread'),
        showingPosts: (count: number) => n('forum', 'Showing %n post', 'Showing %n posts', count),
      },
    }
  },
  computed: {
    threadId(): number | null {
      return this.$route.params.id ? parseInt(this.$route.params.id as string) : null
    },
    threadSlug(): string | null {
      return (this.$route.params.slug as string) || null
    },
  },
  created() {
    this.refresh()
  },
  methods: {
    async refresh(): Promise<void> {
      try {
        this.loading = true
        this.error = null

        // Fetch thread details using the composable
        // Increment view count on initial load, but not on manual refresh
        const incrementView = !this.thread
        let threadData
        if (this.threadSlug) {
          threadData = await this.fetchThread(this.threadSlug, true, incrementView)
        } else if (this.threadId) {
          threadData = await this.fetchThread(this.threadId, false, incrementView)
        } else {
          throw new Error(t('forum', 'No thread ID or slug provided'))
        }

        // Fetch posts
        if (threadData) {
          await this.fetchPosts()
        }
      } catch (e) {
        console.error('Failed to refresh', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    async fetchPosts(): Promise<void> {
      try {
        // Fetch existing read marker before loading posts
        await this.fetchReadMarker()

        const resp = await ocs.get<Post[]>(`/threads/${this.thread!.id}/posts`, {
          params: {
            limit: this.limit,
            offset: this.offset,
          },
        })
        this.posts = resp.data || []

        // Mark thread as read up to the last post in the current view
        if (this.posts.length > 0) {
          await this.markAsRead()
        }
      } catch (e) {
        console.error('Failed to fetch posts', e)
        throw new Error(t('forum', 'Failed to load posts'))
      }
    },

    async fetchReadMarker(): Promise<void> {
      try {
        if (!this.thread) {
          return
        }

        const resp = await ocs.get<{ threadId: number; lastReadPostId: number; readAt: number }>(
          `/threads/${this.thread.id}/read-marker`,
        )
        this.lastReadPostId = resp.data?.lastReadPostId || null
      } catch (e) {
        // Not found or error - treat as no read marker
        this.lastReadPostId = null
        console.debug('No read marker found', e)
      }
    },

    isPostUnread(post: Post): boolean {
      if (this.lastReadPostId === null) {
        // No read marker means all posts are unread
        return true
      }
      // Post is unread if its ID is greater than last read post ID
      return post.id > this.lastReadPostId
    },

    async markAsRead(): Promise<void> {
      try {
        // Get the last post ID from the current view
        const lastPost = this.posts[this.posts.length - 1]
        if (!lastPost || !this.thread) {
          return
        }

        // Send request to mark thread as read
        await ocs.post('/read-markers', {
          threadId: this.thread.id,
          lastReadPostId: lastPost.id,
        })
      } catch (e) {
        // Silently fail - marking as read is not critical
        console.debug('Failed to mark thread as read', e)
      }
    },

    handleReply(post: Post): void {
      console.log('Reply to post:', post.id)
      // TODO: Implement reply functionality
      // Could open a reply form or navigate to a reply page
    },

    setPostCardRef(el: any, postId: number) {
      if (el) {
        this.postCardRefs.set(postId, el)
      } else {
        this.postCardRefs.delete(postId)
      }
    },

    async handleUpdate(data: { post: Post; content: string }): Promise<void> {
      const postCard = this.postCardRefs.get(data.post.id)

      try {
        const response = await ocs.put<Post>(`/posts/${data.post.id}`, {
          content: data.content,
        })

        if (response.data) {
          // Update the post in the local posts array
          const index = this.posts.findIndex((p) => p.id === data.post.id)
          if (index !== -1) {
            // Preserve reactions when updating
            this.posts[index] = { ...response.data, reactions: this.posts[index]?.reactions || [] }
          }

          // Exit edit mode
          if (postCard && typeof postCard.finishEdit === 'function') {
            postCard.finishEdit()
          }

          showSuccess(t('forum', 'Post updated successfully'))
        }
      } catch (e) {
        console.error('Failed to update post', e)
        showError(t('forum', 'Failed to update post'))

        // Reset submitting state on error
        if (postCard && typeof postCard.setEditSubmitting === 'function') {
          postCard.setEditSubmitting(false)
        }
      }
    },

    async handleDelete(post: Post): Promise<void> {
      try {
        // If this is the first post, we're deleting the entire thread
        const isFirstPost = this.posts.length > 0 && this.posts[0]?.id === post.id

        if (isFirstPost) {
          // Delete thread
          if (!this.thread) {
            return
          }

          const response = await ocs.delete<{ success: boolean; categorySlug: string }>(
            `/threads/${this.thread.id}`,
          )

          if (response.data?.success && response.data.categorySlug) {
            showSuccess(t('forum', 'Thread deleted successfully'))
            // Navigate to the category
            this.$router.push(`/c/${response.data.categorySlug}`)
          }
        } else {
          // Delete post optimistically
          await ocs.delete(`/posts/${post.id}`)

          // Remove the post from the local array without refreshing
          const index = this.posts.findIndex((p) => p.id === post.id)
          if (index !== -1) {
            this.posts.splice(index, 1)
          }

          showSuccess(t('forum', 'Post deleted successfully'))
        }
      } catch (e) {
        console.error('Failed to delete post', e)
        showError(t('forum', 'Failed to delete post'))
      }
    },

    replyToThread(): void {
      const replyForm = this.$refs.replyForm as any
      if (!replyForm) {
        return
      }

      // Scroll to the reply form
      const element = replyForm.$el as HTMLElement
      if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' })
      }

      // Focus the textarea after a small delay to ensure scroll completes
      setTimeout(() => {
        if (replyForm && typeof replyForm.focus === 'function') {
          replyForm.focus()
        }
      }, 300)
    },

    async handleSubmitReply(content: string): Promise<void> {
      if (!this.thread) {
        return
      }

      const replyForm = this.$refs.replyForm as any

      try {
        const response = await ocs.post<Post>('/posts', {
          threadId: this.thread.id,
          content,
        })

        // Append the new post to the existing posts array
        if (response.data) {
          // Add empty reactions array to the new post
          const newPost = { ...response.data, reactions: [] }
          this.posts.push(newPost)

          // Clear the form only on success
          if (replyForm && typeof replyForm.clear === 'function') {
            replyForm.clear()
          }
        }
      } catch (e) {
        console.error('Failed to submit reply', e)
        // Reset submitting state on error
        if (replyForm && typeof replyForm.setSubmitting === 'function') {
          replyForm.setSubmitting(false)
        }
        // TODO: Show error notification
      }
    },

    handleCancelReply(): void {
      // Optional: Could implement special behavior on cancel
      console.log('Reply cancelled')
    },

    goBack(): void {
      // Always navigate to the category, not browser history
      if (this.thread?.categorySlug) {
        this.$router.push(`/c/${this.thread.categorySlug}`)
      } else {
        // Fallback to home if no category info
        this.$router.push('/')
      }
    },
  },
})
</script>

<style scoped lang="scss">
.thread-view {
  margin-bottom: 3rem;

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

  .thread-header {
    padding: 20px;
    background: var(--color-background-hover);
    border-radius: 8px;
    border: 1px solid var(--color-border);
  }

  .thread-title-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .thread-title {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 600;
    color: var(--color-main-text);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .badge {
    font-size: 1.2rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;

    &.badge-pinned {
      opacity: 0.9;
    }

    &.badge-locked {
      opacity: 0.8;
    }
  }

  .thread-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: var(--color-text-maxcontrast);
    flex-wrap: wrap;
  }

  .meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .meta-label {
    font-style: italic;
  }

  .meta-value {
    font-weight: 500;
    color: var(--color-text-lighter);

    &.deleted-user {
      font-style: italic;
      opacity: 0.7;
    }
  }

  .meta-divider {
    opacity: 0.5;
  }

  .stat-icon {
    font-size: 1rem;
  }

  .stat-value {
    font-weight: 600;
  }

  .stat-label {
    font-size: 0.85rem;
  }

  .posts-list {
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
