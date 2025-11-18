<template>
  <div class="page-wrapper" :class="{ 'full-width': fullWidth }">
    <slot />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'PageWrapper',
  props: {
    /**
     * Whether to use full width or fixed width (900px max with auto margins)
     */
    fullWidth: {
      type: Boolean,
      default: false,
    },
  },
})
</script>

<style scoped lang="scss">
.page-wrapper {
  padding: 16px;
  max-width: 900px;
  margin: 0 auto;

  &.full-width {
    max-width: none;
    margin: 0;
  }
}
</style>
