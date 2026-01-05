<template>
  <div class="skeleton" :style="skeletonStyle"></div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'Skeleton',
  props: {
    width: {
      type: String,
      default: '100%',
    },
    height: {
      type: String,
      default: '20px',
    },
    shape: {
      type: String as () => 'circle' | 'square' | 'rounded-rect',
      default: 'rounded-rect',
      validator: (value: string) => ['circle', 'square', 'rounded-rect'].includes(value),
    },
    radius: {
      type: String,
      default: '4px',
    },
  },
  computed: {
    skeletonStyle() {
      const borderRadius = this.getBorderRadius()
      return {
        width: this.width,
        height: this.height,
        borderRadius,
      }
    },
  },
  methods: {
    getBorderRadius(): string {
      switch (this.shape) {
        case 'circle':
          return '50%'
        case 'square':
          return '0'
        case 'rounded-rect':
          return this.radius
        default:
          return this.radius
      }
    },
  },
})
</script>

<style scoped lang="scss">
@keyframes shimmer {
  0% {
    background-position: -200% 0;
  }
  100% {
    background-position: 200% 0;
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.skeleton {
  position: relative;
  overflow: hidden;
  background-color: rgba(0, 0, 0, 0.08);

  &::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
      90deg,
      transparent 0%,
      rgba(255, 255, 255, 0.3) 50%,
      transparent 100%
    );
    background-size: 200% 100%;
    animation: shimmer 2s infinite ease-in-out;
  }

  animation: fadeIn 0.3s ease-in;
}
</style>
