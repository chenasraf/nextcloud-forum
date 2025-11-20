<template>
  <span class="role-badge" :class="densityClass" :style="badgeStyle">
    {{ role.name }}
  </span>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { isDarkTheme } from '@nextcloud/vue/functions/isDarkTheme'
import type { Role } from '@/types'

export default defineComponent({
  name: 'RoleBadge',
  props: {
    role: {
      type: Object as PropType<Role>,
      required: true,
    },
    density: {
      type: String as PropType<'normal' | 'compact'>,
      default: 'normal',
      validator: (value: string) => ['normal', 'compact'].includes(value),
    },
  },
  computed: {
    densityClass(): string {
      return `density-${this.density}`
    },

    backgroundColor(): string {
      const isDark = isDarkTheme
      const color = isDark ? this.role.colorDark : this.role.colorLight

      if (color) {
        return color
      }

      // Fallback colors for system roles
      const fallbackColors: Record<number, { light: string; dark: string }> = {
        1: { light: '#dc2626', dark: '#f87171' }, // Admin - red
        2: { light: '#2563eb', dark: '#60a5fa' }, // Moderator - blue
        3: { light: '#059669', dark: '#34d399' }, // User - emerald
      }

      const fallback = fallbackColors[this.role.id]
      if (fallback) {
        return isDark ? fallback.dark : fallback.light
      }

      // Default fallback
      return isDark ? '#ffffff' : '#000000'
    },

    textColor(): string {
      // Calculate luminance to determine if text should be black or white
      const color = this.backgroundColor
      const rgb = this.hexToRgb(color)

      if (!rgb) {
        return '#ffffff'
      }

      // Calculate relative luminance using WCAG formula
      const luminance = (0.299 * rgb.r + 0.587 * rgb.g + 0.114 * rgb.b) / 255

      // If luminance > 0.5, use dark text, otherwise use light text
      return luminance > 0.5 ? '#000000' : '#ffffff'
    },

    badgeStyle(): Record<string, string> {
      return {
        backgroundColor: this.backgroundColor,
        color: this.textColor,
      }
    },
  },
  methods: {
    hexToRgb(hex: string): { r: number; g: number; b: number } | null {
      // Remove # if present
      hex = hex.replace(/^#/, '')

      // Parse hex to RGB
      if (hex.length === 3) {
        // Convert shorthand (e.g., #fff) to full form
        hex = hex
          .split('')
          .map((char) => char + char)
          .join('')
      }

      const bigint = parseInt(hex, 16)
      return {
        r: (bigint >> 16) & 255,
        g: (bigint >> 8) & 255,
        b: bigint & 255,
      }
    },
  },
})
</script>

<style scoped lang="scss">
.role-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 0.85rem;
  font-weight: 600;
  white-space: nowrap;
  line-height: 1.4;

  &.density-compact {
    padding: 2px 8px;
    border-radius: 8px;
    font-size: 0.75rem;
    line-height: 1.3;
  }
}
</style>
