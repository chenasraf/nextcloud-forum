import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'

vi.mock('@icons/AlertCircle.vue', () => createIconMock('AlertCircleIcon'))
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
vi.mock('@/components/PageWrapper', () =>
  createComponentMock('PageWrapper', {
    template: '<div class="page-wrapper-mock"><slot /></div>',
  }),
)

import GenericNotFound from '../GenericNotFound.vue'

describe('GenericNotFound', () => {
  const mountComponent = () => {
    return mount(GenericNotFound)
  }

  it('renders PageWrapper component', () => {
    const wrapper = mountComponent()
    expect(wrapper.find('.page-wrapper-mock').exists()).toBe(true)
  })

  it('renders NotFoundPage component inside PageWrapper', () => {
    const wrapper = mountComponent()
    const pageWrapper = wrapper.find('.page-wrapper-mock')
    expect(pageWrapper.find('.not-found-page-mock').exists()).toBe(true)
  })

  it('passes correct title', () => {
    const wrapper = mountComponent()
    expect(wrapper.find('.title').text()).toBe('Page not found')
  })

  it('passes correct description', () => {
    const wrapper = mountComponent()
    expect(wrapper.find('.description').text()).toBe(
      'The page you are looking for could not be found.',
    )
  })

  it('passes AlertCircleIcon as icon', () => {
    const wrapper = mountComponent()
    expect(wrapper.find('.icon').exists()).toBe(true)
    expect(wrapper.find('.icon').classes()).toContain('alert-circle-icon')
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
