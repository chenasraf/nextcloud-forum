<template>
  <PageWrapper>
    <div class="admin-dashboard">
      <PageHeader :title="strings.title" :subtitle="strings.subtitle" />

      <!-- Loading state -->
      <div v-if="loading" class="center mt-16">
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
          <NcButton @click="refresh">{{ strings.retry }}</NcButton>
        </template>
      </NcEmptyContent>

      <!-- Dashboard content -->
      <div v-else-if="stats" class="dashboard-content">
        <!-- Totals section -->
        <section class="stats-section">
          <h3>{{ strings.totals }}</h3>
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-icon">
                <AccountMultipleIcon :size="32" />
              </div>
              <div class="stat-info">
                <div class="stat-value">{{ stats.totals.users }}</div>
                <div class="stat-label">{{ strings.totalUsers }}</div>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-icon">
                <ForumIcon :size="32" />
              </div>
              <div class="stat-info">
                <div class="stat-value">{{ stats.totals.threads }}</div>
                <div class="stat-label">{{ strings.totalThreads }}</div>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-icon">
                <MessageTextIcon :size="32" />
              </div>
              <div class="stat-info">
                <div class="stat-value">{{ stats.totals.posts }}</div>
                <div class="stat-label">{{ strings.totalPosts }}</div>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-icon">
                <FolderIcon :size="32" />
              </div>
              <div class="stat-info">
                <div class="stat-value">{{ stats.totals.categories }}</div>
                <div class="stat-label">{{ strings.totalCategories }}</div>
              </div>
            </div>
          </div>
        </section>

        <!-- Recent activity section -->
        <section class="stats-section mt-24">
          <h3>{{ strings.recentActivity }}</h3>
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-icon">
                <AccountPlusIcon :size="32" />
              </div>
              <div class="stat-info">
                <div class="stat-value">{{ stats.recent.users }}</div>
                <div class="stat-label">{{ strings.newUsers }}</div>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-icon">
                <ForumIcon :size="32" />
              </div>
              <div class="stat-info">
                <div class="stat-value">{{ stats.recent.threads }}</div>
                <div class="stat-label">{{ strings.newThreads }}</div>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-icon">
                <MessageTextIcon :size="32" />
              </div>
              <div class="stat-info">
                <div class="stat-value">{{ stats.recent.posts }}</div>
                <div class="stat-label">{{ strings.newPosts }}</div>
              </div>
            </div>
          </div>
        </section>

        <!-- Top contributors section -->
        <section class="stats-section mt-24">
          <h3>{{ strings.topContributors }}</h3>
          <div class="contributors-grid">
            <!-- Recent contributors (last 7 days) -->
            <div class="contributors-column">
              <h4>{{ strings.last7Days }}</h4>
              <div v-if="stats.topContributorsRecent.length > 0" class="contributors-list">
                <div
                  v-for="(contributor, index) in stats.topContributorsRecent"
                  :key="contributor.userId"
                  class="contributor-item"
                >
                  <div class="contributor-rank">{{ index + 1 }}</div>
                  <UserInfo
                    :user-id="contributor.userId"
                    :display-name="contributor.userId"
                    :avatar-size="40"
                  >
                    <template #meta>
                      <div class="contributor-stats muted">
                        {{ strings.threadsCount(contributor.threadCount) }} /
                        {{ strings.postsCount(contributor.postCount) }}
                      </div>
                    </template>
                  </UserInfo>
                </div>
              </div>
              <div v-else class="muted">{{ strings.noContributors }}</div>
            </div>

            <!-- All-time contributors -->
            <div class="contributors-column">
              <h4>{{ strings.allTime }}</h4>
              <div v-if="stats.topContributorsAllTime.length > 0" class="contributors-list">
                <div
                  v-for="(contributor, index) in stats.topContributorsAllTime"
                  :key="contributor.userId"
                  class="contributor-item"
                >
                  <div class="contributor-rank">{{ index + 1 }}</div>
                  <UserInfo
                    :user-id="contributor.userId"
                    :display-name="contributor.userId"
                    :avatar-size="40"
                  >
                    <template #meta>
                      <div class="contributor-stats muted">
                        {{ strings.threadsCount(contributor.threadCount) }} /
                        {{ strings.postsCount(contributor.postCount) }}
                      </div>
                    </template>
                  </UserInfo>
                </div>
              </div>
              <div v-else class="muted">{{ strings.noContributors }}</div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </PageWrapper>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import UserInfo from '@/components/UserInfo.vue'
