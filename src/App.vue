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
            @click="toggleHeader(header.id)"
          >
            <template #icon>
              <FolderIcon :size="20" />
            </template>

            <template #actions>
              <NcActionButton>
                <template #icon>
                  <ChevronDownIcon v-if="isHeaderOpen(header.id)" :size="20" />
                  <ChevronRightIcon v-else :size="20" />
                </template>
              </NcActionButton>
            </template>

            <!-- Categories under each header -->
            <template v-if="isHeaderOpen(header.id)">
              <NcAppNavigationItem
                v-for="category in header.categories"
                :key="`category-${category.id}`"
                :name="category.name"
                :to="{ path: `/c/${category.slug}` }"
                :active="isCategoryActive(category)"
              >
                <template #icon>
                  <ForumIcon :size="20" />
                </template>
              </NcAppNavigationItem>
            </template>
          </NcAppNavigationItem>
        </NcAppNavigationItem>

        <!-- Search menu item -->
        <NcAppNavigationItem
          :name="strings.navSearch"
          :to="{ path: '/search' }"
          :active="isSearchActive"
        >
          <template #icon>
            <MagnifyIcon :size="20" />
          </template>
        </NcAppNavigationItem>

        <!-- Admin menu item - only visible to admins -->
        <NcAppNavigationItem v-if="isAdmin" :name="strings.navAdmin" @click="toggleAdmin">
          <template #icon>
            <ShieldCheckIcon :size="20" />
          </template>

          <template #actions>
            <NcActionButton>
              <template #icon>
                <ChevronDownIcon v-if="isAdminOpen" :size="20" />
                <ChevronRightIcon v-else :size="20" />
              </template>
            </NcActionButton>
          </template>

          <!-- Admin sub-items -->
          <template v-if="isAdminOpen">
            <NcAppNavigationItem
              :name="strings.navAdminDashboard"
              :to="{ path: '/admin' }"
              :active="isAdminDashboardActive"
            >
              <template #icon>
                <ChartLineIcon :size="20" />
              </template>
            </NcAppNavigationItem>

            <NcAppNavigationItem
              :name="strings.navAdminUsers"
              :to="{ path: '/admin/users' }"
              :active="isAdminUsersActive"
            >
              <template #icon>
                <AccountMultipleIcon :size="20" />
              </template>
            </NcAppNavigationItem>

            <NcAppNavigationItem
              :name="strings.navAdminRoles"
              :to="{ path: '/admin/roles' }"
              :active="isAdminRolesActive"
            >
              <template #icon>
                <ShieldAccountIcon :size="20" />
              </template>
            </NcAppNavigationItem>

            <NcAppNavigationItem
              :name="strings.navAdminCategories"
              :to="{ path: '/admin/categories' }"
              :active="isAdminCategoriesActive"
            >
              <template #icon>
                <FolderIcon :size="20" />
              </template>
            </NcAppNavigationItem>

            <NcAppNavigationItem
              :name="strings.navAdminBBCodes"
              :to="{ path: '/admin/bbcodes' }"
              :active="isAdminBBCodesActive"
            >
              <template #icon>
                <CodeBracketsIcon :size="20" />
              </template>
            </NcAppNavigationItem>
          </template>
        </NcAppNavigationItem>
      </template>

      <template #footer>
        <div v-if="userId" class="sidebar-footer">
          <NcAvatar :user="userId" :size="32" />
          <span class="user-display-name">{{ displayName }}</span>
        </div>
      </template>
    </NcAppNavigation>

    <!-- Main content -->
    <NcAppContent id="forum-main">
      <div id="forum-content">
        <div id="forum-router">
          <div v-if="isRouterLoading" class="router-loading">
            <NcLoadingIcon :size="48" />
          </div>
          <router-view v-else />
          <div class="bottom-spacer"></div>
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
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import HomeIcon from '@icons/Home.vue'
import ForumIcon from '@icons/Forum.vue'
import FolderIcon from '@icons/Folder.vue'
import MagnifyIcon from '@icons/Magnify.vue'
import PuzzleIcon from '@icons/Puzzle.vue'
import InfoIcon from '@icons/Information.vue'
import ChevronDownIcon from '@icons/ChevronDown.vue'
import ChevronRightIcon from '@icons/ChevronRight.vue'
import ShieldCheckIcon from '@icons/ShieldCheck.vue'
import ShieldAccountIcon from '@icons/ShieldAccount.vue'
import ChartLineIcon from '@icons/ChartLine.vue'
import AccountMultipleIcon from '@icons/AccountMultiple.vue'
import CodeBracketsIcon from '@icons/CodeBrackets.vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import { useCategories } from '@/composables/useCategories'
import { useCurrentUser } from '@/composables/useCurrentUser'
import { useUserRole } from '@/composables/useUserRole'
import { useCurrentThread } from '@/composables/useCurrentThread'
import type { Category } from '@/types'

