<template>
  <NcContent app-name="forum" :data-theme-dark="isDarkTheme">
    <!-- Left sidebar -->
    <AppNavigation />

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
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AppNavigation from '@/components/AppNavigation.vue'
import { isDarkTheme } from '@nextcloud/vue/functions/isDarkTheme'

export default defineComponent({
  name: 'AppUserWrapper',
  components: {
    NcContent,
    NcAppContent,
    NcLoadingIcon,
    AppNavigation,
  },
  // Tell NcContent we *do* have a sidebar so it arranges layout properly
  provide() {
    return { 'NcContent:setHasAppNavigation': () => true }
  },
  data() {
    return {
      isDarkTheme,
      isRouterLoading: false,
      _removeBeforeEach: null as (() => void) | null,
      _removeAfterEach: null as (() => void) | null,
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
})
</script>

<style scoped lang="scss">
#forum-main {
  overflow: auto;
  scroll-behavior: smooth;
}

#forum-content {
  min-height: 100%;
  display: flex;
  flex-direction: column;
  max-width: calc(100% - 128px);
  margin: 0 auto;
  scroll-behavior: smooth;
}

@media screen and (max-width: 768px) {
  #forum-content {
    max-width: 100%;
    padding: 0 1rem;
  }
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
  scroll-behavior: smooth;

  @media (max-width: 768px) {
    padding: 0;
  }
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
  margin-top: 128px;
}
</style>
