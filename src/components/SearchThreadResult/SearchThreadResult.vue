<template>
  <div class="search-thread-result" :class="{ 'dark-theme': isDarkTheme }" @click="$emit('click')">
    <div class="result-header">
      <h4 class="thread-title">
        <span v-if="thread.isPinned" class="badge badge-pinned" :title="strings.pinned">
          <PinIcon :size="16" />
        </span>
        <span v-if="thread.isLocked" class="badge badge-locked" :title="strings.locked">
          <LockIcon :size="16" />
        </span>
        <span v-html="highlightedTitle"></span>
      </h4>
    </div>

    <div class="result-meta">
      <span class="meta-item category">
        <FolderIcon :size="16" />
        {{ thread.categoryName || strings.uncategorized }}
      </span>
      <span class="meta-item author">
        <AccountIcon :size="16" />
        {{ thread.author?.displayName || strings.deletedUser }}
      </span>
      <span class="meta-item">
        <MessageIcon :size="16" />
        {{ n('forum', '%n reply', '%n replies', thread.postCount) }}
      </span>
      <span class="meta-item">
        <EyeIcon :size="16" />
        {{ n('forum', '%n view', '%n views', thread.viewCount) }}
      </span>
      <span class="meta-item time">
        <ClockIcon :size="16" />
        <NcDateTime v-if="thread.createdAt" :timestamp="thread.createdAt * 1000" />
      </span>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import type { Thread } from '@/types'
import { n, t } from '@nextcloud/l10n'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import { isDarkTheme } from '@nextcloud/vue/functions/isDarkTheme'
import FolderIcon from '@icons/Folder.vue'
import AccountIcon from '@icons/Account.vue'
import MessageIcon from '@icons/Message.vue'
import EyeIcon from '@icons/Eye.vue'
import ClockIcon from '@icons/Clock.vue'
import PinIcon from '@icons/Pin.vue'
import LockIcon from '@icons/Lock.vue'

export default defineComponent({
  name: 'SearchThreadResult',
  components: {
    NcDateTime,
    FolderIcon,
    AccountIcon,
    MessageIcon,
    EyeIcon,
    ClockIcon,
    PinIcon,
    LockIcon,
  },
  props: {
    thread: {
      type: Object as PropType<Thread>,
      required: true,
    },
    query: {
      type: String,
      required: true,
    },
  },
  emits: ['click'],
  data() {
    return {
      isDarkTheme,
      strings: {
        pinned: t('forum', 'Pinned thread'),
        locked: t('forum', 'Locked thread'),
        uncategorized: t('forum', 'Uncategorized'),
        deletedUser: t('forum', 'Deleted user'),
      },
    }
  },
  computed: {
    highlightedTitle(): string {
      return this.highlightQuery(this.thread.title, this.query)
    },
  },
  methods: {
    n,
    highlightQuery(text: string, query: string): string {
      if (!query || !text) {
        return this.escapeHtml(text)
      }

      // Extract search terms from query (handle quoted phrases, AND/OR, parentheses, exclusions)
      const terms = this.extractSearchTerms(query)

      if (terms.length === 0) {
        return this.escapeHtml(text)
      }

      // Escape HTML first
      let escaped = this.escapeHtml(text)

      // Sort terms by length (longest first) to handle overlapping matches
      const sortedTerms = terms.sort((a, b) => b.length - a.length)

      // Highlight each term
      for (const term of sortedTerms) {
        const regex = new RegExp(`(${this.escapeRegex(term)})`, 'gi')
        escaped = escaped.replace(regex, '<mark>$1</mark>')
      }

      return escaped
    },
    extractSearchTerms(query: string): string[] {
      const terms: string[] = []
      const quotedRegex = /"([^"]+)"/g
      let match

      // Extract quoted phrases
      while ((match = quotedRegex.exec(query)) !== null) {
        if (match[1]) {
          terms.push(match[1])
        }
      }

      // Remove quoted phrases from query
      const remainingQuery = query.replace(quotedRegex, '')

      // Extract individual words (excluding operators, parentheses, and exclusions)
      const words = remainingQuery.split(/\s+/).filter((word) => {
        const trimmed = word.trim()
        return (
          trimmed.length > 0 &&
          trimmed !== 'AND' &&
          trimmed !== 'OR' &&
          !trimmed.startsWith('-') &&
          !trimmed.startsWith('(') &&
          !trimmed.endsWith(')')
        )
      })

      terms.push(...words)

      return terms.filter((term, index, self) => self.indexOf(term) === index) // Remove duplicates
    },
    escapeHtml(text: string): string {
      const div = document.createElement('div')
      div.textContent = text
      return div.innerHTML
    },
    escapeRegex(text: string): string {
      return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    },
  },
})
</script>

<style scoped lang="scss">
.search-thread-result {
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  background: var(--color-main-background);
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover {
    background: var(--color-background-hover);
    border-color: var(--color-primary-element);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }

  .result-header {
    margin-bottom: 12px;

    .thread-title {
      margin: 0;
      font-size: 1.125rem;
      font-weight: 600;
      color: var(--color-main-text);
      line-height: 1.4;
      display: flex;
      align-items: center;
      gap: 6px;

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

      :deep(mark) {
        background: #ffc107;
        color: #000;
        padding: 2px 4px;
        border-radius: 3px;
        font-weight: 700;
      }
    }
  }

  &.dark-theme {
    .thread-title {
      :deep(mark) {
        background: #ff9800;
        color: #fff;
      }
    }
  }

  .result-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 0.875rem;
    color: var(--color-text-maxcontrast);

    .meta-item {
      display: flex;
      align-items: center;
      gap: 4px;

      &.category {
        font-weight: 600;
        color: var(--color-primary-element);
      }

      &.author {
        color: var(--color-main-text);
      }
    }
  }
}
</style>
