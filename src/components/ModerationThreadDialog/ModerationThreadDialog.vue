<template>
  <NcDialog :name="title" :open="open" size="large" @update:open="$emit('update:open', $event)">
    <!-- Loading -->
    <div v-if="loading" class="center mt-16 mb-16">
      <NcLoadingIcon :size="32" />
    </div>

    <!-- Posts -->
    <div v-else-if="thread" class="thread-dialog">
      <!-- First post (always visible) -->
      <PostCard
        v-if="firstPost"
        :post="firstPost"
        :is-first-post="true"
        :class="{ 'deleted-post': firstPost.deletedAt }"
      />

      <!-- Pagination above replies -->
      <Pagination
        v-if="replyMaxPages > 1"
        :current-page="replyPage"
        :max-pages="replyMaxPages"
        @update:current-page="loadPage"
      />

      <!-- Loading replies on page change -->
      <div v-if="loadingReplies" class="center mt-16 mb-16">
        <NcLoadingIcon :size="24" />
      </div>

      <!-- Replies -->
      <template v-else>
        <PostCard
          v-for="post in replies"
          :key="post.id"
          :post="post"
          :class="{ 'deleted-post': post.deletedAt }"
        />
      </template>

      <!-- Pagination below replies -->
      <Pagination
        v-if="replyMaxPages > 1"
        :current-page="replyPage"
        :max-pages="replyMaxPages"
        @update:current-page="loadPage"
      />
    </div>

    <template #actions>
      <NcButton variant="primary" :disabled="restoring" @click="$emit('restore')">
        <template #icon>
          <NcLoadingIcon v-if="restoring" :size="20" />
          <DeleteRestoreIcon v-else :size="20" />
        </template>
        {{ strings.restoreThread }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import PostCard from '@/components/PostCard'
import Pagination from '@/components/Pagination'
import DeleteRestoreIcon from '@icons/DeleteRestore.vue'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'

const REPLIES_PER_PAGE = 20

export default defineComponent({
  name: 'ModerationThreadDialog',
  components: {
    NcButton,
    NcDialog,
    NcLoadingIcon,
    PostCard,
    Pagination,
    DeleteRestoreIcon,
  },
  props: {
    open: { type: Boolean, required: true },
    threadId: { type: Number, default: null },
    threadTitle: { type: String, default: '' },
    restoring: { type: Boolean, default: false },
  },
  emits: ['update:open', 'restore'],
  data() {
    return {
      loading: false,
      loadingReplies: false,
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      thread: null as any | null,
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      firstPost: null as any | null,
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      replies: [] as any[],
      replyPage: 1,
      totalReplies: 0,
      strings: {
        restoreThread: t('forum', 'Restore thread'),
      },
    }
  },
  computed: {
    title(): string {
      return this.thread?.title || this.threadTitle || ''
    },
    replyMaxPages(): number {
      return Math.ceil(this.totalReplies / REPLIES_PER_PAGE)
    },
  },
  watch: {
    open(val: boolean) {
      if (val && this.threadId) {
        this.replyPage = 1
        this.loadThread()
      } else if (!val) {
        this.thread = null
        this.firstPost = null
        this.replies = []
      }
    },
  },
  methods: {
    async loadThread(): Promise<void> {
      if (!this.threadId) return
      try {
        this.loading = true
        await this.fetchPosts()
      } catch (e) {
        console.error('Failed to load thread', e)
      } finally {
        this.loading = false
      }
    },

    async fetchPosts(): Promise<void> {
      // First post is always at offset 0 with limit 1 on first load,
      // but the API returns all posts for the thread. We request with
      // offset accounting for the first post: replies start at offset 1.
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const response = await ocs.get<any>(`/moderation/threads/${this.threadId}`, {
        params: {
          postLimit: REPLIES_PER_PAGE + 1, // +1 for first post on page 1
          postOffset: this.replyPage === 1 ? 0 : 1 + (this.replyPage - 1) * REPLIES_PER_PAGE,
        },
      })

      this.thread = response.data
      const allPosts = response.data.posts || []
      const totalPosts = response.data.totalPosts ?? allPosts.length

      // Split first post from replies
      if (this.replyPage === 1) {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        this.firstPost = allPosts.find((p: any) => p.isFirstPost) || allPosts[0] || null
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        this.replies = allPosts.filter((p: any) => p !== this.firstPost)
      } else {
        // On subsequent pages, first post was already loaded
        this.replies = allPosts
      }

      // Total replies = total posts minus 1 (first post)
      this.totalReplies = Math.max(0, totalPosts - 1)
    },

    async loadPage(page: number): Promise<void> {
      this.replyPage = page
      try {
        this.loadingReplies = true
        await this.fetchPosts()
      } catch (e) {
        console.error('Failed to load replies page', e)
      } finally {
        this.loadingReplies = false
      }
    },
  },
})
</script>

<style scoped lang="scss">
.thread-dialog {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 8px;
}

.deleted-post {
  opacity: 0.5;
}
</style>
