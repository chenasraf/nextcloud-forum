<template>
  <NcDialog :name="strings.title" size="large" :open="open" @update:open="handleClose">
    <div class="bbcode-help">
      <!-- Built-in BBCodes Section -->
      <section class="bbcode-section">
        <h3 class="section-title">{{ strings.builtInTitle }}</h3>
        <p class="section-description">{{ strings.builtInDescription }}</p>

        <div class="bbcode-list">
          <!-- Library-provided BBCodes -->
          <div v-for="code in builtInCodes" :key="code.tag" class="bbcode-item">
            <div class="bbcode-header">
              <code class="bbcode-tag">[{{ code.tag }}]</code>
              <span class="bbcode-name">{{ code.name }}</span>
            </div>
            <div class="bbcode-example">
              <span class="example-label">{{ strings.example }}:</span>
              <code class="example-code">{{ code.example }}</code>
            </div>
          </div>

          <!-- Database builtin BBCodes -->
          <div v-for="code in builtinDbCodes" :key="code.id" class="bbcode-item">
            <div class="bbcode-header">
              <code class="bbcode-tag">[{{ code.tag }}]</code>
              <span v-if="code.description" class="bbcode-name">{{ code.description }}</span>
            </div>
            <div class="bbcode-example">
              <span class="example-label">{{ strings.example }}:</span>
              <code class="example-code">{{ code.example }}</code>
            </div>
          </div>
        </div>
      </section>

      <!-- Custom BBCodes Section -->
      <section v-if="showCustom" class="bbcode-section">
        <h3 class="section-title">{{ strings.customTitle }}</h3>
        <p class="section-description">{{ strings.customDescription }}</p>

        <!-- Loading state -->
        <div v-if="loading" class="loading-state">
          <NcLoadingIcon :size="32" />
          <span class="loading-text">{{ strings.loading }}</span>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="error-state">
          <span class="error-text">{{ error }}</span>
        </div>

        <!-- Empty state -->
        <div v-else-if="customCodes.length === 0" class="empty-state">
          <span class="empty-text">{{ strings.noCustomCodes }}</span>
        </div>

        <!-- Custom codes list -->
        <div v-else class="bbcode-list">
          <div v-for="code in customCodes" :key="code.id" class="bbcode-item">
            <div class="bbcode-header">
              <code class="bbcode-tag">[{{ code.tag }}]</code>
              <span v-if="code.description" class="bbcode-name">{{ code.description }}</span>
            </div>
            <div class="bbcode-example">
              <span class="example-label">{{ strings.example }}:</span>
              <code class="example-code">{{ code.example }}</code>
            </div>
          </div>
        </div>
      </section>
    </div>
  </NcDialog>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { t } from '@nextcloud/l10n'
import { ocs } from '@/axios'
import type { BBCode } from '@/types'

interface BuiltInCode {
  tag: string
  name: string
  example: string
}

