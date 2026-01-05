<template>
  <div class="post-card" :class="{ 'first-post': isFirstPost, unread: isUnread }">
    <div class="post-header">
      <div class="author-info">
        <span v-if="isUnread" class="unread-indicator" :title="strings.unread"></span>
        <UserInfo
          :user-id="post.author?.userId || post.authorId"
          :display-name="post.author?.displayName || post.authorId"
          :is-deleted="post.author?.isDeleted || false"
          :avatar-size="32"
          :roles="post.author?.roles || []"
        >
          <template #meta>
            <div class="post-meta">
              <NcDateTime v-if="post.createdAt" :timestamp="post.createdAt * 1000" />
              <span v-if="post.isEdited" class="edited-badge">
                <span class="edited-label">{{ strings.edited }}</span>
                <NcDateTime v-if="post.editedAt" :timestamp="post.editedAt * 1000" />
              </span>
            </div>
          </template>
        </UserInfo>
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
          <NcActionButton v-if="post.isEdited" @click="handleViewHistory">
            <template #icon>
              <HistoryIcon :size="20" />
            </template>
            {{ strings.viewHistory }}
          </NcActionButton>
        </NcActions>
      </div>
    </div>

    <div class="post-content">
      <!-- Edit mode -->
      <PostEditForm
        v-if="isEditing"
        ref="editForm"
        :initial-content="post.contentRaw"
        @submit="handleEditSubmit"
        @cancel="cancelEdit"
      />

      <!-- View mode -->
      <div v-else class="content-text" v-html="formattedContent"></div>
    </div>

    <!-- Signature -->
    <div v-if="hasSignature && !isEditing" class="post-signature">
      <div class="signature-content" v-html="post.author?.signature"></div>
    </div>

    <!-- Reactions (hidden when editing) -->
    <PostReactions
      v-if="!isEditing"
      :post-id="post.id"
      :reactions="post.reactions || []"
      @update="handleReactionsUpdate"
    />

    <!-- Edit History Dialog -->
    <PostHistoryDialog
      :open="showHistoryDialog"
      :post-id="post.id"
      @update:open="showHistoryDialog = $event"
    />
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import ReplyIcon from '@icons/Reply.vue'
import PencilIcon from '@icons/Pencil.vue'
import DeleteIcon from '@icons/Delete.vue'
import HistoryIcon from '@icons/History.vue'
import UserInfo from '@/components/UserInfo'
import PostReactions from '@/components/PostReactions'
import PostEditForm from '@/components/PostEditForm'
import PostHistoryDialog from '@/components/PostHistoryDialog'
import { t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import { useUserRole } from '@/composables/useUserRole'
import type { Post } from '@/types'
import type { ReactionGroup } from '@/composables/useReactions'

export default defineComponent({
  name: 'PostCard',
  components: {
    NcDateTime,
    NcActions,
    NcActionButton,
    ReplyIcon,
    PencilIcon,
    DeleteIcon,
    HistoryIcon,
    UserInfo,
    PostReactions,
    PostEditForm,
    PostHistoryDialog,
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
    isUnread: {
      type: Boolean,
      default: false,
    },
    canModerateCategory: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['reply', 'edit', 'delete', 'update'],
  setup() {
    const { isAdmin, isModerator } = useUserRole()

    return {
      isAdmin,
      isModerator,
    }
  },
  data() {
    return {
      isEditing: false,
      showHistoryDialog: false,
      strings: {
        edited: t('forum', 'Edited'),
        reply: t('forum', 'Quote reply'),
        edit: t('forum', 'Edit'),
        delete: t('forum', 'Delete'),
        viewHistory: t('forum', 'View edit history'),
        confirmDelete: t(
          'forum',
          'Are you sure you want to delete this post? This action cannot be undone.',
        ),
        unread: t('forum', 'Unread'),
      },
    }
  },
  computed: {
    currentUser() {
      return getCurrentUser()
    },
    canEdit(): boolean {
      // Authors can edit their own posts
      // Admins and global moderators can edit any post
      // Category moderators can edit posts in their moderated categories
      if (!this.currentUser) {
        return false
      }
      return (
        this.currentUser.uid === this.post.authorId ||
        this.isAdmin ||
        this.isModerator ||
        this.canModerateCategory
      )
    },
    canDelete(): boolean {
      // Authors can delete their own posts
      // Admins and global moderators can delete any post
      // Category moderators can delete posts in their moderated categories
      if (!this.currentUser) {
        return false
      }
      return (
        this.currentUser.uid === this.post.authorId ||
        this.isAdmin ||
        this.isModerator ||
        this.canModerateCategory
      )
    },
    formattedContent(): string {
      // Content is already parsed by BBCodeService on the backend
      // BBCodeService handles HTML escaping before parsing BBCodes
      return this.post.content
    },
    hasSignature(): boolean {
      return !!this.post.author?.signature
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

    handleViewHistory() {
      this.closeActionsMenu()
      this.showHistoryDialog = true
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

  &.unread:not(.first-post) {
    border-left: 3px solid var(--color-primary-element);
    background: var(--color-primary-element-light-hover);
  }

  &:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }

  .unread-indicator {
    display: inline-block;
    width: 6px;
    height: 6px;
    background: var(--color-primary-element);
    border-radius: 50%;
    margin-right: 8px;
    flex-shrink: 0;
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
    position: relative;

    .unread-indicator {
      position: absolute;
      left: 0;
      top: 8px;
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

  .post-signature {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px dashed var(--color-border-dark);
  }

  .content-text,
  .signature-content {
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

    // Blockquotes ([quote])
    :deep(blockquote) {
      border-left: 4px solid var(--color-border-maxcontrast);
      margin: 12px 0;
      padding-left: 12px;
      color: var(--color-text-secondary);
    }

    // Lists ([list])
    :deep(ul) {
      margin: 12px 0;
      padding-left: 32px;
      list-style-type: disc;

      li {
        margin: 4px 0;
        line-height: 1.6;
      }
    }
  }

  .icon {
    font-size: 1rem;
  }
}
</style>
