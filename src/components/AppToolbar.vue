<template>
  <div class="app-toolbar">
    <div class="toolbar-left">
      <slot name="left" />
    </div>

    <div class="toolbar-right">
      <slot name="right" />
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'AppToolbar',
})
</script>

<style scoped lang="scss">
.app-toolbar {
  margin-top: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;

  .toolbar-left,
  .toolbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }

  .toolbar-left {
    flex: 1;
    min-width: 200px;
  }

  .toolbar-right {
    flex-shrink: 0;
  }
}
</style>
