<template>
  <div class="category-card">
    <div class="category-header">
      <h4 class="category-name">{{ category.name }}</h4>
      <div class="category-stats">
        <span class="stat">
          <span class="stat-value">{{ category.threadCount || 0 }}</span>
          <span class="stat-label">{{ strings.threads }}</span>
        </span>
        <span class="stat-divider">·</span>
        <span class="stat">
          <span class="stat-value">{{ category.postCount || 0 }}</span>
          <span class="stat-label">{{ strings.posts }}</span>
        </span>
      </div>
    </div>
    <p v-if="category.description" class="category-description">{{ category.description }}</p>
    <p v-else class="category-description muted">{{ strings.noDescription }}</p>
  </div>
</template>

<script>
import { t } from '@nextcloud/l10n'

export default {
  name: 'CategoryCard',
  props: {
    category: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      strings: {
        threads: t('forum', 'Threads'),
        posts: t('forum', 'Posts'),
        noDescription: t('forum', 'No description available'),
      },
    }
  },
}
</script>

<style scoped lang="scss">
.category-card {
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 16px;
  background: var(--color-main-background);
  transition: box-shadow 0.2s ease, border-color 0.2s ease;
  cursor: pointer;

  * {
    cursor: inherit;
  }

  &:hover {
    border-color: var(--color-primary-element);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
}
</style>
