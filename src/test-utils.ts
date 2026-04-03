/**
 * Test utilities and mock factories for Nextcloud dependencies.
 *
 * Usage in test files:
 *
 *   import { vi } from 'vitest'
 *   import { createIconMock, createComponentMock } from '@/test-utils'
 *
 *   vi.mock('@icons/Check.vue', () => createIconMock('CheckIcon'))
 *   vi.mock('@/components/UserInfo', () => createComponentMock('UserInfo', { ... }))
 *
 * ## Categories
 *
 * - **Icon mocks**: `createIconMock()`
 * - **Component mocks**: `createComponentMock()`, `createNcActionsMock()`,
 *   `createNcActionButtonMock()`, `createNcCheckboxRadioSwitchMock()`
 * - **Router stubs**: `RouterLinkStub`
 * - **Module mocks**: `createCurrentUserMock()`
 *
 * ## Global mocks (test-setup.ts)
 *
 * The following modules are globally mocked in test-setup.ts and do NOT need
 * per-file `vi.mock()` calls:
 *
 * - `@nextcloud/l10n` (t, n)
 * - `@nextcloud/router` (generateUrl)
 * - `@nextcloud/vue/functions/isDarkTheme`
 * - `@nextcloud/vue/components/*` (NcButton, NcDialog, NcEmptyContent, etc.)
 * - `@/axios` (ocs.get/post/put/delete, webDav.put/request)
 * - `@nextcloud/dialogs` (showSuccess, showError, showWarning)
 */

/**
 * Create a mock for an icon component from vue-material-design-icons.
 *
 * @param name - Component name (e.g., 'CheckIcon')
 * @param className - Optional CSS class (defaults to kebab-case of name)
 * @returns Mock factory object for vi.mock()
 *
 * @example
 * vi.mock('@icons/Check.vue', () => createIconMock('CheckIcon'))
 * // Creates: <span class="check-icon" data-icon="CheckIcon" />
 */
export function createIconMock(name: string, className?: string) {
  const cssClass = className ?? name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase()
  return {
    default: {
      name,
      template: `<span class="${cssClass}" data-icon="${name}" />`,
      props: ['size'],
    },
  }
}

/**
 * Create a mock for a Vue component.
 *
 * @param name - Component name
 * @param options - Optional template and props configuration
 * @returns Mock factory object for vi.mock()
 *
 * @example
 * vi.mock('@/components/UserInfo', () => createComponentMock('UserInfo', {
 *   template: '<div class="user-info-mock"><slot name="meta" /></div>',
 *   props: ['userId', 'displayName'],
 * }))
 */
export function createComponentMock(
  name: string,
  options: { template?: string; props?: string[]; emits?: string[] } = {},
) {
  const className = name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase()
  return {
    default: {
      name,
      template: options.template ?? `<div class="${className}-mock" />`,
      props: options.props ?? [],
      emits: options.emits ?? [],
    },
  }
}

// ============================================================================
// Router stubs
// ============================================================================

/**
 * Stub for `<router-link>` that renders as a plain `<a>` tag.
 *
 * Pass via mount options: `global: { stubs: { RouterLink: RouterLinkStub } }`
 * or `global: { stubs: { 'router-link': RouterLinkStub } }`
 *
 * @example
 * const wrapper = mount(MyComponent, {
 *   global: { stubs: { RouterLink: RouterLinkStub } },
 * })
 * expect(wrapper.find('.router-link').exists()).toBe(true)
 */
export const RouterLinkStub = {
  name: 'RouterLink',
  template: '<a class="router-link" :href="to"><slot /></a>',
  props: ['to'],
}

// ============================================================================
// Nextcloud component mocks
// ============================================================================

/**
 * Mock for `NcActions` — renders a wrapper div with slots.
 *
 * @example
 * vi.mock('@nextcloud/vue/components/NcActions', () => createNcActionsMock())
 */
export function createNcActionsMock() {
  return {
    default: {
      name: 'NcActions',
      template: '<div class="nc-actions"><slot /><slot name="icon" /></div>',
      props: ['ariaLabel'],
    },
  }
}

/**
 * Mock for `NcActionButton` — renders a clickable button with slots.
 *
 * @example
 * vi.mock('@nextcloud/vue/components/NcActionButton', () => createNcActionButtonMock())
 */
export function createNcActionButtonMock() {
  return {
    default: {
      name: 'NcActionButton',
      template:
        '<button class="nc-action-button" :aria-label="ariaLabel" :title="title" @click="$emit(\'click\', $event)"><slot /><slot name="icon" /></button>',
      props: ['ariaLabel', 'title'],
      emits: ['click'],
    },
  }
}

/**
 * Mock for `NcCheckboxRadioSwitch` in checkbox mode.
 *
 * @example
 * vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => createNcCheckboxRadioSwitchMock())
 */
export function createNcCheckboxRadioSwitchMock() {
  return {
    default: {
      name: 'NcCheckboxRadioSwitch',
      template:
        '<label class="nc-checkbox" :class="{ disabled }" @click="!disabled && $emit(\'update:model-value\', !modelValue)"><input type="checkbox" :checked="modelValue" :disabled="disabled" /><slot /></label>',
      props: ['modelValue', 'disabled', 'indeterminate'],
      emits: ['update:model-value'],
    },
  }
}

// ============================================================================
// Module mock factories
// ============================================================================

/**
 * Mock for `@nextcloud/auth` with a controllable `getCurrentUser` mock.
 *
 * Returns the mock factory (for `vi.mock`) and the underlying `vi.fn()` so
 * tests can change the return value between assertions.
 *
 * @example
 * const { mockGetCurrentUser } = createCurrentUserMock()
 * vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => mockGetCurrentUser() }))
 *
 * // In beforeEach:
 * mockGetCurrentUser.mockReturnValue({ uid: 'admin', displayName: 'Admin', isAdmin: true })
 */
export function createCurrentUserMock() {
  const mockGetCurrentUser = vi.fn()
  return { mockGetCurrentUser }
}
