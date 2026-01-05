<template>
  <PageWrapper>
    <div class="search-view">
      <!-- Search Header -->
      <div class="search-header">
        <h2 class="search-title">{{ strings.searchTitle }}</h2>

        <!-- Search Input -->
        <div class="search-input-wrapper">
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="strings.searchPlaceholder"
            class="search-input"
            @keydown.enter="performSearch"
          />
          <NcButton variant="primary" @click="performSearch" :disabled="!canSearch || loading">
            <template #icon>
              <MagnifyIcon :size="20" />
            </template>
            {{ strings.search }}
          </NcButton>
        </div>

        <!-- Search Options -->
        <div class="search-options">
          <NcCheckboxRadioSwitch v-model="searchThreads" @update:checked="onOptionsChange">
            {{ strings.searchThreads }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-model="searchPosts" @update:checked="onOptionsChange">
            {{ strings.searchPosts }}
          </NcCheckboxRadioSwitch>

          <NcButton variant="tertiary" @click="showSyntaxHelp = !showSyntaxHelp">
            <template #icon>
              <HelpCircleIcon :size="20" />
            </template>
            {{ strings.syntaxHelp }}
          </NcButton>
        </div>

        <!-- Syntax Help -->
        <div v-if="showSyntaxHelp" class="syntax-help">
          <h3>{{ strings.searchSyntax }}</h3>
          <ul>
            <li><code>"exact phrase"</code> - {{ strings.helpExactPhrase }}</li>
            <li><code>term1 AND term2</code> - {{ strings.helpAnd }}</li>
            <li><code>term1 OR term2</code> - {{ strings.helpOr }}</li>
            <li><code>(term1 OR term2) AND term3</code> - {{ strings.helpGrouping }}</li>
            <li><code>-excluded</code> - {{ strings.helpExclude }}</li>
          </ul>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="center mt-16">
        <NcLoadingIcon :size="32" />
        <span class="muted ml-8">{{ strings.searching }}</span>
      </div>

      <!-- Error State -->
      <NcEmptyContent
        v-else-if="error"
        :title="strings.errorTitle"
        :description="error"
        class="mt-16"
      >
        <template #action>
          <NcButton @click="performSearch">{{ strings.retry }}</NcButton>
        </template>
      </NcEmptyContent>

      <!-- Empty State (no query) -->
      <NcEmptyContent
        v-else-if="!hasSearched"
        :title="strings.emptyTitle"
        :description="strings.emptyDesc"
        class="mt-16"
      >
        <template #icon>
          <MagnifyIcon :size="64" />
        </template>
      </NcEmptyContent>

      <!-- No Results -->
      <NcEmptyContent
        v-else-if="hasSearched && threadResults.length === 0 && postResults.length === 0"
        :title="strings.noResultsTitle"
        :description="strings.noResultsDesc"
        class="mt-16"
      >
        <template #icon>
          <MagnifyIcon :size="64" />
        </template>
      </NcEmptyContent>

      <!-- Results -->
      <div v-else class="search-results mt-16">
        <!-- Thread Results Section -->
        <section v-if="searchThreads && threadResults.length > 0" class="results-section">
          <h3 class="results-header">
            {{ strings.threadResults(threadCount) }}
          </h3>
          <div class="results-list">
            <SearchThreadResult
              v-for="thread in threadResults"
              :key="thread.id"
              :thread="thread"
              :query="currentQuery"
              @click="navigateToThread(thread)"
            />
          </div>
        </section>

        <!-- Post Results Section -->
        <section v-if="searchPosts && postResults.length > 0" class="results-section mt-16">
          <h3 class="results-header">
            {{ strings.postResults(postCount) }}
          </h3>
          <div class="results-list">
            <SearchPostResult
              v-for="post in postResults"
              :key="post.id"
              :post="post"
              :query="currentQuery"
            />
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
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PageWrapper from '@/components/PageWrapper'
import MagnifyIcon from '@icons/Magnify.vue'
import HelpCircleIcon from '@icons/HelpCircle.vue'
import SearchThreadResult from '@/components/SearchThreadResult'
import SearchPostResult from '@/components/SearchPostResult'
import type { Thread, Post } from '@/types'
import { ocs } from '@/axios'
import { t, n } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'

export default defineComponent({
  name: 'SearchView',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcCheckboxRadioSwitch,
    PageWrapper,
    MagnifyIcon,
    HelpCircleIcon,
    SearchThreadResult,
    SearchPostResult,
  },
  data() {
    return {
      searchQuery: '',
      currentQuery: '',
      searchThreads: true,
      searchPosts: true,
      showSyntaxHelp: false,
      loading: false,
      hasSearched: false,
      error: null as string | null,
      threadResults: [] as Thread[],
      postResults: [] as Post[],
      threadCount: 0,
      postCount: 0,
      limit: 50,
      offset: 0,

      strings: {
        searchTitle: t('forum', 'Search'),
        searchPlaceholder: t('forum', 'Enter search query …'),
        search: t('forum', 'Search'),
        searchThreads: t('forum', 'Search in threads'),
        searchPosts: t('forum', 'Search in replies'),
        syntaxHelp: t('forum', 'Syntax help'),
        searchSyntax: t('forum', 'Search syntax'),
        helpExactPhrase: t('forum', 'Match exact phrase'),
        helpAnd: t('forum', 'Both terms required'),
        helpOr: t('forum', 'Either term matches'),
        helpGrouping: t('forum', 'Group conditions with parentheses'),
        helpExclude: t('forum', 'Exclude term from results'),
        searching: t('forum', 'Searching …'),
        errorTitle: t('forum', 'Search Error'),
        retry: t('forum', 'Retry'),
        emptyTitle: t('forum', 'Enter a search query'),
        emptyDesc: t('forum', 'Use the search box above to find threads and replies'),
        noResultsTitle: t('forum', 'No results found'),
        noResultsDesc: t('forum', 'Try different keywords or check your syntax'),
        threadResults: (count: number) => n('forum', '%n thread found', '%n threads found', count),
        postResults: (count: number) => n('forum', '%n reply found', '%n replies found', count),
      },
    }
  },
  computed: {
    canSearch(): boolean {
      return this.searchQuery.trim().length > 0 && (this.searchThreads || this.searchPosts)
    },
  },
  created() {
    // Check for query parameter in URL
    const query = this.$route.query.q as string
    if (query) {
      this.searchQuery = query
      this.performSearch()
    }
  },
  methods: {
    async performSearch(): Promise<void> {
      const query = this.searchQuery.trim()

      if (!query) {
        showError(t('forum', 'Please enter a search query'))
        return
      }

      if (!this.searchThreads && !this.searchPosts) {
        showError(t('forum', 'Please select at least one search scope'))
        return
      }

      try {
        this.loading = true
        this.error = null
        this.currentQuery = query

        const response = await ocs.get('/search', {
          params: {
            q: query,
            searchThreads: this.searchThreads,
            searchPosts: this.searchPosts,
            limit: this.limit,
            offset: this.offset,
          },
        })

        this.threadResults = response.data.threads || []
        this.postResults = response.data.posts || []
        this.threadCount = response.data.threadCount || 0
        this.postCount = response.data.postCount || 0
        this.hasSearched = true

        // Update URL with query
        if (this.$route.query.q !== query) {
          this.$router.replace({ query: { q: query } })
        }
      } catch (e) {
        console.error('Failed to perform search', e)
        this.error = (e as Error).message || t('forum', 'Failed to search')
      } finally {
        this.loading = false
      }
    },

    onOptionsChange(): void {
      // Re-search if we already have results
      if (this.hasSearched && this.currentQuery) {
        this.performSearch()
      }
    },

    navigateToThread(thread: Thread): void {
      if (thread.slug) {
        this.$router.push(`/t/${thread.slug}`)
      }
    },
  },
})
</script>

