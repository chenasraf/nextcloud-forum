<template>
  <PageWrapper :full-width="true">
    <template #toolbar>
      <AppToolbar>
        <template #left>
          <NcButton @click="goBack">
            <template #icon>
              <ArrowLeftIcon :size="20" />
            </template>
            {{ thread?.categoryName ? strings.backToCategory(thread.categoryName) : strings.back }}
          </NcButton>
        </template>

        <template #right>
          <!-- Subscription toggle switch (authenticated users only) -->
          <NcCheckboxRadioSwitch
            v-if="!loading && thread && userId !== null"
            v-model="thread.isSubscribed"
            @update:model-value="handleToggleSubscription"
            type="switch"
          >
            <span class="icon-label">
              <BellIcon :size="20" />
              {{ thread.isSubscribed ? strings.subscribed : strings.subscribe }}
            </span>
          </NcCheckboxRadioSwitch>

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

          <!-- Moderation buttons (only visible to moderators) -->
          <template v-if="canModerate && !loading">
            <NcButton
              @click="handleToggleLock"
              :aria-label="thread?.isLocked ? strings.unlockThread : strings.lockThread"
              :title="thread?.isLocked ? strings.unlockThread : strings.lockThread"
            >
              <template #icon>
                <LockOpenIcon v-if="thread?.isLocked" :size="20" />
                <LockIcon v-else :size="20" />
              </template>
            </NcButton>

            <NcButton
              @click="handleTogglePin"
              :aria-label="thread?.isPinned ? strings.unpinThread : strings.pinThread"
              :title="thread?.isPinned ? strings.unpinThread : strings.pinThread"
            >
              <template #icon>
                <PinOffIcon v-if="thread?.isPinned" :size="20" />
                <PinIcon v-else :size="20" />
              </template>
            </NcButton>

            <NcButton
              @click="showMoveDialog = true"
              :aria-label="strings.moveThread"
              :title="strings.moveThread"
            >
              <template #icon>
                <FolderMoveIcon :size="20" />
              </template>
            </NcButton>
          </template>

          <NcButton
            @click="replyToThread"
            :disabled="loading || (thread?.isLocked && !canModerate)"
            variant="primary"
          >
            <template #icon>
              <ReplyIcon :size="20" />
            </template>
            {{ strings.reply }}
          </NcButton>
        </template>
      </AppToolbar>
    </template>

    <div class="thread-view">
      <!-- Loading state -->
      <div class="center mt-16" v-if="loading">
        <NcLoadingIcon :size="32" />
        <span class="muted ml-8">{{ strings.loading }}</span>
      </div>

      <!-- Error state: Thread not found -->
      <ThreadNotFound v-else-if="error && (error.includes('not found') || error.includes('404'))" />

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

      <!-- Thread Header -->
      <div v-else-if="thread" class="thread-header mt-16">
        <div class="thread-title-section">
          <div class="thread-title-row">
            <h2 v-if="!isEditingTitle" class="thread-title">
              <span v-if="thread.isPinned" class="badge badge-pinned" :title="strings.pinned">
                <PinIcon :size="20" />
              </span>
              <span v-if="thread.isLocked" class="badge badge-locked" :title="strings.locked">
                <LockIcon :size="20" />
              </span>
              {{ thread.title }}
            </h2>
            <NcTextField
              v-else
              v-model="editedTitle"
              class="thread-title-input"
              @keydown.enter="handleSaveTitle"
              @keydown.esc="handleCancelEditTitle"
              ref="titleInput"
              :disabled="isSavingTitle"
            />
            <NcButton
              v-if="!isEditingTitle && canEditTitle"
              @click="handleStartEditTitle"
              type="tertiary"
              :aria-label="strings.editTitle"
              :title="strings.editTitle"
              class="edit-title-button"
            >
              <template #icon>
                <PencilIcon :size="20" />
              </template>
            </NcButton>
            <NcButton
              v-if="isEditingTitle"
              @click="handleSaveTitle"
              :disabled="isSavingTitle || !editedTitle.trim()"
              type="primary"
              :aria-label="strings.saveTitle"
              :title="strings.saveTitle"
              class="save-title-button"
            >
              <template #icon>
                <CheckIcon :size="20" />
              </template>
            </NcButton>
          </div>
          <div class="thread-meta">
            <span class="meta-item">
              <span class="meta-label">{{ strings.by }}</span>
              <span class="meta-value" :class="{ 'deleted-user': thread.author?.isDeleted }">
                {{ thread.author?.displayName || thread.authorId }}
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

      <!-- Locked thread message (only shown to non-moderators) -->
      <NcNoteCard
        v-if="!loading && !error && thread && thread.isLocked && !canModerate && userId !== null"
        type="warning"
        class="mt-16"
      >
        <p>
          <LockIcon :size="20" class="inline-icon" />
          {{ strings.lockedMessage }}
        </p>
      </NcNoteCard>

      <!-- Guest user message -->
      <NcNoteCard v-if="!loading && !error && thread && userId === null" type="info" class="mt-16">
        <p>{{ strings.guestMessage }}</p>
        <template #action>
          <NcButton @click="replyToThread" variant="primary">
            {{ strings.signInToReply }}
          </NcButton>
        </template>
      </NcNoteCard>

      <!-- Reply form (authenticated users only, moderators can reply even when locked) -->
      <PostReplyForm
        v-if="!loading && !error && thread && userId !== null && (!thread.isLocked || canModerate)"
        ref="replyForm"
        @submit="handleSubmitReply"
        @cancel="handleCancelReply"
      />
    </div>

    <!-- Move Category Dialog -->
    <MoveCategoryDialog
      v-if="thread"
      ref="moveDialog"
      :open="showMoveDialog"
      :current-category-id="thread.categoryId"
      @update:open="showMoveDialog = $event"
      @move="handleMoveThread"
    />
  </PageWrapper>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AppToolbar from '@/components/AppToolbar.vue'
