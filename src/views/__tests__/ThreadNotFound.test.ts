import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'

vi.mock('@icons/MessageAlert.vue', () => createIconMock('MessageAlertIcon'))
vi.mock('@/components/NotFoundPage', () =>
  createComponentMock('NotFoundPage', {
    template: `
      <div class="not-found-page-mock">
        <span class="title">{{ title }}</span>
        <span class="description">{{ description }}</span>
        <span class="show-back">{{ showBackButton }}</span>
        <span class="show-home">{{ showHomeButton }}</span>
        <component :is="icon" class="icon" />
      </div>
    `,
    props: ['title', 'description', 'icon', 'showBackButton', 'showHomeButton'],
  }),
)

import ThreadNotFound from '../ThreadNotFound.vue'

describe('ThreadNotFound', () => {
  const mountComponent = () => {
    return mount(ThreadNotFound)
  }

  it('renders NotFoundPage component', () => {
    const wrapper = mountComponent()
    expect(wrapper.find('.not-found-page-mock').exists()).toBe(true)
  })

  it('passes correct title', () => {
    const wrapper = mountComponent()
    expect(wrapper.find('.title').text()).toBe('Thread not found')
  })

  it('passes correct description', () => {
    const wrapper = mountComponent()
    expect(wrapper.find('.description').text()).toBe(
      'The thread you are looking for does not exist or has been removed.',
    )
  })

  it('passes MessageAlertIcon as icon', () => {
    const wrapper = mountComponent()
    expect(wrapper.find('.icon').exists()).toBe(true)
    expect(wrapper.find('.icon').classes()).toContain('message-alert-icon')
  })

  it('shows back button', () => {
    const wrapper = mountComponent()
    expect(wrapper.find('.show-back').text()).toBe('true')
  })

  it('shows home button', () => {
    const wrapper = mountComponent()
    expect(wrapper.find('.show-home').text()).toBe('true')
  })
})
