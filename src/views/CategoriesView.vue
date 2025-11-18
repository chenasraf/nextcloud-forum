<template>
  <PageWrapper :full-width="true">
    <template #toolbar>
      <AppToolbar>
        <template #left>
          <h2 class="view-title">{{ strings.title }}</h2>
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

    <div class="categories-view">
      <header class="page-header">
        <h2>{{ forumTitle }}</h2>
        <p class="muted">{{ forumSubtitle }}</p>
      </header>

      <!-- Loading state -->
      <div class="center mt-16" v-if="loading">
        <NcLoadingIcon :size="32" />
        <span class="muted ml-8">{{ strings.loading }}</span>
      </div>

      <!-- Empty state -->
      <NcEmptyContent
        v-else-if="categoryHeaders.length === 0"
        :title="strings.emptyTitle"
        :description="strings.emptyDesc"
        class="mt-16"
      />

      <!-- Categories list -->
      <section v-else class="mt-16">
        <div v-for="header in categoryHeaders" :key="header.id" class="header-section">
          <h3 class="header-title">{{ header.name }}</h3>

          <!-- Categories grid -->
          <div v-if="header.categories && header.categories.length > 0" class="categories-grid">
            <CategoryCard
              v-for="category in header.categories"
              :key="category.id"
              :category="category"
              @click="navigateToCategory(category)"
            />
          </div>

          <!-- Empty state for header with no categories -->
          <p v-else class="no-categories muted">{{ strings.noCategories }}</p>
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
import AppToolbar from '@/components/AppToolbar.vue'
import PageWrapper from '@/components/PageWrapper.vue'
import CategoryCard from '@/components/CategoryCard.vue'
import RefreshIcon from '@icons/Refresh.vue'
import { useCategories } from '@/composables/useCategories'
import type { Category } from '@/types'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'

export default defineComponent({
  name: 'CategoriesView',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    AppToolbar,
    PageWrapper,
    CategoryCard,
    RefreshIcon,
  },
  setup() {
    const { categoryHeaders, loading, fetchCategories, refresh } = useCategories()
    return {
      categoryHeaders,
      loading,
      fetchCategories,
      refreshCategories: refresh,
    }
  },
  data() {
    return {
      forumTitle: t('forum', 'Forum'),
      forumSubtitle: t('forum', 'Welcome to the forum'),
      strings: {
        title: t('forum', 'Categories'),
        refresh: t('forum', 'Refresh'),
        loading: t('forum', 'Loading…'),
        emptyTitle: t('forum', 'No categories yet'),
        emptyDesc: t('forum', 'Categories will appear here once they are created.'),
        noCategories: t('forum', 'No categories in this section'),
      },
    }
  },
  async created() {
    // Fetch forum settings and categories
    try {
      await Promise.all([this.fetchForumSettings(), this.fetchCategories()])
    } catch (e) {
      console.error('Failed to fetch initial data', e)
    }
  },
  methods: {
    async fetchForumSettings() {
      try {
        const response = await ocs.get<{ title: string; subtitle: string }>('/admin/settings')
        this.forumTitle = response.data.title || t('forum', 'Forum')
        this.forumSubtitle = response.data.subtitle || t('forum', 'Welcome to the forum')
      } catch (e) {
        // Silently fail and use defaults if settings can't be loaded
        console.debug('Could not load forum settings, using defaults', e)
      }
    },

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

  .no-categories {
    padding: 24px;
    text-align: center;
    font-style: italic;
  }
}
</style>
