<template>
  <div class="search-thread-result" @click="$emit('click')">
    <div class="result-header">
      <h4 class="thread-title" v-html="highlightedTitle"></h4>
      <div class="thread-badges">
        <span v-if="thread.isPinned" class="badge badge-pinned">{{ strings.pinned }}</span>
        <span v-if="thread.isLocked" class="badge badge-locked">{{ strings.locked }}</span>
      </div>
    </div>

    <div class="result-meta">
      <span class="meta-item category">
        <FolderIcon :size="16" />
        {{ thread.categoryName || strings.uncategorized }}
      </span>
      <span class="meta-item author">
        <AccountIcon :size="16" />
        {{ thread.authorDisplayName || strings.deletedUser }}
      </span>
      <span class="meta-item">
        <MessageIcon :size="16" />
        {{ n('forum', '%n post', '%n posts', thread.postCount) }}
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
import FolderIcon from '@icons/Folder.vue'
import AccountIcon from '@icons/Account.vue'
import MessageIcon from '@icons/Message.vue'
import EyeIcon from '@icons/Eye.vue'
import ClockIcon from '@icons/Clock.vue'

export default defineComponent({
  name: 'SearchThreadResult',
  components: {
    NcDateTime,
    FolderIcon,
    AccountIcon,
    MessageIcon,
    EyeIcon,
    ClockIcon,
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
      strings: {
        pinned: t('forum', 'Pinned'),
        locked: t('forum', 'Locked'),
        uncategorized: t('forum', 'Uncategorized'),
        deletedUser: t('forum', 'Deleted User'),
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
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;

    .thread-title {
      margin: 0;
      font-size: 1.125rem;
      font-weight: 600;
      color: var(--color-main-text);
      flex: 1;
      line-height: 1.4;

      :deep(mark) {
        background: var(--color-primary-element-light);
        color: var(--color-primary-element-text);
        padding: 2px 4px;
        border-radius: 3px;
        font-weight: 700;
      }
    }

    .thread-badges {
      display: flex;
      gap: 6px;
      flex-shrink: 0;

      .badge {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;

        &.badge-pinned {
          background: var(--color-primary-element-light);
          color: var(--color-primary-element-text);
        }

        &.badge-locked {
          background: var(--color-warning);
          color: var(--color-main-background);
        }
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
