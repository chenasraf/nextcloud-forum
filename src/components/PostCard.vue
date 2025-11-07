<template>
  <div class="post-card" :class="{ 'first-post': isFirstPost }">
    <div class="post-header">
      <div class="author-info">
        <NcAvatar v-if="!post.authorIsDeleted" :user="post.authorId" :size="32" />
        <NcAvatar v-else :display-name="post.authorDisplayName" :size="32" />
        <div class="author-details">
          <span class="author-name" :class="{ 'deleted-user': post.authorIsDeleted }">
            {{ post.authorDisplayName || post.authorId }}
          </span>
          <div class="post-meta">
            <NcDateTime v-if="post.createdAt" :timestamp="post.createdAt * 1000" />
            <span v-if="post.isEdited" class="edited-badge">
              <span class="edited-label">{{ strings.edited }}</span>
              <NcDateTime v-if="post.editedAt" :timestamp="post.editedAt * 1000" />
            </span>
          </div>
        </div>
      </div>
      <div class="post-actions">
        <NcActions>
          <NcActionButton @click="$emit('reply', post)">
            <template #icon>
              <ReplyIcon :size="20" />
            </template>
            {{ strings.reply }}
          </NcActionButton>
          <NcActionButton v-if="canEdit" @click="$emit('edit', post)">
            <template #icon>
              <PencilIcon :size="20" />
            </template>
            {{ strings.edit }}
          </NcActionButton>
          <NcActionButton v-if="canDelete" @click="$emit('delete', post)">
            <template #icon>
              <DeleteIcon :size="20" />
            </template>
            {{ strings.delete }}
          </NcActionButton>
        </NcActions>
      </div>
    </div>

    <div class="post-content">
      <div class="content-text" v-html="formattedContent"></div>
    </div>
  </div>
</template>

<script>
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import ReplyIcon from '@icons/Reply.vue'
import PencilIcon from '@icons/Pencil.vue'
import DeleteIcon from '@icons/Delete.vue'
import { t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'

export default {
  name: 'PostCard',
  components: {
    NcAvatar,
    NcDateTime,
    NcActions,
    NcActionButton,
    ReplyIcon,
    PencilIcon,
    DeleteIcon,
  },
  props: {
    post: {
      type: Object,
      required: true,
    },
    isFirstPost: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['reply', 'edit', 'delete'],
  data() {
    return {
      strings: {
        edited: t('forum', 'Edited'),
        reply: t('forum', 'Reply'),
        edit: t('forum', 'Edit'),
        delete: t('forum', 'Delete'),
      },
    }
  },
  computed: {
    currentUser() {
      return getCurrentUser()
    },
    canEdit() {
      return this.currentUser && this.currentUser.uid === this.post.authorId
    },
    canDelete() {
      // For now, only author can delete. Later add admin/moderator check
      return this.currentUser && this.currentUser.uid === this.post.authorId
    },
    formattedContent() {
      // Content is already parsed by BBCodeService on the backend
      // BBCodeService handles HTML escaping before parsing BBCodes
      return this.post.content
    },
  },
  methods: {
  },
}
</script>

<style scoped lang="scss">
.post-card {
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 16px;
  background: var(--color-main-background);
  transition: box-shadow 0.2s ease;
  cursor: pointer;

  * {
    cursor: inherit;
  }

  &.first-post {
    background: var(--color-background-hover);
    border-left: 3px solid var(--color-primary-element);
  }

  &:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }

  .post-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    gap: 12px;
  }

  .author-info {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    flex: 1;
  }

  .author-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .author-name {
    font-weight: 600;
    color: var(--color-main-text);
    font-size: 1rem;

    &.deleted-user {
      font-style: italic;
      opacity: 0.7;
    }
  }

  .post-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
    flex-wrap: wrap;
  }

  .edited-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    background: var(--color-background-dark);
    border-radius: 4px;
    font-size: 0.75rem;
  }

  .edited-label {
    font-style: italic;
    opacity: 0.8;
  }

  .post-actions {
    flex-shrink: 0;
  }

  .post-content {
    margin-top: 12px;
  }

  .content-text {
    color: var(--color-main-text);
    line-height: 1.6;
    font-size: 0.95rem;
    word-wrap: break-word;
    overflow-wrap: break-word;

    :deep(br) {
      line-height: 1.6;
    }
  }

  .icon {
    font-size: 1rem;
  }
}
</style>