<style scoped lang="scss">
.search-view {
  .search-header {
    margin-bottom: 24px;
  }

  .search-title {
    margin: 0 0 16px 0;
    font-size: 1.75rem;
    font-weight: 600;
    color: var(--color-main-text);
  }

  .search-input-wrapper {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;

    .search-input {
      flex: 1;
      padding: 10px 12px;
      font-size: 1rem;
      border: 1px solid var(--color-border);
      border-radius: 6px;
      background: var(--color-main-background);
      color: var(--color-main-text);

      &:focus {
        outline: none;
        border-color: var(--color-primary-element);
      }
    }
  }

  .search-options {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
  }

  .syntax-help {
    margin-top: 16px;
    padding: 16px;
    background: var(--color-background-hover);
    border-radius: 8px;
    border: 1px solid var(--color-border);

    h3 {
      margin: 0 0 12px 0;
      font-size: 1rem;
      font-weight: 600;
    }

    ul {
      margin: 0;
      padding-left: 24px;

      li {
        margin-bottom: 8px;
        line-height: 1.6;

        code {
          background: var(--color-background-dark);
          padding: 2px 6px;
          border-radius: 3px;
          font-family: 'Courier New', monospace;
          font-size: 0.9rem;
        }
      }
    }
  }

  .center {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .muted {
    color: var(--color-text-maxcontrast);
    opacity: 0.7;
  }

  .mt-16 {
    margin-top: 16px;
  }

  .ml-8 {
    margin-left: 8px;
  }

  .results-section {
    .results-header {
      margin: 0 0 16px 0;
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--color-main-text);
    }

    .results-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
  }
}
</style>
