/**
 * Vitest global setup file.
 *
 * This file sets up global mocks that are commonly used across tests.
 * These mocks are applied automatically to all test files.
 */
import { vi } from 'vitest'

// Mock @nextcloud/l10n globally
vi.mock('@nextcloud/l10n', () => ({
  t: (_app: string, text: string, vars?: Record<string, unknown>) => {
    if (vars) {
      return Object.entries(vars).reduce(
        (acc, [key, value]) => acc.replace(`{${key}}`, String(value)),
        text,
      )
    }
    return text
  },
  n: (
    _app: string,
    singular: string,
    plural: string,
    count: number,
    vars?: Record<string, unknown>,
  ) => {
    let result = count === 1 ? singular : plural
    // Replace %n with actual count
    result = result.replace(/%n/g, String(count))
    if (vars) {
      result = Object.entries(vars).reduce(
        (acc, [key, value]) => acc.replace(`{${key}}`, String(value)),
        result,
      )
    }
    return result
  },
}))

// Mock @nextcloud/router globally
vi.mock('@nextcloud/router', () => ({
  generateUrl: (path: string) => path,
}))

// Mock @nextcloud/vue/functions/isDarkTheme globally (defaults to light theme)
vi.mock('@nextcloud/vue/functions/isDarkTheme', () => ({
  isDarkTheme: false,
}))

// Mock @nextcloud/vue components globally
vi.mock('@nextcloud/vue/components/NcDateTime', () => ({
  default: {
    name: 'NcDateTime',
    template: '<span class="nc-datetime" :data-timestamp="timestamp" />',
    props: ['timestamp'],
  },
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button :disabled="disabled" :href="href" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
    props: ['variant', 'disabled', 'ariaLabel', 'title', 'href'],
  },
}))

vi.mock('@nextcloud/vue/components/NcAvatar', () => ({
  default: {
    name: 'NcAvatar',
    template: '<div class="nc-avatar-mock" :data-user="user" :data-size="size"></div>',
    props: ['user', 'displayName', 'size'],
  },
}))

vi.mock('@nextcloud/vue/components/NcEmptyContent', () => ({
  default: {
    name: 'NcEmptyContent',
    template:
      '<div class="nc-empty-content"><slot name="icon" /><span class="title">{{ title }}</span><span class="description">{{ description }}</span><slot name="action" /></div>',
    props: ['title', 'description'],
  },
}))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    template: '<div class="nc-dialog" v-if="open"><slot /><slot name="actions" /></div>',
    props: ['name', 'open', 'size'],
  },
}))

vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
  default: {
    name: 'NcLoadingIcon',
    template: '<span class="nc-loading-icon" />',
    props: ['size'],
  },
}))

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
  default: {
    name: 'NcTextField',
    template:
      '<input class="nc-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" :disabled="disabled" :placeholder="placeholder" />',
    props: ['modelValue', 'label', 'placeholder', 'disabled', 'required', 'type'],
    emits: ['update:modelValue'],
  },
}))

vi.mock('@nextcloud/vue/components/NcTextArea', () => ({
  default: {
    name: 'NcTextArea',
    template:
      '<textarea class="nc-text-area" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" :disabled="disabled" :placeholder="placeholder" />',
    props: ['modelValue', 'label', 'placeholder', 'disabled', 'rows'],
    emits: ['update:modelValue'],
  },
}))

vi.mock('@nextcloud/vue/components/NcSelect', () => ({
  default: {
    name: 'NcSelect',
    template:
      '<select class="nc-select" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><slot /></select>',
    props: ['modelValue', 'options', 'placeholder', 'label', 'trackBy', 'clearable'],
    emits: ['update:modelValue'],
  },
}))

vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({
  default: {
    name: 'NcNoteCard',
    template: '<div class="nc-note-card" :data-type="type"><slot /></div>',
    props: ['type'],
  },
}))
