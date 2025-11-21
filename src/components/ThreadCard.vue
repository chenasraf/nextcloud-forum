<template>
  <div
    class="thread-card"
    :class="{ pinned: thread.isPinned, locked: thread.isLocked, unread: isUnread }"
  >
    <div class="thread-main">
      <div class="thread-header">
        <div class="thread-title-row">
          <span v-if="isUnread" class="unread-indicator" :title="strings.unread"></span>
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
          <UserInfo
            :user-id="thread.author?.userId || thread.authorId"
            :display-name="thread.author?.displayName || thread.authorId"
            :is-deleted="thread.author?.isDeleted || false"
            :avatar-size="32"
            :roles="thread.author?.roles || []"
            :show-roles="false"
            layout="inline"
            @click.stop
          >
            <template #meta>
              <NcDateTime v-if="thread.createdAt" :timestamp="thread.createdAt * 1000" />
            </template>
          </UserInfo>
        </div>
      </div>

      <div class="thread-stats">
        <div class="stat">
          <span class="stat-icon">
            <CommentIcon :size="20" />
          </span>
          <span class="stat-value">{{ (thread.postCount || 1) - 1 }}</span>
          <span class="stat-label">{{ strings.replies((thread.postCount || 1) - 1) }}</span>
        </div>
        <div class="stat">
          <span class="stat-icon">
            <EyeIcon :size="20" />
          </span>
          <span class="stat-value">{{ thread.viewCount || 0 }}</span>
          <span class="stat-label">{{ strings.views(thread.viewCount || 0) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import UserInfo from '@/components/UserInfo.vue'
import PinIcon from '@icons/Pin.vue'
import LockIcon from '@icons/Lock.vue'
import CommentIcon from '@icons/Comment.vue'
import EyeIcon from '@icons/Eye.vue'
import { t, n } from '@nextcloud/l10n'
import type { Thread } from '@/types'

export default defineComponent({
  name: 'ThreadCard',
  components: {
    NcDateTime,
    UserInfo,
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
    isUnread: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      strings: {
        replies: (count: number) => n('forum', 'Reply', 'Replies', count),
        views: (count: number) => n('forum', 'View', 'Views', count),
        pinned: t('forum', 'Pinned thread'),
        locked: t('forum', 'Locked thread'),
        unread: t('forum', 'Unread'),
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

  &:hover,
  &.unread:hover,
  &.pinned:hover {
    border-color: var(--color-primary-element);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }

  &.pinned {
    background: var(--color-background-hover);
    border-color: var(--color-primary-element-light);
  }

  &.locked {
    opacity: 0.85;
  }

  &.unread {
    border-left: 4px solid var(--color-primary-element);
    background: var(--color-primary-element-light-hover);
  }

  .thread-main {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;

    @media (max-width: 768px) {
      align-items: flex-start;
      gap: 6px;
    }
  }

  .unread-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: var(--color-primary-element);
    border-radius: 50%;
    flex-shrink: 0;
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

    @media (max-width: 768px) {
      flex-direction: row;
      padding: 6px 8px;
      gap: 6px;
    }
  }

  .stat-icon {
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;

    @media (max-width: 768px) {
      :deep(svg) {
        width: 20px;
        height: 20px;
      }
    }
  }

  .stat-value {
    font-weight: 600;
    font-size: 1rem;
    color: var(--color-main-text);

    @media (max-width: 768px) {
      font-size: 0.9rem;
    }
  }

  .stat-label {
    font-size: 0.7rem;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
    letter-spacing: 0.5px;

    @media (max-width: 768px) {
      font-size: 0.65rem;
    }
  }
}

@media (max-width: 768px) {
  .thread-card .thread-main {
    flex-direction: column;
  }

  .thread-stats {
    flex-direction: row;
    flex-wrap: wrap;
    width: 100%;
    justify-content: flex-start;
    gap: 8px;
  }
}
</style>
