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

    <div class="profile-view">
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

      <!-- Profile content -->
      <div v-else class="profile-content mt-16">
        <!-- User Header -->
        <div class="user-header">
          <div class="user-avatar">
            <NcAvatar :user="userId" :size="80" :show-user-status="false" />
          </div>
          <div class="user-info">
            <h2 class="user-name">{{ displayName }}</h2>
            <div class="user-meta">
              <span v-if="userStats && userStats.createdAt" class="meta-item">
                <span class="meta-label">{{ strings.firstPost }}</span>
                <NcDateTime :timestamp="userStats.createdAt * 1000" />
              </span>
              <span v-if="userStats && userStats.createdAt" class="meta-divider">·</span>
              <span class="meta-item">
                <span class="meta-label">{{ strings.threads }}</span>
                <span class="meta-value">{{ userStats?.threadCount || 0 }}</span>
              </span>
              <span class="meta-divider">·</span>
              <span class="meta-item">
                <span class="meta-label">{{ strings.posts }}</span>
                <span class="meta-value">{{ userStats?.postCount || 0 }}</span>
              </span>
            </div>
          </div>
        </div>

        <!-- Tabs -->
        <div class="profile-tabs mt-24">
          <div class="tabs-header">
            <button
              class="tab-button"
              :class="{ active: activeTab === 'threads' }"
              @click="activeTab = 'threads'"
            >
              {{ strings.threads }} ({{ threads.length }})
            </button>
            <button
              class="tab-button"
              :class="{ active: activeTab === 'posts' }"
              @click="activeTab = 'posts'"
            >
              {{ strings.replies }} ({{ posts.length }})
            </button>
          </div>

          <div class="tabs-content mt-16">
            <!-- Threads Tab -->
            <div v-if="activeTab === 'threads'" class="tab-pane">
              <div v-if="loadingThreads" class="center">
                <NcLoadingIcon :size="24" />
              </div>
              <NcEmptyContent
                v-else-if="threads.length === 0"
                :title="strings.noThreads"
                :description="strings.noThreadsDesc"
              />
              <div v-else class="threads-list">
                <ThreadCard
                  v-for="thread in threads"
                  :key="thread.id"
                  :thread="thread"
                  @click="navigateToThread(thread)"
                />
              </div>
            </div>

            <!-- Posts Tab -->
            <div v-if="activeTab === 'posts'" class="tab-pane">
              <div v-if="loadingPosts" class="center">
                <NcLoadingIcon :size="24" />
              </div>
              <NcEmptyContent
                v-else-if="posts.length === 0"
                :title="strings.noPosts"
                :description="strings.noPostsDesc"
              />
              <div v-else class="posts-list">
                <div
                  v-for="post in posts"
                  :key="post.id"
                  class="post-item"
                  @click="navigateToPost(post)"
                >
                  <div class="post-meta">
                    <span class="post-thread" v-if="post.threadTitle">
                      {{ strings.inThread }} <strong>{{ post.threadTitle }}</strong>
                    </span>
                    <span class="post-date">
                      <NcDateTime v-if="post.createdAt" :timestamp="post.createdAt * 1000" />
                    </span>
                  </div>
                  <div class="post-content" v-html="post.content"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PageWrapper>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import AppToolbar from '@/components/AppToolbar.vue'
import PageWrapper from '@/components/PageWrapper.vue'
import ThreadCard from '@/components/ThreadCard.vue'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import RefreshIcon from '@icons/Refresh.vue'
import type { UserStats, Thread, Post } from '@/types'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

