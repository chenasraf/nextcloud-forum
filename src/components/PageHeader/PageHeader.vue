<template>
  <div class="page-header" :class="{ colored: !!color }" :style="headerStyle">
    <template v-if="loading">
      <Skeleton width="200px" height="1lh" radius="6px" />
      <Skeleton width="350px" height="1lh" radius="4px" class="mt-8" />
    </template>
    <template v-else>
      <h2 class="page-title">{{ title }}</h2>
      <p v-if="subtitle" class="page-subtitle">{{ subtitle }}</p>
    </template>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import Skeleton from '@/components/Skeleton'

export default defineComponent({
  name: 'PageHeader',
  components: {
    Skeleton,
  },
  props: {
    /**
     * The main title/heading
     */
    title: {
      type: String,
      default: '',
    },
    /**
     * Optional subtitle/description
     */
    subtitle: {
      type: String,
      default: '',
    },
    /**
     * Show loading skeleton
     */
    loading: {
      type: Boolean,
      default: false,
    },
    /**
     * Background color (hex)
     */
    color: {
      type: String,
      default: null,
    },
    /**
     * Text color mode: 'light' or 'dark'
     */
    textColor: {
      type: String as () => 'light' | 'dark' | null,
      default: null,
    },
  },
  computed: {
    headerStyle(): Record<string, string> {
      if (!this.color) return {}
      const textMain = this.textColor === 'light' ? '#ffffff' : '#1a1a1a'
      const textMuted = this.textColor === 'light' ? 'rgba(255,255,255,0.7)' : 'rgba(0,0,0,0.55)'
      return {
        '--header-bg': this.color,
        '--header-border': this.color,
        '--header-text': textMain,
        '--header-text-muted': textMuted,
      }
    },
  },
})
</script>

<style scoped lang="scss">
.page-header {
  padding: 20px;
  background: var(--color-background-hover);
  border-radius: 8px;
  border: 1px solid var(--color-border);
  margin-bottom: 16px;

  &.colored {
    background: var(--header-bg);
    border-color: var(--header-border);
  }
}

.page-title {
  margin: 0 0 8px 0;
  font-size: 1.75rem;
  font-weight: 600;
  color: var(--color-main-text);

  .colored & {
    color: var(--header-text);
  }
}

.page-subtitle {
  margin: 0;
  font-size: 1rem;
  color: var(--color-text-lighter);
  line-height: 1.5;

  .colored & {
    color: var(--header-text-muted);
  }
}
</style>
