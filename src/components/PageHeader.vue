<template>
  <div class="page-header">
    <h2 class="page-title">{{ title }}</h2>
    <p v-if="subtitle" class="page-subtitle">{{ subtitle }}</p>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'PageHeader',
  props: {
    /**
     * The main title/heading
     */
    title: {
      type: String,
      required: true,
    },
    /**
     * Optional subtitle/description
     */
    subtitle: {
      type: String,
      default: '',
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
