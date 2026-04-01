<template>
  <div class="deleted-item-list">
    <!-- Loading -->
    <div v-if="loading" class="center mt-16" aria-live="polite" aria-busy="true">
      <NcLoadingIcon :size="32" />
    </div>

    <!-- Error -->
    <NcEmptyContent
      v-else-if="error"
      :title="strings.errorTitle"
      :description="error"
      class="mt-16"
    >
      <template #action>
        <NcButton @click="$emit('retry')">{{ strings.retry }}</NcButton>
      </template>
    </NcEmptyContent>

    <!-- Empty -->
    <NcEmptyContent
      v-else-if="items.length === 0"
      :title="strings.emptyTitle"
      :description="strings.emptyDesc"
      class="mt-16"
    />

    <!-- Items -->
    <template v-else>
      <ul class="item-list">
        <!-- Thread items: clickable to open preview -->
        <li
          v-for="item in items"
          :key="item.id"
          class="deleted-item-wrapper"
          :class="{ clickable: mode === 'threads' }"
          :role="mode === 'threads' ? 'button' : undefined"
          :tabindex="mode === 'threads' ? 0 : undefined"
          @click="mode === 'threads' && $emit('view', item)"
          @keydown.enter="mode === 'threads' && $emit('view', item)"
        >
          <div class="deleted-item-overlay">
            <span class="deleted-badge">
              {{ strings.deleted }} <NcDateTime :timestamp="item.deletedAt * 1000" />
            </span>
            <router-link
              v-if="mode === 'replies' && item.threadSlug"
              :to="`/t/${item.threadSlug}`"
              class="thread-link"
              @click.stop
            >
              {{ item.threadTitle }}
            </router-link>
            <span v-else-if="mode === 'replies' && item.threadTitle" class="muted">
              {{ item.threadTitle }}
            </span>
            <div class="deleted-item-actions">
              <NcButton
                variant="primary"
                :disabled="restoring === item.id"
                @click.stop="$emit('restore', item)"
              >
                <template #icon>
                  <NcLoadingIcon v-if="restoring === item.id" :size="20" />
                  <DeleteRestoreIcon v-else :size="20" />
                </template>
                {{ strings.restore }}
              </NcButton>
            </div>
          </div>
          <ThreadCard v-if="mode === 'threads'" :thread="item" />
          <PostCard v-else :post="item" />
        </li>
      </ul>

      <Pagination
        v-if="maxPages > 1"
        :current-page="page"
        :max-pages="maxPages"
        @update:current-page="$emit('update:page', $event)"
      />
    </template>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ThreadCard from '@/components/ThreadCard'
import PostCard from '@/components/PostCard'
import Pagination from '@/components/Pagination'
import DeleteRestoreIcon from '@icons/DeleteRestore.vue'
import { t } from '@nextcloud/l10n'

export default defineComponent({
  name: 'ModerationDeletedList',
  components: {
    NcButton,
    NcDateTime,
    NcEmptyContent,
    NcLoadingIcon,
    ThreadCard,
    PostCard,
    Pagination,
    DeleteRestoreIcon,
  },
  props: {
    mode: { type: String as () => 'threads' | 'replies', required: true },
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    items: { type: Array as () => any[], required: true },
    total: { type: Number, required: true },
    page: { type: Number, required: true },
    perPage: { type: Number, required: true },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
    restoring: { type: Number, default: null },
  },
  emits: ['view', 'restore', 'retry', 'update:page'],
  computed: {
    maxPages(): number {
      return Math.ceil(this.total / this.perPage)
    },
    strings() {
      return {
        deleted: t('forum', 'Deleted'),
        restore: t('forum', 'Restore'),
        errorTitle: t('forum', 'Error loading content'),
        retry: t('forum', 'Retry'),
        emptyTitle: t('forum', 'No deleted content'),
        emptyDesc: t('forum', 'There is no deleted content to review.'),
      }
    },
  },
})
</script>

<style scoped lang="scss">
.item-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 16px;
  list-style: none;
  padding: 0;
}

.deleted-item-wrapper {
  position: relative;
  opacity: 0.85;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  overflow: hidden;

  &.clickable {
    cursor: pointer;
  }

  &:hover {
    opacity: 1;
  }
}

.deleted-item-overlay {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 16px;
  background: var(--color-background-dark);
  border-bottom: 1px solid var(--color-border);
  font-size: 0.85rem;
}

.deleted-item-actions {
  display: flex;
  gap: 8px;
  margin-left: auto;
  flex-shrink: 0;
}

.deleted-badge {
  font-weight: 600;
  color: var(--color-error-text);
}

.thread-link {
  color: var(--color-primary-element);
  text-decoration: none;

  &:hover {
    text-decoration: underline;
  }
}
</style>
