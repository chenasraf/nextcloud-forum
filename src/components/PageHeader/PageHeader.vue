<template>
  <div class="page-header">
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

  .mt-8 {
    margin-top: 8px;
  }
}

.page-title {
  margin: 0 0 8px 0;
  font-size: 1.75rem;
  font-weight: 600;
  color: var(--color-main-text);
}

.page-subtitle {
  margin: 0;
  font-size: 1rem;
  color: var(--color-text-lighter);
  line-height: 1.5;
}
</style>
