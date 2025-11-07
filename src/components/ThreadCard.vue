<template>
  <div class="thread-card" :class="{ pinned: thread.isPinned, locked: thread.isLocked }">
    <div class="thread-main">
      <div class="thread-header">
        <div class="thread-title-row">
          <h4 class="thread-title">
            <span v-if="thread.isPinned" class="badge badge-pinned" :title="strings.pinned">
              <PinIcon :size="16" />
            </span>
            <span v-if="thread.isLocked" class="badge badge-locked" :title="strings.locked">
              <LockIcon :size="16" />
            </span>
            {{ thread.title }}
          </h4>
        </div>
        <div class="thread-meta">
          <span class="meta-item">
            <span class="meta-label">{{ strings.by }}</span>
            <span class="meta-value" :class="{ 'deleted-user': thread.authorIsDeleted }">
              {{ thread.authorDisplayName || thread.authorId }}
            </span>
          </span>
          <span class="meta-divider">·</span>
          <span class="meta-item">
            <NcDateTime v-if="thread.createdAt" :timestamp="thread.createdAt * 1000" />
          </span>
        </div>
      </div>

      <div class="thread-stats">
        <div class="stat">
          <span class="stat-icon">
            <CommentIcon :size="20" />
          </span>
          <span class="stat-value">{{ thread.postCount || 0 }}</span>
          <span class="stat-label">{{ strings.posts }}</span>
        </div>
        <div class="stat">
          <span class="stat-icon">
            <EyeIcon :size="20" />
          </span>
          <span class="stat-value">{{ thread.viewCount || 0 }}</span>
          <span class="stat-label">{{ strings.views }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import PinIcon from '@icons/Pin.vue'
import LockIcon from '@icons/Lock.vue'
import CommentIcon from '@icons/Comment.vue'
import EyeIcon from '@icons/Eye.vue'
import { t } from '@nextcloud/l10n'
import type { Thread } from '@/types'

export default defineComponent({
  name: 'ThreadCard',
  components: {
    NcDateTime,
    PinIcon,
    LockIcon,
    CommentIcon,
    EyeIcon,
  },
  props: {
    thread: {
      type: Object as PropType<Thread>,
      required: true,
    },
  },
  data() {
    return {
      strings: {
        by: t('forum', 'by'),
        posts: t('forum', 'Posts'),
        views: t('forum', 'Views'),
        pinned: t('forum', 'Pinned thread'),
        locked: t('forum', 'Locked thread'),
      },
    }
  },
})
</script>

<style scoped lang="scss">
.thread-card {
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 16px;
  background: var(--color-main-background);
  transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.1s ease;
  cursor: pointer;

  * {
    cursor: inherit;
  }

  &:hover {
    border-color: var(--color-primary-element);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
  }

  &.pinned {
    background: var(--color-background-hover);
    border-color: var(--color-primary-element-light);
  }

  &.locked {
    opacity: 0.85;
  }

  .thread-main {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
  }

  .thread-header {
    flex: 1;
    min-width: 0;
  }

  .thread-title-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
  }

  .thread-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--color-main-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .badge {
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;

    &.badge-pinned {
      opacity: 0.9;
    }

    &.badge-locked {
      opacity: 0.8;
    }
  }

  .thread-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
    flex-wrap: wrap;
  }

  .meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .meta-label {
    font-style: italic;
  }

  .meta-value {
    font-weight: 500;
    color: var(--color-text-lighter);

    &.deleted-user {
      font-style: italic;
      opacity: 0.7;
    }
  }

  .meta-divider {
    opacity: 0.5;
  }

  .thread-stats {
    display: flex;
    /* flex-direction: column; */
    gap: 12px;
    min-width: 80px;
  }

  .stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: 8px;
    background: var(--color-background-hover);
    border-radius: 6px;
  }

  .stat-icon {
    font-size: 1.2rem;
  }

  .stat-value {
    font-weight: 600;
    font-size: 1rem;
    color: var(--color-main-text);
  }

  .stat-label {
    font-size: 0.7rem;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
}

@media (max-width: 768px) {
  .thread-card .thread-main {
    flex-direction: column;
  }

  .thread-stats {
    flex-direction: row;
    width: 100%;
    justify-content: flex-start;
  }
}
</style>
