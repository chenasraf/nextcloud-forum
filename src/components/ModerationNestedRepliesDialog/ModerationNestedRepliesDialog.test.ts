import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock } from '@/test-utils'
import ModerationNestedRepliesDialog from './ModerationNestedRepliesDialog.vue'

// Uses global mocks for @nextcloud/l10n, NcButton, NcDialog from test-setup.ts

vi.mock('@icons/DeleteForever.vue', () => createIconMock('DeleteForeverIcon'))

function findButton(wrapper: ReturnType<typeof mount>, label: string) {
  return wrapper.findAll('button').find((b) => b.text().includes(label))
}

describe('ModerationNestedRepliesDialog', () => {
  it('should not render when closed', () => {
    const wrapper = mount(ModerationNestedRepliesDialog, {
      props: { open: false, replyCount: 2 },
    })
    expect(wrapper.find('.nc-dialog').exists()).toBe(false)
  })

  it('should describe the nested replies of a single reply', () => {
    const wrapper = mount(ModerationNestedRepliesDialog, {
      props: { open: true, replyCount: 3 },
    })
    expect(wrapper.text()).toContain('This reply has 3 nested replies')
  })

  it('should describe the nested replies of several replies', () => {
    const wrapper = mount(ModerationNestedRepliesDialog, {
      props: { open: true, postCount: 2, replyCount: 1 },
    })
    expect(wrapper.text()).toContain('The selected replies have 1 nested reply')
  })

  it('should emit choose keep', async () => {
    const wrapper = mount(ModerationNestedRepliesDialog, {
      props: { open: true, replyCount: 1 },
    })
    await findButton(wrapper, 'Keep nested replies')?.trigger('click')
    expect(wrapper.emitted('choose')?.[0]).toEqual(['keep'])
  })

  it('should emit choose delete', async () => {
    const wrapper = mount(ModerationNestedRepliesDialog, {
      props: { open: true, replyCount: 1 },
    })
    await findButton(wrapper, 'Delete nested replies')?.trigger('click')
    expect(wrapper.emitted('choose')?.[0]).toEqual(['delete'])
  })

  it('should close on cancel without choosing', async () => {
    const wrapper = mount(ModerationNestedRepliesDialog, {
      props: { open: true, replyCount: 1 },
    })
    await findButton(wrapper, 'Cancel')?.trigger('click')
    expect(wrapper.emitted('update:open')?.[0]).toEqual([false])
    expect(wrapper.emitted('choose')).toBeUndefined()
  })
})
