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
  options: { template?: string; props?: string[] } = {},
) {
  const className = name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase()
  return {
    default: {
      name,
      template: options.template ?? `<div class="${className}-mock" />`,
      props: options.props ?? [],
    },
  }
}