export default defineComponent({
  name: 'BBCodeHelpDialog',
  components: {
    NcDialog,
    NcLoadingIcon,
  },
  props: {
    open: {
      type: Boolean,
      required: true,
    },
    showCustom: {
      type: Boolean,
      default: true,
    },
  },
  emits: ['update:open'],
  data() {
    return {
      loading: false,
      error: null as string | null,
      customCodes: [] as BBCode[],
      builtinDbCodes: [] as BBCode[],

      builtInCodes: [
        {
          tag: 'b',
          name: t('forum', 'Font style bold'),
          example: t('forum', '{bStart}Hello world{bEnd}', { bStart: '[b]', bEnd: '[/b]' }),
        },
        {
          tag: 'i',
          name: t('forum', 'Font style italic'),
          example: t('forum', '{iStart}Hello world{iEnd}', { iStart: '[i]', iEnd: '[/i]' }),
        },
        {
          tag: 's',
          name: t('forum', 'Font style struck through'),
          example: t('forum', '{sStart}Hello world{sEnd}', { sStart: '[s]', sEnd: '[/s]' }),
        },
        {
          tag: 'u',
          name: t('forum', 'Font style underlined'),
          example: t('forum', '{uStart}Hello world{uEnd}', { uStart: '[u]', uEnd: '[/u]' }),
        },
        {
          tag: 'code',
          name: t('forum', 'Code'),
          example: t('forum', '{codeStart}Hello world{codeEnd}', {
            codeStart: '[code]',
            codeEnd: '[/code]',
          }),
        },
        {
          tag: 'email',
          name: t('forum', 'Email (clickable)'),
          example: t('forum', '{emailStart}test@example.com{emailEnd}', {
            emailStart: '[email]',
            emailEnd: '[/email]',
          }),
        },
        {
          tag: 'url',
          name: t('forum', 'URL (clickable)'),
          example: t('forum', '{urlStart}Example.com{urlEnd}', {
            urlStart: '[url=http://example.com]',
            urlEnd: '[/url]',
          }),
        },
        {
          tag: 'img',
          name: t('forum', 'Image (not clickable)'),
          example: t('forum', '{imgStart}http://example.com/example.png{imgEnd}', {
            imgStart: '[img]',
            imgEnd: '[/img]',
          }),
        },
        {
          tag: 'quote',
          name: t('forum', 'Quote'),
          example: t('forum', '{quoteStart}Hello world{quoteEnd}', {
            quoteStart: '[quote]',
            quoteEnd: '[/quote]',
          }),
        },
        {
          tag: 'youtube',
          name: t('forum', 'Embedded YouTube video'),
          example: t('forum', '{youtubeStart}a-video-id-123456{youtubeEnd}', {
            youtubeStart: '[youtube]',
            youtubeEnd: '[/youtube]',
          }),
        },
        {
          tag: 'font',
          name: t('forum', 'Font (name)'),
          example: t('forum', '{fontStart}Hello world!{fontEnd}', {
            fontStart: '[font=Arial]',
            fontEnd: '[/font]',
          }),
        },
        {
          tag: 'size',
          name: t('forum', 'Font size'),
          example: t('forum', '{sizeStart}Hello world!{sizeEnd}', {
            sizeStart: '[size=12]',
            sizeEnd: '[/size]',
          }),
        },
        {
          tag: 'color',
          name: t('forum', 'Font color'),
          example: t('forum', '{colorStart}Hello world!{colorEnd}', {
            colorStart: '[color=red]',
            colorEnd: '[/color]',
          }),
        },
        {
          tag: 'left',
          name: t('forum', 'Align left'),
          example: t('forum', '{leftStart}Hello world{leftEnd}', {
            leftStart: '[left]',
            leftEnd: '[/left]',
          }),
        },
        {
          tag: 'center',
          name: t('forum', 'Align center'),
          example: t('forum', '{centerStart}Hello world{centerEnd}', {
            centerStart: '[center]',
            centerEnd: '[/center]',
          }),
        },
        {
          tag: 'right',
          name: t('forum', 'Align right'),
          example: t('forum', '{rightStart}Hello world{rightEnd}', {
            rightStart: '[right]',
            rightEnd: '[/right]',
          }),
        },
        {
          tag: 'list',
          name: t('forum', 'List'),
          example: t(
            'forum',
            '{listStart}{item1Start}Hello world!{item2Start}Hello moon!{item2End}{listEnd}',
            {
              listStart: '[list]',
              item1Start: '[*]',
              item2Start: '[li]',
              item2End: '[/li]',
              listEnd: '[/list]',
            },
          ),
        },
        {
          tag: '*',
          name: t('forum', 'List item within a list'),
          example: t('forum', '{itemStart}Hello world!\\r\\n{itemStart}Hello moon!', {
            itemStart: '[*]',
          }),
        },
        {
          tag: 'li',
          name: t('forum', 'List item within a list (alias)'),
          example: t('forum', '{liStart}Hello world!\\r\\n{liEnd}{liStart}Hello moon!{liEnd}', {
            liStart: '[li]',
            liEnd: '[/li]',
          }),
        },
      ] as BuiltInCode[],

      strings: {
        title: t('forum', 'BBCode help'),
        builtInTitle: t('forum', 'Built-in BBCodes'),
        builtInDescription: t('forum', 'These BBCodes are available by default.'),
        customTitle: t('forum', 'Custom BBCodes'),
        customDescription: t(
          'forum',
          'These BBCodes are custom to this forum and configured by administrators.',
        ),
        example: t('forum', 'Example'),
        replacement: t('forum', 'Replacement'),
        loading: t('forum', 'Loading custom BBCodes...'),
        noCustomCodes: t('forum', 'No custom BBCodes configured.'),
      },
    }
  },
  watch: {
    open: {
      immediate: true,
      handler(newValue) {
        if (newValue) {
          // Fetch builtin codes from database
          if (this.builtinDbCodes.length === 0) {
            this.fetchBuiltinCodes()
          }
          // Fetch custom codes if enabled
          if (this.showCustom && this.customCodes.length === 0) {
            this.fetchCustomCodes()
          }
        }
      },
    },
  },
  methods: {
    async fetchBuiltinCodes() {
      try {
        const response = await ocs.get<BBCode[]>('/bbcodes/builtin')
        this.builtinDbCodes = response.data || []
      } catch (e) {
        console.error('Failed to fetch builtin BBCodes:', e)
        // Silently fail for builtin codes - not critical
      }
    },

    async fetchCustomCodes() {
      if (!this.showCustom) {
        return
      }

      try {
        this.loading = true
        this.error = null
        const response = await ocs.get<BBCode[]>('/bbcodes')
        this.customCodes = (response.data || []).filter((code) => code.enabled)
      } catch (e) {
        console.error('Failed to fetch custom BBCodes:', e)
        this.error = t('forum', 'Failed to load custom BBCodes')
      } finally {
        this.loading = false
      }
    },

    handleClose(value: boolean) {
      this.$emit('update:open', value)
    },
  },
})
</script>

