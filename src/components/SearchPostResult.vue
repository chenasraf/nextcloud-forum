<template>
  <div class="search-post-result" @click="navigateToPost">
    <div class="result-header">
      <div class="thread-context">
        <span class="meta-label">{{ strings.inThread }}:</span>
        <router-link
          v-if="post.threadSlug"
          :to="`/t/${post.threadSlug}#post-${post.id}`"
          class="thread-link"
          @click.stop
        >
          {{ post.threadTitle }}
        </router-link>
        <span v-else class="thread-missing">{{ strings.threadUnavailable }}</span>
      </div>
    </div>

    <div class="post-content" v-html="highlightedContent"></div>

    <div class="result-meta">
      <span class="meta-item author">
        <AccountIcon :size="16" />
        {{ post.authorDisplayName || strings.deletedUser }}
      </span>
      <span class="meta-item time">
        <ClockIcon :size="16" />
        <NcDateTime v-if="post.createdAt" :timestamp="post.createdAt * 1000" />
      </span>
      <span v-if="post.isEdited" class="meta-item edited">
        <PencilIcon :size="16" />
        {{ strings.edited }}
      </span>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import type { Post } from '@/types'
import { t } from '@nextcloud/l10n'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import AccountIcon from '@icons/Account.vue'
import ClockIcon from '@icons/Clock.vue'
import PencilIcon from '@icons/Pencil.vue'

export default defineComponent({
  name: 'SearchPostResult',
  components: {
    NcDateTime,
    AccountIcon,
    ClockIcon,
    PencilIcon,
  },
  props: {
    post: {
      type: Object as PropType<Post>,
      required: true,
    },
    query: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      strings: {
        inThread: t('forum', 'In thread'),
        threadUnavailable: t('forum', 'Thread unavailable'),
        deletedUser: t('forum', 'Deleted User'),
        edited: t('forum', 'Edited'),
      },
    }
  },
  computed: {
    highlightedContent(): string {
      // Strip HTML tags first, then highlight query terms, then truncate
      const text = this.stripHtml(this.post.content)
      const truncated = this.truncateContent(text, 250)
      return this.highlightQuery(truncated, this.query)
    },
  },
  methods: {
    navigateToPost(): void {
      if (this.post.threadSlug) {
        this.$router.push(`/t/${this.post.threadSlug}#post-${this.post.id}`)
      }
    },
    stripHtml(html: string): string {
      const div = document.createElement('div')
      div.innerHTML = html
      return div.textContent || div.innerText || ''
    },
    truncateContent(text: string, maxLength: number): string {
      if (text.length <= maxLength) {
        return text
      }
      return text.substring(0, maxLength) + '...'
    },
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
.search-post-result {
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

    .thread-context {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.875rem;

      .meta-label {
        color: var(--color-text-maxcontrast);
        font-weight: 500;
      }

      .thread-link {
        color: var(--color-primary-element);
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s ease;

        &:hover {
          color: var(--color-primary-element-hover);
          text-decoration: underline;
        }
      }

      .thread-missing {
        color: var(--color-text-maxcontrast);
        font-style: italic;
      }
    }
  }

  .post-content {
    margin-bottom: 12px;
    line-height: 1.6;
    color: var(--color-main-text);
    font-size: 0.9375rem;

    :deep(mark) {
      background: var(--color-primary-element-light);
      color: var(--color-primary-element-text);
      padding: 2px 4px;
      border-radius: 3px;
      font-weight: 700;
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

      &.author {
        color: var(--color-main-text);
        font-weight: 500;
      }

      &.edited {
        color: var(--color-warning);
      }
    }
  }
}
</style>
