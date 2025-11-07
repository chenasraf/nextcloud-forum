<template>
  <NcContent app-name="forum">
    <!-- Left sidebar -->
    <NcAppNavigation>
      <template #search>
        <NcAppNavigationSearch
          v-model="searchValue"
          :label="strings.searchLabel"
          :placeholder="strings.searchPlaceholder"
        />
      </template>

      <template #list>
        <NcAppNavigationItem :name="strings.navHome" :to="{ path: '/' }" :open="true">
          <template #icon>
            <HomeIcon :size="20" />
          </template>

          <!-- Category headers as collapsible submenus -->
          <NcAppNavigationItem
            v-for="header in categoryHeaders"
            :key="`header-${header.id}`"
            :name="header.name"
            :open="isHeaderOpen(header.id)"
            @click.native.prevent="toggleHeader(header.id)"
          >
            <template #icon>
              <FolderIcon :size="20" />
            </template>

            <!-- Categories under each header -->
            <NcAppNavigationItem
              v-for="category in header.categories"
              :key="`category-${category.id}`"
              :name="category.name"
              :to="{ path: `/c/${category.slug}` }"
            >
              <template #icon>
                <ForumIcon :size="20" />
              </template>
            </NcAppNavigationItem>
          </NcAppNavigationItem>
        </NcAppNavigationItem>
      </template>

      <template #footer>
        <!-- Optional footer controls -->
      </template>
    </NcAppNavigation>

    <!-- Main content -->
    <NcAppContent id="forum-main">
      <div id="forum-content">
        <header class="page-header">
          <h2>{{ strings.title }}</h2>
          <p class="muted" v-html="strings.subtitle"></p>
        </header>

        <div id="forum-router">
          <div v-if="isRouterLoading" class="router-loading">
            <NcLoadingIcon :size="48" />
          </div>
          <router-view v-else />
        </div>
      </div>
    </NcAppContent>
  </NcContent>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { t } from '@nextcloud/l10n'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationSearch from '@nextcloud/vue/components/NcAppNavigationSearch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import HomeIcon from '@icons/Home.vue'
import ForumIcon from '@icons/Forum.vue'
import FolderIcon from '@icons/Folder.vue'
import PuzzleIcon from '@icons/Puzzle.vue'
import InfoIcon from '@icons/Information.vue'
import { useCategories } from '@/composables/useCategories'

export default defineComponent({
  name: 'AppUserWrapper',
  components: {
    NcContent,
    NcAppContent,
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppNavigationSearch,
    NcLoadingIcon,
    HomeIcon,
    ForumIcon,
    FolderIcon,
    PuzzleIcon,
    InfoIcon,
  },
  setup() {
    const { categoryHeaders, fetchCategories } = useCategories()
    return {
      categoryHeaders,
      fetchCategories,
    }
  },
  // Tell NcContent we *do* have a sidebar so it arranges layout properly
  provide() {
    return { 'NcContent:setHasAppNavigation': () => true }
  },
  data() {
    return {
      searchValue: '',
      isRouterLoading: false,
      openHeaders: {} as Record<number, boolean>, // Track which headers are open
      // Mount path for this app section; adjust to your mount.
      basePath: '/apps/forum',
      strings: {
        title: t('forum', 'Hello World — App'),
        subtitle: t(
          'forum',
          'Use the sidebar to navigate between views. Backend calls use {cStart}axios{cEnd} and OCS responses.',
          { cStart: '<code>', cEnd: '</code>' },
          undefined,
          { escape: false },
        ),
        searchLabel: t('forum', 'Search'),
        searchPlaceholder: t('forum', 'Type to filter…'),
        navHome: t('forum', 'Home'),
        navExamples: t('forum', 'Examples'),
        navAbout: t('forum', 'About'),
      },
      _removeBeforeEach: null as (() => void) | null,
      _removeAfterEach: null as (() => void) | null,
    }
  },
  async created() {
    // Fetch categories for sidebar
    try {
      await this.fetchCategories()

      // Initialize all headers as open by default
      const openState: Record<number, boolean> = {}
      this.categoryHeaders.forEach((header) => {
        openState[header.id] = true
      })
      this.openHeaders = openState
    } catch (e) {
      console.error('Failed to load categories for sidebar:', e)
    }

    // Show a loading overlay while routes are changing
    this._removeBeforeEach = this.$router.beforeEach((to, from, next) => {
      this.isRouterLoading = true
      next()
    })
    this._removeAfterEach = this.$router.afterEach(() => {
      this.isRouterLoading = false
    })
  },
  beforeUnmount() {
    // Clean up router guards
    if (typeof this._removeBeforeEach === 'function') this._removeBeforeEach()
    if (typeof this._removeAfterEach === 'function') this._removeAfterEach()
  },
  methods: {
    isPrefixRoute(prefix: string): boolean {
      return this.$route.path.startsWith(prefix)
    },

    toggleHeader(headerId: number): void {
      // Vue 3 doesn't need $set - direct assignment works with reactivity
      this.openHeaders = {
        ...this.openHeaders,
        [headerId]: !this.openHeaders[headerId],
      }
    },

    isHeaderOpen(headerId: number): boolean {
      return this.openHeaders[headerId] !== false
    },
  },
})
</script>

<style scoped lang="scss">
#forum-main {
  height: 100vh;
  overflow: auto;
}

#forum-content {
  flex-basis: 100%;
  flex: 1;
  display: flex;
  flex-direction: column;
  max-width: calc(100% - 128px);
  margin: 0 auto;
}

.page-header {
  padding: 1rem;
  padding-bottom: 0.5rem;

  h2 {
    margin: 0 0 6px 0;
  }

  .muted {
    color: var(--color-text-maxcontrast);
    opacity: 0.7;
  }
}

#forum-router {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
}

.router-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
}
</style>
