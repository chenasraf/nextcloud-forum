<template>
  <NcAppNavigation>
    <template #list>
      <NcAppNavigationItem :name="strings.navHome" :to="{ path: '/' }" :open="true">
        <template #icon>
          <HomeIcon :size="20" />
        </template>

        <!-- Search menu item -->
        <NcAppNavigationItem
          :name="strings.navSearch"
          :to="{ path: '/search' }"
          :active="isPathActive('/search')"
        >
          <template #icon>
            <MagnifyIcon :size="20" />
          </template>
        </NcAppNavigationItem>

        <!-- Bookmarks menu item (authenticated users only) -->
        <NcAppNavigationItem
          v-if="userId !== null"
          :name="strings.navBookmarks"
          :to="{ path: '/bookmarks' }"
          :active="isPathActive('/bookmarks')"
        >
          <template #icon>
            <BookmarkIcon :size="20" />
          </template>
        </NcAppNavigationItem>

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
            <NcActionButton
              :aria-label="isHeaderOpen(header.id) ? strings.collapse : strings.expand"
              :title="isHeaderOpen(header.id) ? strings.collapse : strings.expand"
            >
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

        <!-- Preferences menu item (authenticated users only) -->
        <NcAppNavigationItem
          v-if="userId !== null"
          :name="strings.navPreferences"
          :to="{ path: '/preferences' }"
          :active="isPathActive('/preferences')"
        >
          <template #icon>
            <AccountCogIcon :size="20" />
          </template>
        </NcAppNavigationItem>
      </NcAppNavigationItem>

      <!-- Admin menu item - only visible to admins -->
      <NcAppNavigationItem v-if="isAdmin" :name="strings.navAdmin" @click="toggleAdmin">
        <template #icon>
          <ShieldCheckIcon :size="20" />
        </template>

        <template #actions>
          <NcActionButton
            :aria-label="isAdminOpen ? strings.collapse : strings.expand"
            :title="isAdminOpen ? strings.collapse : strings.expand"
          >
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
            :active="isPathActive('/admin')"
          >
            <template #icon>
              <ChartLineIcon :size="20" />
            </template>
          </NcAppNavigationItem>

          <NcAppNavigationItem
            :name="strings.navAdminSettings"
            :to="{ path: '/admin/settings' }"
            :active="isPathActive('/admin/settings')"
          >
            <template #icon>
              <CogIcon :size="20" />
            </template>
          </NcAppNavigationItem>

          <NcAppNavigationItem
            :name="strings.navAdminUsers"
            :to="{ path: '/admin/users' }"
            :active="isPathActive('/admin/users', true)"
          >
            <template #icon>
              <AccountMultipleIcon :size="20" />
            </template>
          </NcAppNavigationItem>

          <NcAppNavigationItem
            :name="strings.navAdminRoles"
            :to="{ path: '/admin/roles' }"
            :active="isPathActive('/admin/roles', true)"
          >
            <template #icon>
              <ShieldAccountIcon :size="20" />
            </template>
          </NcAppNavigationItem>

          <NcAppNavigationItem
            :name="strings.navAdminCategories"
            :to="{ path: '/admin/categories' }"
            :active="isPathActive('/admin/categories', true)"
          >
            <template #icon>
              <FolderIcon :size="20" />
            </template>
          </NcAppNavigationItem>

          <NcAppNavigationItem
            :name="strings.navAdminBBCodes"
            :to="{ path: '/admin/bbcodes' }"
            :active="isPathActive('/admin/bbcodes', true)"
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
        <UserInfo :user-id="userId" :display-name="displayName" :avatar-size="32" />
      </div>
    </template>
  </NcAppNavigation>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { t } from '@nextcloud/l10n'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationSearch from '@nextcloud/vue/components/NcAppNavigationSearch'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import UserInfo from '@/components/UserInfo.vue'