export default defineComponent({
  name: 'AppUserWrapper',
  components: {
    NcContent,
    NcAppContent,
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppNavigationSearch,
    NcLoadingIcon,
    NcActionButton,
    NcAvatar,
    HomeIcon,
    ForumIcon,
    FolderIcon,
    MagnifyIcon,
    PuzzleIcon,
    InfoIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ShieldCheckIcon,
    ShieldAccountIcon,
    ChartLineIcon,
    AccountMultipleIcon,
    CodeBracketsIcon,
  },
  setup() {
    const { categoryHeaders, fetchCategories } = useCategories()
    const { userId, displayName, fetchCurrentUser } = useCurrentUser()
    const { isAdmin, fetchUserRoles } = useUserRole()
    const { categoryId: currentThreadCategoryId, fetchThread, clearThread } = useCurrentThread()

    // Fetch current user and their roles on mount
    fetchCurrentUser().then((user) => {
      if (user) {
        fetchUserRoles(user.userId)
      }
    })

    return {
      categoryHeaders,
      fetchCategories,
      userId,
      displayName,
      isAdmin,
      currentThreadCategoryId,
      fetchThread,
      clearThread,
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
      isAdminOpen: true, // Track admin menu state
      // Mount path for this app section; adjust to your mount.
      basePath: '/apps/forum',
      strings: {
        searchLabel: t('forum', 'Search'),
        searchPlaceholder: t('forum', 'Type to filter…'),
        navHome: t('forum', 'Home'),
        navSearch: t('forum', 'Search'),
        navAdmin: t('forum', 'Admin'),
        navAdminDashboard: t('forum', 'Dashboard'),
        navAdminUsers: t('forum', 'Users'),
        navAdminRoles: t('forum', 'Roles'),
        navAdminCategories: t('forum', 'Categories'),
        navAdminBBCodes: t('forum', 'BBCodes'),
        navExamples: t('forum', 'Examples'),
        navAbout: t('forum', 'About'),
      },
      _removeBeforeEach: null as (() => void) | null,
      _removeAfterEach: null as (() => void) | null,
    }
  },
  computed: {
    isSearchActive(): boolean {
      return this.$route.path === '/search'
    },
    isAdminDashboardActive(): boolean {
      return this.$route.path === '/admin'
    },
    isAdminUsersActive(): boolean {
      return this.$route.path.startsWith('/admin/users')
    },
    isAdminRolesActive(): boolean {
      return this.$route.path.startsWith('/admin/roles')
    },
    isAdminCategoriesActive(): boolean {
      return this.$route.path.startsWith('/admin/categories')
    },
    isAdminBBCodesActive(): boolean {
      return this.$route.path.startsWith('/admin/bbcodes')
    },
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

    toggleAdmin(): void {
      this.isAdminOpen = !this.isAdminOpen
    },

    isCategoryActive(category: Category): boolean {
      // Check if we're on the category page itself
      if (
        this.$route.path === `/c/${category.slug}` ||
        this.$route.path === `/category/${category.id}`
      ) {
        return true
      }

      // Check if we're creating a thread in this category
      if (
        this.$route.path === `/c/${category.slug}/new` ||
        this.$route.path === `/category/${category.id}/new`
      ) {
        return true
      }

      // Check if we're viewing a thread that belongs to this category
      if (
        (this.$route.path.startsWith('/thread/') || this.$route.path.startsWith('/t/')) &&
        this.currentThreadCategoryId === category.id
      ) {
        return true
      }

      return false
    },

    async updateThreadCategory(): Promise<void> {
      // Reset when not on a thread page
      if (!this.$route.path.startsWith('/thread/') && !this.$route.path.startsWith('/t/')) {
        this.clearThread()
        return
      }

      // Fetch thread data to get category using the composable
      // Don't increment view count when fetching for sidebar navigation
      if (this.$route.params.slug) {
        await this.fetchThread(this.$route.params.slug as string, true, false)
      } else if (this.$route.params.id) {
        await this.fetchThread(this.$route.params.id as string, false, false)
      }
    },
  },
  watch: {
    $route: {
      handler() {
        this.updateThreadCategory()
      },
      immediate: true,
    },
  },
})
</script>

<style scoped lang="scss">
#forum-main {
  overflow: auto;
}

#forum-content {
  min-height: 100%;
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
  padding: 1rem;
  min-height: 0;
}

.bottom-spacer {
  height: 3rem;
  flex-shrink: 0;
}

.router-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
}

.sidebar-footer {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;

  .user-display-name {
    font-weight: 500;
    color: var(--color-main-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