export default defineComponent({
  name: 'ProfileView',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcAvatar,
    NcDateTime,
    AppToolbar,
    PageWrapper,
    ThreadCard,
    ArrowLeftIcon,
    RefreshIcon,
  },
  data() {
    return {
      loading: false,
      loadingThreads: false,
      loadingPosts: false,
      userStats: null as UserStats | null,
      displayName: '',
      threads: [] as Thread[],
      posts: [] as Post[],
      error: null as string | null,
      activeTab: 'threads' as 'threads' | 'posts',
      strings: {
        back: t('forum', 'Back'),
        refresh: t('forum', 'Refresh'),
        loading: t('forum', 'Loading...'),
        errorTitle: t('forum', 'Error'),
        retry: t('forum', 'Retry'),
        firstPost: t('forum', 'First post'),
        posts: t('forum', 'Posts'),
        threads: t('forum', 'Threads'),
        replies: t('forum', 'Replies'),
        noThreads: t('forum', 'No threads'),
        noThreadsDesc: t('forum', 'This user has not created any threads yet'),
        noPosts: t('forum', 'No replies'),
        noPostsDesc: t('forum', 'This user has not posted any replies yet'),
        inThread: t('forum', 'in thread'),
      },
    }
  },
  computed: {
    userId(): string {
      return this.$route.params.userId as string
    },
  },
  mounted() {
    this.loadProfile()
  },
  watch: {
    userId() {
      this.loadProfile()
    },
    activeTab(newTab) {
      if (newTab === 'threads' && this.threads.length === 0) {
        this.loadThreads()
      } else if (newTab === 'posts' && this.posts.length === 0) {
        this.loadPosts()
      }
    },
  },
  methods: {
    async loadProfile() {
      this.loading = true
      this.error = null

      try {
        // Get display name from Nextcloud first
        this.displayName = await this.getDisplayName(this.userId)

        // Load user stats (may not exist if user hasn't posted)
        try {
          const userResponse = await ocs.get(`/users/${this.userId}`)
          this.userStats = userResponse.data
        } catch (err: any) {
          // 404 is OK - user hasn't posted yet
          if (err.response?.status !== 404) {
            throw err
          }
          this.userStats = null
        }

        // Load both tabs on initial load for accurate counts
        await Promise.all([this.loadThreads(), this.loadPosts()])
      } catch (err: any) {
        console.error('Error loading profile:', err)
        this.error = err.response?.data?.error || t('forum', 'Failed to load user profile')
      } finally {
        this.loading = false
      }
    },

    async loadThreads() {
      this.loadingThreads = true
      try {
        const response = await ocs.get(`/users/${this.userId}/threads`)
        this.threads = response.data
      } catch (err) {
        console.error('Error loading threads:', err)
      } finally {
        this.loadingThreads = false
      }
    },

    async loadPosts() {
      this.loadingPosts = true
      try {
        // Exclude first posts (those are the thread content itself, shown in threads tab)
        const response = await ocs.get(`/users/${this.userId}/posts`, {
          params: { excludeFirstPosts: '1' },
        })

        // Enrich posts with thread information
        const enrichedPosts = await Promise.all(
          response.data.map(async (post: Post) => {
            try {
              const threadResponse = await ocs.get(`/threads/${post.threadId}`, {
                params: { incrementView: '0' },
              })
              return {
                ...post,
                threadTitle: threadResponse.data.title,
                threadSlug: threadResponse.data.slug,
              }
            } catch {
              return post
            }
          }),
        )

        this.posts = enrichedPosts
      } catch (err) {
        console.error('Error loading posts:', err)
      } finally {
        this.loadingPosts = false
      }
    },

    async getDisplayName(userId: string): Promise<string> {
      // For current user, use getCurrentUser()
      const currentUser = getCurrentUser()
      if (currentUser && currentUser.uid === userId) {
        return currentUser.displayName || userId
      }

      // For other users, we'll need to make an OCS API call to get the display name
      // Nextcloud provides this via the OCS user API
      try {
        const response = await fetch(generateUrl('/ocs/v2.php/cloud/users/{userId}', { userId }), {
          headers: {
            'OCS-APIRequest': 'true',
            Accept: 'application/json',
          },
        })
        const data = await response.json()
        return data.ocs?.data?.displayname || userId
      } catch (err) {
        console.error('Error fetching display name:', err)
        return userId
      }
    },

    refresh() {
      this.threads = []
      this.posts = []
      this.loadProfile()
    },

    goBack() {
      this.$router.back()
    },

    navigateToThread(thread: Thread) {
      this.$router.push(`/t/${thread.slug}`)
    },

    navigateToPost(post: Post) {
      if (post.threadSlug) {
        this.$router.push(`/t/${post.threadSlug}#post-${post.id}`)
      } else {
        this.$router.push(`/thread/${post.threadId}#post-${post.id}`)
      }
    },
  },
})
</script>

<style scoped lang="scss">
.profile-view {
  .center {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px;
  }

  .ml-8 {
    margin-left: 8px;
  }

  .mt-16 {
    margin-top: 16px;
  }

  .mt-24 {
    margin-top: 24px;
  }

  .muted {
    color: var(--color-text-maxcontrast);
  }

  .user-header {
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 24px;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 8px;
  }

  .user-info {
    flex: 1;
  }

  .user-name {
    margin: 0 0 8px 0;
    font-size: 24px;
    font-weight: 600;
  }

  .user-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-text-maxcontrast);
    font-size: 14px;
  }

  .meta-label {
    margin-right: 4px;
  }

  .meta-value {
    font-weight: 600;
    color: var(--color-text-light);
  }

  .meta-divider {
    color: var(--color-text-maxcontrast);
  }

  .profile-tabs {
    .tabs-header {
      display: flex;
      border-bottom: 1px solid var(--color-border);
    }

    .tab-button {
      padding: 12px 24px;
      background: none;
      border: none;
      border-bottom: 2px solid transparent;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      color: var(--color-text-maxcontrast);
      transition: all 0.2s;
      border-radius: 0;

      &:hover {
        color: var(--color-text-light);
        background: var(--color-background-hover);
      }

      &.active {
        color: var(--color-text-light);
        border-bottom-color: var(--color-text-light);
      }
    }

    .tabs-content {
      min-height: 200px;
    }
  }

  .threads-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .posts-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .post-item {
    padding: 16px;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;

    &:hover {
      background: var(--color-background-hover);
      border-color: var(--color-primary-element);
    }
  }

  .post-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    font-size: 14px;
    color: var(--color-text-maxcontrast);
  }

  .post-thread {
    strong {
      color: var(--color-text-light);
    }
  }

  .post-content {
    color: var(--color-text-light);
    line-height: 1.6;

    // Truncate long content
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
}
</style>
