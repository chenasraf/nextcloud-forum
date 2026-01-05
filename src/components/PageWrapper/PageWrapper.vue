<template>
  <div class="page-wrapper-container">
    <!-- Toolbar slot - always full width -->
    <div v-if="$slots.toolbar" class="page-wrapper-toolbar">
      <slot name="toolbar" />
    </div>

    <!-- Content wrapper - respects fullWidth prop -->
    <div class="page-wrapper-content" :class="{ 'full-width': fullWidth }">
      <slot />
    </div>
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
.page-wrapper-container {
  display: flex;
  flex-direction: column;

  @media screen and (max-width: 768px) {
    padding: 0;
  }
}

.page-wrapper-toolbar {
  width: 100%;
  flex-shrink: 0;
}

.page-wrapper-content {
  padding: 16px;
  max-width: 900px;
  margin: 0 auto;
  width: 100%;

  &.full-width {
    max-width: none;
    margin: 0;
  }
}
</style>
