<template>
  <PageWrapper :full-width="true">
    <template #toolbar>
      <AppToolbar>
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

    <div class="categories-view">
      <PageHeader :title="forumTitle" :subtitle="forumSubtitle" :loading="settingsLoading" />

      <!-- Loading state -->
      <div class="center mt-16" v-if="loading">
        <NcLoadingIcon :size="32" />
        <span class="muted ml-8">{{ strings.loading }}</span>
      </div>

      <!-- Empty state -->
      <NcEmptyContent
        v-else-if="visibleHeaders.length === 0"
        :title="strings.emptyTitle"
        :description="strings.emptyDesc"
        class="mt-16"
      />

      <!-- Categories list -->
      <section v-else class="mt-16">
        <div v-for="header in visibleHeaders" :key="header.id" class="header-section">
          <h3 class="header-title">{{ header.name }}</h3>

          <div class="categories-grid">
            <CategoryCard
              v-for="category in header.categories"
              :key="category.id"
              :category="category"
              :children="category.children || []"
              :hide-children="category.hideChildrenOnCard"
              :unread-count="userId !== null ? getUnreadCount(category, true) : 0"
              :show-unread-count="showUnreadCounts"
              @click="navigateToCategory(category)"
            />
          </div>
        </div>
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
import CategoryCard from '@/components/CategoryCard'
import RefreshIcon from '@icons/Refresh.vue'
import { useCategories } from '@/composables/useCategories'
import { usePublicSettings } from '@/composables/usePublicSettings'
import { useCurrentUser } from '@/composables/useCurrentUser'
import { useUserPreferences } from '@/composables/useUserPreferences'
import type { Category } from '@/types'
import { t } from '@nextcloud/l10n'

export default defineComponent({
  name: 'CategoriesView',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    AppToolbar,
    PageWrapper,
    PageHeader,
    CategoryCard,
    RefreshIcon,
  },
  setup() {
    const { categoryHeaders, loading, fetchCategories, refresh, getUnreadCount } = useCategories()
    const { settings, loading: settingsLoading, fetchPublicSettings } = usePublicSettings()
    const { userId } = useCurrentUser()
    const { showUnreadCounts } = useUserPreferences()

    return {
      categoryHeaders,
      loading,
      fetchCategories,
      refreshCategories: refresh,
      getUnreadCount,
      showUnreadCounts,
      publicSettings: settings,
      settingsLoading,
      fetchPublicSettings,
      userId,
    }
  },
  data() {
    return {
      strings: {
        refresh: t('forum', 'Refresh'),
        loading: t('forum', 'Loading …'),
        emptyTitle: t('forum', 'No categories yet'),
        emptyDesc: t('forum', 'Categories will appear here once they are created.'),
      },
    }
  },
  computed: {
    forumTitle(): string {
      return this.publicSettings?.title || t('forum', 'Forum')
    },
    forumSubtitle(): string {
      return this.publicSettings?.subtitle || t('forum', 'Welcome to the forum!')
    },
    visibleHeaders() {
      return this.categoryHeaders.filter((header) => (header.categories?.length ?? 0) > 0)
    },
  },
  async created() {
    // Fetch forum settings and categories
    try {
      await Promise.all([this.fetchPublicSettings(), this.fetchCategories()])
    } catch (e) {
      console.error('Failed to fetch initial data', e)
    }
  },
  methods: {
    async refresh() {
      try {
        await this.refreshCategories()
      } catch (e) {
        console.error('Failed to refresh categories', e)
      }
    },

    navigateToCategory(category: Category) {
      this.$router.push(`/c/${category.slug}`)
    },
  },
})
</script>

<style scoped lang="scss">
.categories-view {
  .view-title {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
  }

  .header-section {
    margin-bottom: 32px;

    &:last-child {
      margin-bottom: 0;
    }
  }

  .header-title {
    margin: 0 0 16px 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--color-main-text);
    padding-bottom: 8px;
    border-bottom: 2px solid var(--color-border);
  }

  .categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
  }
}
</style>
