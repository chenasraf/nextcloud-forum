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
        <NcActions ref="actionsMenu">
          <NcActionButton @click="handleReply">
            <template #icon>
              <ReplyIcon :size="20" />
            </template>
            {{ strings.reply }}
          </NcActionButton>
          <NcActionButton v-if="canEdit" @click="handleEditClick">
            <template #icon>
              <PencilIcon :size="20" />
            </template>
            {{ strings.edit }}
          </NcActionButton>
          <NcActionButton v-if="canDelete" @click="handleDelete">
            <template #icon>
              <DeleteIcon :size="20" />
            </template>
            {{ strings.delete }}
          </NcActionButton>
        </NcActions>
      </div>
    </div>

    <div class="post-content">
      <!-- Edit mode -->
      <PostEditForm v-if="isEditing" ref="editForm" :initial-content="post.contentRaw"
        @submit="handleEditSubmit" @cancel="cancelEdit" />

      <!-- View mode -->
      <div v-else class="content-text" v-html="formattedContent"></div>
    </div>

    <!-- Reactions (hidden when editing) -->
    <PostReactions v-if="!isEditing" :post-id="post.id" :reactions="post.reactions || []"
      @update="handleReactionsUpdate" />
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import ReplyIcon from '@icons/Reply.vue'
import PencilIcon from '@icons/Pencil.vue'
import DeleteIcon from '@icons/Delete.vue'
import PostReactions from './PostReactions.vue'
import PostEditForm from './PostEditForm.vue'
import { t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import type { Post } from '@/types'
import type { ReactionGroup } from '@/composables/useReactions'

export default defineComponent({
  name: 'PostCard',
  components: {
    NcAvatar,
    NcDateTime,
    NcActions,
    NcActionButton,
    ReplyIcon,
    PencilIcon,
    DeleteIcon,
    PostReactions,
    PostEditForm,
  },
  props: {
    post: {
      type: Object as PropType<Post>,
      required: true,
    },
    isFirstPost: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['reply', 'edit', 'delete', 'update'],
  data() {
    return {
      isEditing: false,
      strings: {
        edited: t('forum', 'Edited'),
        reply: t('forum', 'Reply'),
        edit: t('forum', 'Edit'),
        delete: t('forum', 'Delete'),
        confirmDelete: t('forum', 'Are you sure you want to delete this post? This action cannot be undone.'),
      },
    }
  },
  computed: {
    currentUser() {
      return getCurrentUser()
    },
    canEdit(): boolean {
      return this.currentUser !== null && this.currentUser.uid === this.post.authorId
    },
    canDelete(): boolean {
      // For now, only author can delete. Later add admin/moderator check
      return this.currentUser !== null && this.currentUser.uid === this.post.authorId
    },
    formattedContent(): string {
      // Content is already parsed by BBCodeService on the backend
      // BBCodeService handles HTML escaping before parsing BBCodes
      return this.post.content
    },
  },
  methods: {
    closeActionsMenu() {
      const menu = this.$refs.actionsMenu as any
      if (menu && typeof menu.closeMenu === 'function') {
        menu.closeMenu()
      }
    },

    handleReply() {
      this.closeActionsMenu()
      this.$emit('reply', this.post)
    },

    handleEditClick() {
      this.closeActionsMenu()
      this.startEdit()
    },

    handleDelete() {
      this.closeActionsMenu()

      // Confirm deletion
      // eslint-disable-next-line no-alert
      if (!confirm(this.strings.confirmDelete)) {
        return
      }

      this.$emit('delete', this.post)
    },

    handleReactionsUpdate(reactions: ReactionGroup[]) {
      // Update the post's reactions locally
      if (this.post.reactions !== undefined) {
        this.post.reactions = reactions
      }
    },

    startEdit() {
      this.isEditing = true
      // Focus the edit form after it mounts
      this.$nextTick(() => {
        const editForm = this.$refs.editForm as any
        if (editForm && typeof editForm.focus === 'function') {
          editForm.focus()
        }
      })
    },

    handleEditSubmit(content: string) {
      // Emit event to parent with post and new content
      this.$emit('update', { post: this.post, content })
    },

    cancelEdit() {
      this.isEditing = false
    },

    finishEdit() {
      // Called by parent when edit is successfully saved
      this.isEditing = false
    },

    setEditSubmitting(value: boolean) {
      // Update the submitting state of the edit form
      const editForm = this.$refs.editForm as any
      if (editForm && typeof editForm.setSubmitting === 'function') {
        editForm.setSubmitting(value)
      }
    },
  },
})
</script>

<style scoped lang="scss">
.post-card {
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 16px;
  background: var(--color-main-background);
  transition: box-shadow 0.2s ease;

  &.first-post {
    background: var(--color-background-hover);
    border-left: 4px solid var(--color-primary-element);
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

    :deep(em) {
      font-style: italic;
      color: inherit;
    }

    :deep(br) {
      line-height: 1.6;
    }

    // Code blocks ([code])
    :deep(pre) {
      background: var(--color-background-dark);
      border: 1px solid var(--color-border);
      border-radius: 6px;
      padding: 16px;
      margin: 12px 0;
      overflow-x: auto;

      code {
        background: none;
        padding: 0;
        border: none;
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.9rem;
        line-height: 1.5;
        color: var(--color-main-text);
        white-space: pre;
        display: block;
      }
    }

    // Inline code ([icode])
    :deep(code) {
      background: var(--color-background-dark);
      padding: 2px 6px;
      border-radius: 3px;
      font-family: 'Courier New', Courier, monospace;
      font-size: 0.9rem;
      color: var(--color-main-text);
    }
  }

  .icon {
    font-size: 1rem;
  }
}
</style>