import PageWrapper from '@/components/PageWrapper.vue'
import PostCard from '@/components/PostCard.vue'
import PostReplyForm from '@/components/PostReplyForm.vue'
import ThreadNotFound from '@/views/ThreadNotFound.vue'
import MoveCategoryDialog from '@/components/MoveCategoryDialog.vue'
import PinIcon from '@icons/Pin.vue'
import PinOffIcon from '@icons/PinOff.vue'
import LockIcon from '@icons/Lock.vue'
import LockOpenIcon from '@icons/LockOpen.vue'
import EyeIcon from '@icons/Eye.vue'
import BellIcon from '@icons/Bell.vue'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import RefreshIcon from '@icons/Refresh.vue'
import ReplyIcon from '@icons/Reply.vue'
import PencilIcon from '@icons/Pencil.vue'
import CheckIcon from '@icons/Check.vue'
import FolderMoveIcon from '@icons/FolderMove.vue'
import type { Post } from '@/types'
import { ocs } from '@/axios'
import { t, n } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { useCurrentThread } from '@/composables/useCurrentThread'
import { usePermissions } from '@/composables/usePermissions'
import { useCurrentUser } from '@/composables/useCurrentUser'

export default defineComponent({
  name: 'ThreadView',
  components: {
    NcButton,
    NcCheckboxRadioSwitch,
    NcEmptyContent,
    NcLoadingIcon,
    NcDateTime,
    NcNoteCard,
    NcTextField,
    AppToolbar,
    PageWrapper,
    PostCard,
    PostReplyForm,
    ThreadNotFound,
    PinIcon,
    PinOffIcon,
    LockIcon,
    LockOpenIcon,
    EyeIcon,
    BellIcon,
    ArrowLeftIcon,
    RefreshIcon,
    ReplyIcon,
    PencilIcon,
    CheckIcon,
    FolderMoveIcon,
    MoveCategoryDialog,
  },
  setup() {
    const { currentThread: thread, fetchThread } = useCurrentThread()
    const { checkCategoryPermission } = usePermissions()
    const { userId } = useCurrentUser()

    return {
      thread,
      fetchThread,
      checkCategoryPermission,
      userId,
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
      canModerate: false,
      isEditingTitle: false,
      editedTitle: '',
      isSavingTitle: false,
      showMoveDialog: false,

      strings: {
        back: t('forum', 'Back'),
        backToCategory: (categoryName: string) =>
          t('forum', 'Back to {category}', { category: categoryName }),
        refresh: t('forum', 'Refresh'),
        reply: t('forum', 'Reply'),
        loading: t('forum', 'Loading …'),
        errorTitle: t('forum', 'Error loading thread'),
        emptyPostsTitle: t('forum', 'No posts yet'),
        emptyPostsDesc: t('forum', 'Be the first to post in this thread.'),
        retry: t('forum', 'Retry'),
        by: t('forum', 'by'),
        views: (count: number) => n('forum', '%n view', '%n views', count),
        pinned: t('forum', 'Pinned thread'),
        locked: t('forum', 'Locked thread'),
        lockedMessage: t('forum', 'This thread is locked. Only moderators can post replies.'),
        guestMessage: t('forum', 'You must be signed in to reply to this thread.'),
        signInToReply: t('forum', 'Sign in to reply'),
        showingPosts: (count: number) => n('forum', 'Showing %n post', 'Showing %n posts', count),
        lockThread: t('forum', 'Lock thread'),
        unlockThread: t('forum', 'Unlock thread'),
        pinThread: t('forum', 'Pin thread'),
        unpinThread: t('forum', 'Unpin thread'),
        threadLocked: t('forum', 'Thread locked'),
        threadUnlocked: t('forum', 'Thread unlocked'),
        threadPinned: t('forum', 'Thread pinned'),
        threadUnpinned: t('forum', 'Thread unpinned'),
        subscribe: t('forum', 'Subscribe'),
        subscribed: t('forum', 'Subscribed'),
        threadSubscribed: t('forum', 'Subscribed to thread'),
        threadUnsubscribed: t('forum', 'Unsubscribed from thread'),
        editTitle: t('forum', 'Edit title'),
        saveTitle: t('forum', 'Save title'),
        titleUpdated: t('forum', 'Thread title updated'),
        moveThread: t('forum', 'Move thread'),
        threadMoved: t('forum', 'Thread moved successfully'),
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
    canEditTitle(): boolean {
      // Allow if user is the author, or has moderation permissions
      return this.thread?.authorId === this.userId || this.canModerate
    },
  },
  watch: {
    '$route.hash'(newHash: string) {
      // When hash changes within the same thread, scroll to the post
      if (newHash && newHash.startsWith('#post-') && this.posts.length > 0) {
        this.$nextTick(() => {
          this.scrollToPostFromHash()
        })
      }
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

        // Check if thread was found
        if (!threadData) {
          throw new Error(t('forum', 'Thread not found'))
        }

        // Fetch posts
        await this.fetchPosts()
        // Check moderation permission
        await this.checkModerationPermission()
      } catch (e) {
        console.error('Failed to refresh', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    async checkModerationPermission(): Promise<void> {
      if (this.thread?.categoryId) {
        this.canModerate = await this.checkCategoryPermission(this.thread.categoryId, 'canModerate')
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

        // Scroll to post if hash is present in URL
        await this.$nextTick()
        this.scrollToPostFromHash()
      } catch (e) {
        console.error('Failed to fetch posts', e)
        throw new Error(t('forum', 'Failed to load posts'))
      }
    },

    async fetchReadMarker(): Promise<void> {
      try {
        // Guests don't have read markers
        if (this.userId === null) {
          return
        }

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
      // Guests see everything as read
      if (this.userId === null) {
        return false
      }

      if (this.lastReadPostId === null) {
        // No read marker means all posts are unread
        return true
      }
      // Post is unread if its ID is greater than last read post ID
      return post.id > this.lastReadPostId
    },

    async markAsRead(): Promise<void> {
      try {
        // Guests can't mark as read
        if (this.userId === null) {
          return
        }

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
      const replyForm = this.$refs.replyForm as any
      if (!replyForm) {
        return
      }

      // Set the quoted content in the reply form
      if (replyForm && typeof replyForm.setQuotedContent === 'function') {
        replyForm.setQuotedContent(post.contentRaw)
      }

      // Scroll to the reply form with smooth behavior
      const element = replyForm.$el as HTMLElement
      if (element) {
        element.scrollIntoView({
          behavior: 'smooth',
          block: 'nearest',
          inline: 'nearest',
        })
      }

      // Wait for scroll animation to complete before focusing
      setTimeout(() => {
        if (replyForm && typeof replyForm.focus === 'function') {
          replyForm.focus()
        }
      }, 500)
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

          showSuccess(t('forum', 'Post updated'))
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
            showSuccess(t('forum', 'Thread deleted'))
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

          showSuccess(t('forum', 'Post deleted'))
        }
      } catch (e) {
        console.error('Failed to delete post', e)
        showError(t('forum', 'Failed to delete post'))
      }
    },

    replyToThread(): void {
      // Redirect guests to login
      if (this.userId === null) {
        const returnUrl = generateUrl(`/apps/forum/t/${this.thread?.slug}`)
        window.location.href = generateUrl(`/login?redirect_url=${encodeURIComponent(returnUrl)}`)
        return
      }

      const replyForm = this.$refs.replyForm as any
      if (!replyForm) {
        return
      }

      // Scroll to the reply form with smooth behavior
      const element = replyForm.$el as HTMLElement
      if (element) {
        element.scrollIntoView({
          behavior: 'smooth',
          block: 'nearest',
          inline: 'nearest',
        })
      }

      // Wait for scroll animation to complete before focusing
      setTimeout(() => {
        if (replyForm && typeof replyForm.focus === 'function') {
          replyForm.focus()
        }
      }, 500)
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

    async handleToggleLock(): Promise<void> {
      if (!this.thread) return

      const newLockState = !this.thread.isLocked

      try {
        const response = await ocs.put(`/threads/${this.thread.id}/lock`, { locked: newLockState })
        if (response.data) {
          // Update local thread state
          this.thread.isLocked = newLockState
          showSuccess(newLockState ? this.strings.threadLocked : this.strings.threadUnlocked)
        }
      } catch (e) {
        console.error('Failed to toggle thread lock', e)
        showError(t('forum', 'Failed to update thread lock status'))
      }
    },

    async handleTogglePin(): Promise<void> {
      if (!this.thread) return

      const newPinState = !this.thread.isPinned

      try {
        const response = await ocs.put(`/threads/${this.thread.id}/pin`, { pinned: newPinState })
        if (response.data) {
          // Update local thread state
          this.thread.isPinned = newPinState
          showSuccess(newPinState ? this.strings.threadPinned : this.strings.threadUnpinned)
        }
      } catch (e) {
        console.error('Failed to toggle thread pin', e)
        showError(t('forum', 'Failed to update thread pin status'))
      }
    },

    async handleToggleSubscription(newValue: boolean): Promise<void> {
      if (!this.thread) return

      try {
        if (newValue) {
          // Subscribe to thread
          await ocs.post(`/threads/${this.thread.id}/subscribe`)
          this.thread.isSubscribed = true
          showSuccess(this.strings.threadSubscribed)
        } else {
          // Unsubscribe from thread
          await ocs.delete(`/threads/${this.thread.id}/subscribe`)
          this.thread.isSubscribed = false
          showSuccess(this.strings.threadUnsubscribed)
        }
      } catch (e) {
        console.error('Failed to toggle thread subscription', e)
        showError(t('forum', 'Failed to update subscription'))
        // Revert the state on error
        this.thread.isSubscribed = !newValue
      }
    },

    scrollToPostFromHash(): void {
      // Check if there's a hash in the URL like #post-123
      const hash = window.location.hash || this.$route.hash

      if (hash && hash.startsWith('#post-')) {
        const postId = parseInt(hash.replace('#post-', ''))

        if (!isNaN(postId)) {
          // Try immediately first
          this.scrollToPost(postId)

          // If that didn't work (refs not ready), try again after a short delay
          setTimeout(() => {
            this.scrollToPost(postId)
          }, 100)

          // Final attempt after a longer delay
          setTimeout(() => {
            this.scrollToPost(postId)
          }, 500)
        }
      }
    },

    scrollToPost(postId: number): void {
      // Get the PostCard component reference
      const postCardRef = this.postCardRefs.get(postId)

      if (postCardRef && postCardRef.$el) {
        const element = postCardRef.$el as HTMLElement
        const offset = 80 // Offset for toolbar and some breathing room

        // Use requestAnimationFrame to ensure scroll happens after any router scroll operations
        requestAnimationFrame(() => {
          // Find the scrolling container - Nextcloud uses #app-content or #forum-main
          const scrollContainer =
            document.querySelector('#app-content') ||
            document.querySelector('#forum-main') ||
            document.documentElement

          const elementTop = element.getBoundingClientRect().top
          const scrollTop = scrollContainer.scrollTop || 0
          const containerTop = scrollContainer.getBoundingClientRect().top
          const targetPosition = elementTop - containerTop + scrollTop - offset

          scrollContainer.scrollTo({
            top: targetPosition,
            behavior: 'smooth',
          })

          // Add highlight effect
          element.classList.add('highlight-post')
          setTimeout(() => {
            element.classList.remove('highlight-post')
          }, 2000)
        })
      }
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

    handleStartEditTitle(): void {
      if (!this.thread) return

      this.editedTitle = this.thread.title
      this.isEditingTitle = true

      // Focus the input after it's rendered
      this.$nextTick(() => {
        const textFieldComponent = this.$refs.titleInput as any
        if (textFieldComponent && textFieldComponent.$el) {
          const input = textFieldComponent.$el.querySelector('input')
          if (input) {
            input.focus()
            input.select()
          }
        }
      })
    },

    handleCancelEditTitle(): void {
      this.isEditingTitle = false
      this.editedTitle = ''
    },

    async handleSaveTitle(): Promise<void> {
      if (!this.thread || !this.editedTitle.trim() || this.isSavingTitle) return

      // Don't save if title hasn't changed
      if (this.editedTitle.trim() === this.thread.title) {
        this.handleCancelEditTitle()
        return
      }

      this.isSavingTitle = true

      try {
        const response = await ocs.put(`/threads/${this.thread.id}/title`, {
          title: this.editedTitle.trim(),
        })

        if (response.data) {
          // Update local thread state
          this.thread.title = this.editedTitle.trim()
          showSuccess(this.strings.titleUpdated)
          this.isEditingTitle = false
          this.editedTitle = ''
        }
      } catch (e) {
        console.error('Failed to update thread title', e)
        showError(t('forum', 'Failed to update thread title'))
      } finally {
        this.isSavingTitle = false
      }
    },
  },
  async handleMoveThread(categoryId: number): Promise<void> {
    if (!this.thread) return

    try {
      const response = await ocs.put(`/threads/${this.thread.id}/move`, {
        categoryId,
      })

      if (response.data) {
        showSuccess(this.strings.threadMoved)
        this.showMoveDialog = false

        // Refresh the thread data to update category information and back link
        await this.refresh()

        // Reset the move dialog
        const moveDialog = this.$refs.moveDialog as any
        if (moveDialog && typeof moveDialog.reset === 'function') {
          moveDialog.reset()
        }
      }
    } catch (e) {
      console.error('Failed to move thread', e)
      showError(t('forum', 'Failed to move thread'))

      // Reset moving state in dialog
      const moveDialog = this.$refs.moveDialog as any
      if (moveDialog && typeof moveDialog.reset === 'function') {
        moveDialog.reset()
      }
    }
  },
})
</script>

<style scoped lang="scss">
:deep(.icon-label) {
  display: flex;
  align-items: center;
  gap: 4px;
}

.inline-icon {
  vertical-align: middle;
  margin-right: 4px;
}

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

  .thread-title-row {
    display: flex;
    align-items: center;
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

  .thread-title-input {
    flex: 1;

    :deep(input) {
      font-size: 1.75rem;
      font-weight: 600;
      color: var(--color-main-text);
      font-family: inherit;
    }
  }

  .edit-title-button,
  .save-title-button {
    flex-shrink: 0;
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

// Highlight animation for scrolled-to posts
:deep(.highlight-post) {
  animation: highlightFade 2s ease-in-out;
}

@keyframes highlightFade {
  0% {
    background-color: var(--color-primary-element-light);
    box-shadow: 0 0 0 4px var(--color-primary-element-light);
  }

  100% {
    background-color: transparent;
    box-shadow: none;
  }
}
</style>
