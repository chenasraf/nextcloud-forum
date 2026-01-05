import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import PostReplyForm from './PostReplyForm.vue'

// Mock BBCodeEditor
vi.mock('@/components/BBCodeEditor', () =>
  createComponentMock('BBCodeEditor', {
    template: `<div class="bbcode-editor-mock">
      <textarea
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        @input="$emit('update:modelValue', $event.target.value)"
        @keydown="$emit('keydown', $event)"
      />
    </div>`,
    props: ['modelValue', 'placeholder', 'rows', 'disabled', 'minHeight'],
    emits: ['update:modelValue', 'keydown'],
  }),
)

// Mock UserInfo
vi.mock('@/components/UserInfo', () =>
  createComponentMock('UserInfo', {
    template: '<div class="user-info-mock" :data-user-id="userId">{{ displayName }}</div>',
    props: ['userId', 'displayName', 'avatarSize', 'clickable'],
  }),
)

// Mock icons
vi.mock('@icons/Send.vue', () => createIconMock('SendIcon'))

// Mock NcLoadingIcon
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () =>
  createComponentMock('NcLoadingIcon', {
    template: '<span class="loading-icon-mock" />',
    props: ['size'],
  }),
)

// Mock useCurrentUser composable
vi.mock('@/composables/useCurrentUser', () => ({
  useCurrentUser: () => ({
    userId: 'testuser',
    displayName: 'Test User',
  }),
}))

