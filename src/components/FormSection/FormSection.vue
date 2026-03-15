<template>
  <div class="form-section">
    <h3>{{ title }}</h3>
    <p v-if="subtitle" class="muted">{{ subtitle }}</p>
    <slot />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'FormSection',
  props: {
    title: {
      type: String,
      required: true,
    },
    subtitle: {
      type: String,
      default: '',
    },
  },
})
</script>

<style scoped lang="scss">
.form-section {
  padding: 24px;
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: 8px;

  h3 {
    margin: 0 0 8px 0;
    font-size: 1.1rem;
    font-weight: 600;
  }

  > p {
    margin: 0 0 20px 0;
    font-size: 0.9rem;
  }
}
</style>
