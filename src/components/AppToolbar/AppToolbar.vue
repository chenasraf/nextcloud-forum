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
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;

  .toolbar-left,
  .toolbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    max-width: 100%;
  }

  .toolbar-left {
    flex: 1 1 auto;
    min-width: 200px;

    @media (max-width: 768px) {
      padding-left: 32px;
    }
  }

  .toolbar-right {
    flex-shrink: 0;
    margin-left: auto;
    justify-content: flex-end;
  }
}
</style>
