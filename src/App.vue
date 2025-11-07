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
        <NcAppNavigationItem
          :name="strings.navHome"
          :to="{ path: '/' }"
          :active="$route.path === '/' || $route.path === ''"
        >
          <template #icon>
            <HomeIcon :size="20" />
          </template>
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

<script>
import { t } from '@nextcloud/l10n'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationSearch from '@nextcloud/vue/components/NcAppNavigationSearch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import HomeIcon from '@icons/Home.vue'
import PuzzleIcon from '@icons/Puzzle.vue'
import InfoIcon from '@icons/Information.vue'

export default {
  name: 'AppUserWrapper',
  components: {
    NcContent,
    NcAppContent,
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppNavigationSearch,
    NcLoadingIcon,
    HomeIcon,
    PuzzleIcon,
    InfoIcon,
  },
  // Tell NcContent we *do* have a sidebar so it arranges layout properly
  provide() {
    return { 'NcContent:setHasAppNavigation': () => true }
  },
  data() {
    return {
      searchValue: '',
      isRouterLoading: false,
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
      _removeBeforeEach: null,
      _removeAfterEach: null,
    }
  },
  created() {
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
    isPrefixRoute(prefix) {
      return this.$route.path.startsWith(prefix)
    },
  },
}
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