describe('PostReplyForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('rendering', () => {
    it('should render user info header', () => {
      const wrapper = mount(PostReplyForm)
      const userInfo = wrapper.find('.user-info-mock')
      expect(userInfo.exists()).toBe(true)
      expect(userInfo.attributes('data-user-id')).toBe('testuser')
      expect(userInfo.text()).toBe('Test User')
    })

    it('should render editor', () => {
      const wrapper = mount(PostReplyForm)
      expect(wrapper.find('.bbcode-editor-mock').exists()).toBe(true)
    })

    it('should render cancel and submit buttons', () => {
      const wrapper = mount(PostReplyForm)
      const buttons = wrapper.findAll('button')
      expect(buttons).toHaveLength(2)
      expect(buttons[0]!.text()).toBe('Cancel')
      expect(buttons[1]!.text()).toContain('Submit reply')
    })

    it('should render send icon in submit button', () => {
      const wrapper = mount(PostReplyForm)
      expect(wrapper.find('.send-icon').exists()).toBe(true)
    })
  })

  describe('button states', () => {
    it('should disable submit button when content is empty', () => {
      const wrapper = mount(PostReplyForm)
      const submitButton = wrapper.findAll('button')[1]!
      expect(submitButton.attributes('disabled')).toBeDefined()
    })

    it('should disable cancel button when content is empty', () => {
      const wrapper = mount(PostReplyForm)
      const cancelButton = wrapper.findAll('button')[0]!
      expect(cancelButton.attributes('disabled')).toBeDefined()
    })

    it('should enable submit button when content is not empty', async () => {
      const wrapper = mount(PostReplyForm)
      const textarea = wrapper.find('textarea')
      await textarea.setValue('Some reply content')

      const submitButton = wrapper.findAll('button')[1]!
      expect(submitButton.attributes('disabled')).toBeUndefined()
    })

    it('should enable cancel button when content is not empty', async () => {
      const wrapper = mount(PostReplyForm)
      const textarea = wrapper.find('textarea')
      await textarea.setValue('Some reply content')

      const cancelButton = wrapper.findAll('button')[0]!
      expect(cancelButton.attributes('disabled')).toBeUndefined()
    })
  })

  describe('submit', () => {
    it('should emit submit with trimmed content', async () => {
      const wrapper = mount(PostReplyForm)
      const textarea = wrapper.find('textarea')
      await textarea.setValue('  Reply content with spaces  ')

      const submitButton = wrapper.findAll('button')[1]!
      await submitButton.trigger('click')

      expect(wrapper.emitted('submit')).toBeTruthy()
      expect(wrapper.emitted('submit')![0]).toEqual(['Reply content with spaces'])
    })

    it('should not emit submit when content is empty', async () => {
      const wrapper = mount(PostReplyForm)
      const submitButton = wrapper.findAll('button')[1]!
      await submitButton.trigger('click')

      expect(wrapper.emitted('submit')).toBeFalsy()
    })
  })

  describe('cancel', () => {
    it('should show confirmation when canceling with content', async () => {
      const confirmMock = vi.fn(() => false)
      vi.stubGlobal('confirm', confirmMock)

      const wrapper = mount(PostReplyForm)
      const textarea = wrapper.find('textarea')
      await textarea.setValue('Some content')

      const cancelButton = wrapper.findAll('button')[0]!
      await cancelButton.trigger('click')

      expect(confirmMock).toHaveBeenCalled()
      expect(wrapper.emitted('cancel')).toBeFalsy()

      vi.unstubAllGlobals()
    })

    it('should emit cancel and clear content when confirmation is accepted', async () => {
      const confirmMock = vi.fn(() => true)
      vi.stubGlobal('confirm', confirmMock)

      const wrapper = mount(PostReplyForm)
      const textarea = wrapper.find('textarea')
      await textarea.setValue('Some content')

      const cancelButton = wrapper.findAll('button')[0]!
      await cancelButton.trigger('click')

      expect(wrapper.emitted('cancel')).toBeTruthy()
      expect(textarea.element.value).toBe('')

      vi.unstubAllGlobals()
    })
  })

  describe('exposed methods', () => {
    it('should clear content with clear()', async () => {
      const wrapper = mount(PostReplyForm)
      const textarea = wrapper.find('textarea')
      await textarea.setValue('Some content')

      const vm = wrapper.vm as InstanceType<typeof PostReplyForm>
      vm.clear()
      await wrapper.vm.$nextTick()

      expect(textarea.element.value).toBe('')
    })

    it('should set submitting state with setSubmitting()', async () => {
      const wrapper = mount(PostReplyForm)
      const textarea = wrapper.find('textarea')
      await textarea.setValue('Some content')

      const vm = wrapper.vm as InstanceType<typeof PostReplyForm>
      vm.setSubmitting(true)
      await wrapper.vm.$nextTick()

      expect(wrapper.find('textarea').attributes('disabled')).toBeDefined()
      expect(wrapper.find('.loading-icon-mock').exists()).toBe(true)

      vm.setSubmitting(false)
      await wrapper.vm.$nextTick()

      expect(wrapper.find('textarea').attributes('disabled')).toBeUndefined()
    })

    it('should set quoted content with setQuotedContent()', async () => {
      const wrapper = mount(PostReplyForm)

      const vm = wrapper.vm as InstanceType<typeof PostReplyForm>
      vm.setQuotedContent('Original message')
      await wrapper.vm.$nextTick()

      const textarea = wrapper.find('textarea')
      expect(textarea.element.value).toBe('[quote]Original message[/quote]\n')
    })
  })

  describe('submitting state', () => {
    it('should disable editor when submitting', async () => {
      const wrapper = mount(PostReplyForm)
      const textarea = wrapper.find('textarea')
      await textarea.setValue('Reply content')

      const submitButton = wrapper.findAll('button')[1]!
      await submitButton.trigger('click')

      expect(wrapper.find('textarea').attributes('disabled')).toBeDefined()
    })

    it('should show loading icon when submitting', async () => {
      const wrapper = mount(PostReplyForm)
      const textarea = wrapper.find('textarea')
      await textarea.setValue('Reply content')

      const submitButton = wrapper.findAll('button')[1]!
      await submitButton.trigger('click')

      expect(wrapper.find('.loading-icon-mock').exists()).toBe(true)
    })
  })
})