import HomeIcon from '@icons/Home.vue'
import ForumIcon from '@icons/Forum.vue'
import FolderIcon from '@icons/Folder.vue'
import MagnifyIcon from '@icons/Magnify.vue'
import BookmarkIcon from '@icons/Bookmark.vue'
import ChevronDownIcon from '@icons/ChevronDown.vue'
import ChevronRightIcon from '@icons/ChevronRight.vue'
import ShieldCheckIcon from '@icons/ShieldCheck.vue'
import ShieldAccountIcon from '@icons/ShieldAccount.vue'
import ChartLineIcon from '@icons/ChartLine.vue'
import AccountMultipleIcon from '@icons/AccountMultiple.vue'
import CodeBracketsIcon from '@icons/CodeBrackets.vue'
import CogIcon from '@icons/Cog.vue'
import AccountCogIcon from '@icons/AccountCog.vue'
import { useCategories } from '@/composables/useCategories'
import { useCurrentUser } from '@/composables/useCurrentUser'
import { useUserRole } from '@/composables/useUserRole'
import { useCurrentThread } from '@/composables/useCurrentThread'
import type { Category } from '@/types'

export default defineComponent({
  name: 'AppNavigation',
  components: {
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppNavigationSearch,
    NcActionButton,
    UserInfo,
    HomeIcon,
    ForumIcon,
    FolderIcon,
    MagnifyIcon,
    BookmarkIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ShieldCheckIcon,
    ShieldAccountIcon,
    ChartLineIcon,
    AccountMultipleIcon,
    CodeBracketsIcon,
    CogIcon,
    AccountCogIcon,
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
  data() {
    return {
      searchValue: '',
      openHeaders: {} as Record<number, boolean>,
      isAdminOpen: true,
      STORAGE_KEY: 'forum_navigation_state',
      strings: {
        searchLabel: t('forum', 'Search'),
        navHome: t('forum', 'Home'),
        navSearch: t('forum', 'Search'),
        navBookmarks: t('forum', 'Bookmarks'),
        navPreferences: t('forum', 'User preferences'),
        navAdmin: t('forum', 'Admin'),
        navAdminDashboard: t('forum', 'Dashboard'),
        navAdminSettings: t('forum', 'Forum settings'),
        navAdminUsers: t('forum', 'Users'),
        navAdminRoles: t('forum', 'Roles'),
        navAdminCategories: t('forum', 'Categories'),
        navAdminBBCodes: t('forum', 'BBCodes'),
        expand: t('forum', 'Expand'),
        collapse: t('forum', 'Collapse'),
      },
    }
  },
  async created() {
    // Fetch categories for sidebar
    try {
      await this.fetchCategories()

      // Load saved state from local storage
      this.loadNavigationState()
    } catch (e) {
      console.error('Failed to load categories for sidebar:', e)
    }
  },
  methods: {
    loadNavigationState(): void {
      try {
        const savedState = localStorage.getItem(this.STORAGE_KEY)
        if (savedState) {
          const parsed = JSON.parse(savedState)

          // Load admin section state
          if (typeof parsed.isAdminOpen === 'boolean') {
            this.isAdminOpen = parsed.isAdminOpen
          }

          // Load category headers state
          if (parsed.openHeaders && typeof parsed.openHeaders === 'object') {
            this.openHeaders = parsed.openHeaders
          }
        }

        // Initialize headers that don't have saved state to open by default
        const openState: Record<number, boolean> = { ...this.openHeaders }
        this.categoryHeaders.forEach((header) => {
          if (openState[header.id] === undefined) {
            openState[header.id] = true
          }
        })
        this.openHeaders = openState
      } catch (e) {
        console.error('Failed to load navigation state from local storage:', e)

        // Fallback: Initialize all headers as open by default
        const openState: Record<number, boolean> = {}
        this.categoryHeaders.forEach((header) => {
          openState[header.id] = true
        })
        this.openHeaders = openState
      }
    },

    saveNavigationState(): void {
      try {
        const state = {
          isAdminOpen: this.isAdminOpen,
          openHeaders: this.openHeaders,
        }
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(state))
      } catch (e) {
        console.error('Failed to save navigation state to local storage:', e)
      }
    },

    isPathActive(path: string, usePrefix = false): boolean {
      if (usePrefix) {
        return this.$route.path.startsWith(path)
      }
      return this.$route.path === path
    },

    toggleHeader(headerId: number): void {
      this.openHeaders = {
        ...this.openHeaders,
        [headerId]: !this.openHeaders[headerId],
      }
      this.saveNavigationState()
    },

    isHeaderOpen(headerId: number): boolean {
      return this.openHeaders[headerId] !== false
    },

    toggleAdmin(): void {
      this.isAdminOpen = !this.isAdminOpen
      this.saveNavigationState()
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
.sidebar-footer {
  padding: 16px;
}
</style>