import PageWrapper from '@/components/PageWrapper.vue'
import PageHeader from '@/components/PageHeader.vue'
import AccountMultipleIcon from '@icons/AccountMultiple.vue'
import AccountPlusIcon from '@icons/AccountPlus.vue'
import ForumIcon from '@icons/Forum.vue'
import MessageTextIcon from '@icons/MessageText.vue'
import FolderIcon from '@icons/Folder.vue'
import { ocs } from '@/axios'
import { t, n } from '@nextcloud/l10n'

interface DashboardStats {
  totals: {
    users: number
    threads: number
    posts: number
    categories: number
  }
  recent: {
    users: number
    threads: number
    posts: number
  }
  topContributorsAllTime: Array<{
    userId: string
    postCount: number
    threadCount: number
  }>
  topContributorsRecent: Array<{
    userId: string
    postCount: number
    threadCount: number
  }>
}

export default defineComponent({
  name: 'AdminDashboard',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    UserInfo,
    PageWrapper,
    PageHeader,
    AccountMultipleIcon,
    AccountPlusIcon,
    ForumIcon,
    MessageTextIcon,
    FolderIcon,
  },
  data() {
    return {
      loading: false,
      stats: null as DashboardStats | null,
      error: null as string | null,

      strings: {
        title: t('forum', 'Admin Dashboard'),
        subtitle: t('forum', 'Overview of forum activity and statistics'),
        loading: t('forum', 'Loading statistics…'),
        errorTitle: t('forum', 'Error loading dashboard'),
        retry: t('forum', 'Retry'),
        totals: t('forum', 'Total Statistics'),
        totalUsers: t('forum', 'Total Users'),
        totalThreads: t('forum', 'Total Threads'),
        totalPosts: t('forum', 'Total Posts'),
        totalCategories: t('forum', 'Total Categories'),
        recentActivity: t('forum', 'Recent Activity (Last 7 Days)'),
        newUsers: t('forum', 'New Users'),
        newThreads: t('forum', 'New Threads'),
        newPosts: t('forum', 'New Posts'),
        topContributors: t('forum', 'Top Contributors'),
        noContributors: t('forum', 'No contributors yet'),
        last7Days: t('forum', 'Last 7 Days'),
        allTime: t('forum', 'All Time'),
        threadsCount: (count: number) => n('forum', '%n thread', '%n threads', count),
        postsCount: (count: number) => n('forum', '%n post', '%n posts', count),
      },
    }
  },
  created() {
    this.refresh()
  },
  methods: {
    async refresh(): Promise<void> {
      try {
        this.loading = true
        this.error = null

        const response = await ocs.get<DashboardStats>('/admin/dashboard')
        this.stats = response.data
      } catch (e) {
        console.error('Failed to load dashboard stats', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },
  },
})
</script>

<style scoped lang="scss">
.admin-dashboard {
  .muted {
    color: var(--color-text-maxcontrast);
    opacity: 0.7;
  }

  .mt-8 {
    margin-top: 8px;
  }

  .mt-16 {
    margin-top: 16px;
  }

  .mt-24 {
    margin-top: 24px;
  }

  .ml-8 {
    margin-left: 8px;
  }

  .center {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .page-header {
    margin-bottom: 24px;

    h2 {
      margin: 0 0 6px 0;
    }
  }

  .dashboard-content {
    .stats-section {
      h3 {
        margin: 0 0 16px 0;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--color-main-text);
      }
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
    }

    .stat-card {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 20px;
      background: var(--color-background-hover);
      border: 1px solid var(--color-border);
      border-radius: 8px;

      .stat-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        background: var(--color-primary-element-light);
        border-radius: 8px;
        color: var(--color-primary-element);
      }

      .stat-info {
        flex: 1;

        .stat-value {
          font-size: 1.75rem;
          font-weight: 600;
          color: var(--color-main-text);
          line-height: 1.2;
        }

        .stat-label {
          font-size: 0.9rem;
          color: var(--color-text-maxcontrast);
          margin-top: 4px;
        }
      }
    }

    .contributors-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 24px;

      @media (max-width: 768px) {
        grid-template-columns: 1fr;
      }
    }

    .contributors-column {
      h4 {
        margin: 0 0 12px 0;
        font-size: 1rem;
        font-weight: 500;
        color: var(--color-text-maxcontrast);
      }
    }

    .contributors-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .contributor-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 16px;
      background: var(--color-background-hover);
      border: 1px solid var(--color-border);
      border-radius: 8px;

      .contributor-rank {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        background: var(--color-primary-element-light);
        border-radius: 50%;
        color: var(--color-primary-element);
        font-weight: 600;
        font-size: 0.9rem;
      }

      .contributor-stats {
        font-size: 0.85rem;
        margin-top: 2px;
      }
    }
  }
}
</style>
