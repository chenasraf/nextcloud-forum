import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createIconMock } from '@/test-utils'

// Uses global mock for @/axios from test-setup.ts

vi.mock('@icons/Creation.vue', () => createIconMock('CreationIcon'))
vi.mock('@icons/Close.vue', () => createIconMock('CloseIcon'))

import { ocs } from '@/axios'
import ThreadSummary from './ThreadSummary.vue'

const mockPost = vi.mocked(ocs.post)
const mockGet = vi.mocked(ocs.get)

describe('ThreadSummary', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const createWrapper = (props = {}) =>
    mount(ThreadSummary, {
      props: {
        threadId: 7,
        ...props,
      },
    })

  it('requests a summary of the whole thread by default', async () => {
    mockPost.mockResolvedValue({
      data: { taskId: 1, status: 'successful', summary: 'The gist.' },
    } as never)

    const wrapper = createWrapper()
    await flushPromises()

    expect(mockPost).toHaveBeenCalledWith('/threads/7/summary', { afterPostId: 0 })
    expect(wrapper.find('.summary-text').text()).toBe('The gist.')
    expect(wrapper.text()).toContain('AI-generated')
    expect(wrapper.text()).toContain('Thread summary')
  })

  it('requests a summary of unread replies', async () => {
    mockPost.mockResolvedValue({
      data: { taskId: 1, status: 'successful', summary: 'New things.' },
    } as never)

    const wrapper = createWrapper({ afterPostId: 12 })
    await flushPromises()

    expect(mockPost).toHaveBeenCalledWith('/threads/7/summary', { afterPostId: 12 })
    expect(wrapper.text()).toContain('Summary of unread replies')
  })

  it('polls the task until it finishes', async () => {
    mockPost.mockResolvedValue({ data: { taskId: 5, status: 'scheduled', summary: null } } as never)
    mockGet
      .mockResolvedValueOnce({ data: { taskId: 5, status: 'running', summary: null } } as never)
      .mockResolvedValueOnce({
        data: { taskId: 5, status: 'successful', summary: 'Done.' },
      } as never)

    const wrapper = createWrapper()
    await flushPromises()
    expect(wrapper.find('.summary-pending').exists()).toBe(true)

    await vi.runOnlyPendingTimersAsync()
    await flushPromises()
    expect(mockGet).toHaveBeenCalledWith('/threads/7/summary/5')
    expect(wrapper.find('.summary-pending').exists()).toBe(true)

    await vi.runOnlyPendingTimersAsync()
    await flushPromises()
    expect(mockGet).toHaveBeenCalledTimes(2)
    expect(wrapper.find('.summary-text').text()).toBe('Done.')
  })

  it('shows an error when the task fails', async () => {
    mockPost.mockResolvedValue({ data: { taskId: 5, status: 'failed', summary: null } } as never)

    const wrapper = createWrapper()
    await flushPromises()

    expect(wrapper.find('.summary-error').exists()).toBe(true)
    expect(wrapper.find('.summary-text').exists()).toBe(false)
  })

  it('shows an error when the request fails', async () => {
    mockPost.mockRejectedValue(new Error('503'))
    vi.spyOn(console, 'error').mockImplementation(() => {})

    const wrapper = createWrapper()
    await flushPromises()

    expect(wrapper.find('.summary-error').exists()).toBe(true)
  })

  it('stops polling when unmounted', async () => {
    mockPost.mockResolvedValue({ data: { taskId: 5, status: 'scheduled', summary: null } } as never)

    const wrapper = createWrapper()
    await flushPromises()
    wrapper.unmount()

    await vi.runOnlyPendingTimersAsync()
    expect(mockGet).not.toHaveBeenCalled()
  })

  it('emits close', async () => {
    mockPost.mockResolvedValue({ data: { taskId: 1, status: 'successful', summary: 'x' } } as never)

    const wrapper = createWrapper()
    await flushPromises()
    wrapper.findComponent('.summary-close').vm.$emit('click')

    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})
