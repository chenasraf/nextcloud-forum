<template>
  <div
    class="category-card"
    :class="{ unread: isUnread, colored: !!category.color }"
    :style="cardStyle"
    role="link"
    tabindex="0"
  >
    <div class="category-header">
      <span
        v-if="isUnread"
        class="unread-indicator"
        :title="strings.unread"
        :aria-label="strings.unread"
        role="img"
      ></span>
      <h4 class="category-name">{{ category.name }}</h4>
      <div class="category-stats">
        <span class="stat">
          <span class="stat-value">{{ category.threadCount || 0 }}</span>
          <span class="stat-label">{{ strings.threads(category.threadCount || 0) }}</span>
        </span>
        <span class="stat-divider">·</span>
        <span class="stat">
          <span class="stat-value">{{ category.postCount || 0 }}</span>
          <span class="stat-label">{{ strings.replies(category.postCount || 0) }}</span>
        </span>
      </div>
    </div>
    <p v-if="category.description" class="category-description">{{ category.description }}</p>
    <p v-else class="category-description muted">{{ strings.noDescription }}</p>
    <!-- Child category links -->
    <div v-if="!hideChildren && visibleChildren.length > 0" class="category-children">
      <router-link
        v-for="child in visibleChildren"
        :key="child.id"
        :to="`/c/${child.slug}`"
        class="child-link"
        @click.stop
      >
        {{ child.name }}
      </router-link>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { t, n } from '@nextcloud/l10n'
import type { Category } from '@/types'

export default defineComponent({
  name: 'CategoryCard',
  props: {
    category: {
      type: Object as PropType<Category>,
      required: true,
    },
    isUnread: {
      type: Boolean,
      default: false,
    },
    children: {
      type: Array as PropType<Category[]>,
      default: () => [],
    },
    hideChildren: {
      type: Boolean,
      default: false,
    },
  },
  computed: {
    cardStyle(): Record<string, string> {
      const style: Record<string, string> = {}
      if (this.category.color) {
        style['--card-bg'] = this.category.color
        style['--card-border'] = this.category.color
        style['--card-text'] = this.category.textColor === 'light' ? '#ffffff' : '#1a1a1a'
        style['--card-text-muted'] =
          this.category.textColor === 'light' ? 'rgba(255,255,255,0.7)' : 'rgba(0,0,0,0.55)'
      }
      return style
    },
    visibleChildren(): Category[] {
      return this.children
    },
  },
  data() {
    return {
      strings: {
        threads: (count: number) => t('forum', 'Threads'),
        replies: (count: number) => t('forum', 'Replies'),
        noDescription: t('forum', 'No description available'),
        unread: t('forum', 'New activity'),
      },
    }
  },
})
</script>

<style scoped lang="scss">
.category-card {
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 16px;
  background: var(--color-main-background);
  transition:
    box-shadow 0.2s ease,
    border-color 0.2s ease;
  cursor: pointer;

  * {
    cursor: inherit;
  }

  &.colored {
    background: var(--card-bg);
    border-color: var(--card-border);
    color: var(--card-text);

    .category-name,
    .stat-value {
      color: var(--card-text);
    }

    .category-description,
    .stat-label,
    .stat-divider {
      color: var(--card-text-muted);
    }

    .category-description.muted {
      color: var(--card-text-muted);
    }

    .child-link {
      background: rgba(255, 255, 255, 0.15);
      color: var(--card-text);
      border-color: rgba(255, 255, 255, 0.2);

      &:hover {
        background: rgba(255, 255, 255, 0.25);
      }
    }
  }

  &.unread:not(.colored) {
    border-left: 4px solid var(--color-primary-element);
    background: var(--color-primary-element-light-hover);
  }

  &.unread.colored {
    border-left: 4px solid var(--card-text);
  }

  &:hover {
    border-color: var(--color-primary-element);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }

  .unread-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: var(--color-primary-element);
    border-radius: 50%;
    flex-shrink: 0;
  }

  .category-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
    gap: 12px;
  }

  .category-name {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--color-main-text);
    flex: 1;
  }

  .category-stats {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    white-space: nowrap;
  }

  .stat {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
  }

  .stat-value {
    font-weight: 600;
    color: var(--color-main-text);
  }

  .stat-label {
    font-size: 0.75rem;
    color: var(--color-text-maxcontrast);
  }

  .stat-divider {
    color: var(--color-text-maxcontrast);
    opacity: 0.5;
  }

  .category-description {
    margin: 0;
    font-size: 0.9rem;
    color: var(--color-text-lighter);
    line-height: 1.4;

    &.muted {
      color: var(--color-text-maxcontrast);
      opacity: 0.7;
      font-style: italic;
    }
  }

  .category-children {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid var(--color-border);
  }

  .child-link {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    background: var(--color-background-dark);
    color: var(--color-main-text);
    border: 1px solid var(--color-border);
    cursor: pointer;
    transition:
      background 0.15s ease,
      border-color 0.15s ease;

    &:hover {
      background: var(--color-background-hover);
      border-color: var(--color-primary-element);
    }
  }
}
</style>