<style scoped lang="scss">
.bbcode-help {
  padding: 16px;
  max-height: 70vh;
  overflow-y: auto;
}

.bbcode-section {
  margin-bottom: 32px;

  &:last-child {
    margin-bottom: 0;
  }
}

.section-title {
  margin: 0 0 8px 0;
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--color-main-text);
}

.section-description {
  margin: 0 0 16px 0;
  font-size: 0.9rem;
  color: var(--color-text-lighter);
  line-height: 1.5;
}

.bbcode-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.bbcode-item {
  padding: 12px;
  background: var(--color-background-hover);
  border: 1px solid var(--color-border);
  border-radius: 6px;
}

.bbcode-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.bbcode-tag {
  padding: 4px 8px;
  background: var(--color-background-dark);
  border: 1px solid var(--color-border);
  border-radius: 4px;
  font-family: 'Courier New', Courier, monospace;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--color-primary-element);
}

.bbcode-name {
  font-size: 0.95rem;
  color: var(--color-main-text);
  font-weight: 500;
}

.bbcode-example,
.bbcode-replacement {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.example-label,
.replacement-label {
  font-size: 0.85rem;
  color: var(--color-text-maxcontrast);
  font-weight: 500;
}

.example-code,
.replacement-code {
  padding: 8px 12px;
  background: var(--color-background-dark);
  border: 1px solid var(--color-border-dark);
  border-radius: 4px;
  font-family: 'Courier New', Courier, monospace;
  font-size: 0.85rem;
  color: var(--color-main-text);
  white-space: pre-wrap;
  word-break: break-all;
}

.loading-state,
.error-state,
.empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 32px 16px;
  text-align: center;
}

.loading-text,
.empty-text {
  font-size: 0.9rem;
  color: var(--color-text-maxcontrast);
}

.error-text {
  font-size: 0.9rem;
  color: var(--color-error);
}
</style>
