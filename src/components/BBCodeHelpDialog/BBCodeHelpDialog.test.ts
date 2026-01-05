import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { BBCode } from '@/types'

// Mock axios - must use factory that doesn't reference external variables
vi.mock('@/axios', () => ({
  ocs: {
    get: vi.fn(),
  },
}))

// Import after mock
import { ocs } from '@/axios'
import BBCodeHelpDialog from './BBCodeHelpDialog.vue'

const mockGet = vi.mocked(ocs.get)

describe('BBCodeHelpDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGet.mockResolvedValue({ data: [] } as never)
  })

  const createWrapper = (props = {}) => {
    return mount(BBCodeHelpDialog, {
      props: {
        open: true,
        showCustom: true,
        ...props,
      },
    })
  }

  describe('rendering', () => {
    it('renders the dialog when open', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.nc-dialog').exists()).toBe(true)
    })

    it('renders built-in BBCodes section', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.bbcode-section').exists()).toBe(true)
      expect(wrapper.text()).toContain('Built-in BBCodes')
    })

    it('renders all built-in BBCode tags', () => {
      const wrapper = createWrapper()
      const tags = wrapper.findAll('.bbcode-tag')

      // Check for some expected built-in tags
      const tagTexts = tags.map((t) => t.text())
      expect(tagTexts).toContain('[b]')
      expect(tagTexts).toContain('[i]')
      expect(tagTexts).toContain('[code]')
      expect(tagTexts).toContain('[url]')
      expect(tagTexts).toContain('[img]')
      expect(tagTexts).toContain('[quote]')
    })

    it('renders BBCode examples', () => {
      const wrapper = createWrapper()
      const examples = wrapper.findAll('.example-code')
      expect(examples.length).toBeGreaterThan(0)
      // Check for a specific example
      expect(wrapper.text()).toContain('[b]Hello world![/b]')
    })

    it('renders custom BBCodes section when showCustom is true', () => {
      const wrapper = createWrapper({ showCustom: true })
      expect(wrapper.text()).toContain('Custom BBCodes')
    })

    it('does not render custom BBCodes section when showCustom is false', () => {
      const wrapper = createWrapper({ showCustom: false })
      expect(wrapper.text()).not.toContain('Custom BBCodes')
    })
  })

  describe('fetching builtin DB codes', () => {
    it('fetches builtin codes when dialog opens', async () => {
      createWrapper({ open: true })
      await flushPromises()

      expect(mockGet).toHaveBeenCalledWith('/bbcodes/builtin')
    })

    it('displays builtin DB codes', async () => {
      const builtinCodes: BBCode[] = [
        {
          id: 1,
          tag: 'spoiler',
          replacement: '<span class="spoiler">{content}</span>',
          example: '[spoiler]Hidden text[/spoiler]',
          description: 'Spoiler text',
          enabled: true,
          parseInner: true,
          isBuiltin: true,
          specialHandler: null,
          createdAt: Date.now(),
        },
      ]
      mockGet.mockImplementation((url: string) => {
        if (url === '/bbcodes/builtin') {
          return Promise.resolve({ data: builtinCodes }) as never
        }
        return Promise.resolve({ data: [] }) as never
      })

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      expect(wrapper.text()).toContain('[spoiler]')
      expect(wrapper.text()).toContain('Spoiler text')
    })

    it('silently fails when builtin codes fetch fails', async () => {
      const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
      mockGet.mockImplementation((url: string) => {
        if (url === '/bbcodes/builtin') {
          return Promise.reject(new Error('Network error'))
        }
        return Promise.resolve({ data: [] }) as never
      })

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      // Should not show error state for builtin codes
      expect(wrapper.find('.error-state').exists()).toBe(false)
      expect(consoleError).toHaveBeenCalled()
      consoleError.mockRestore()
    })
  })

  describe('fetching custom codes', () => {
    it('fetches custom codes when dialog opens with showCustom true', async () => {
      createWrapper({ open: true, showCustom: true })
      await flushPromises()

      expect(mockGet).toHaveBeenCalledWith('/bbcodes')
    })

    it('does not fetch custom codes when showCustom is false', async () => {
      createWrapper({ open: true, showCustom: false })
      await flushPromises()

      expect(mockGet).not.toHaveBeenCalledWith('/bbcodes')
    })

    it('displays loading state while fetching custom codes', async () => {
      let resolvePromise: (value: unknown) => void
      mockGet.mockImplementation((url: string) => {
        if (url === '/bbcodes') {
          return new Promise((resolve) => {
            resolvePromise = resolve
          }) as never
        }
        return Promise.resolve({ data: [] }) as never
      })

      const wrapper = createWrapper({ open: true, showCustom: true })
      await flushPromises()

      expect(wrapper.find('.loading-state').exists()).toBe(true)
      expect(wrapper.text()).toContain('Loading custom BBCodes')

      resolvePromise!({ data: [] })
      await flushPromises()

      expect(wrapper.find('.loading-state').exists()).toBe(false)
    })

    it('displays custom codes after fetch', async () => {
      const customCodes: BBCode[] = [
        {
          id: 10,
          tag: 'highlight',
          replacement: '<mark>{content}</mark>',
          example: '[highlight]Important text[/highlight]',
          description: 'Highlight text',
          enabled: true,
          parseInner: true,
          isBuiltin: false,
          specialHandler: null,
          createdAt: Date.now(),
        },
      ]
      mockGet.mockImplementation((url: string) => {
        if (url === '/bbcodes') {
          return Promise.resolve({ data: customCodes }) as never
        }
        return Promise.resolve({ data: [] }) as never
      })

      const wrapper = createWrapper({ open: true, showCustom: true })
      await flushPromises()

      expect(wrapper.text()).toContain('[highlight]')
      expect(wrapper.text()).toContain('Highlight text')
    })

    it('filters out disabled custom codes', async () => {
      const customCodes: BBCode[] = [
        {
          id: 10,
          tag: 'enabled',
          replacement: '<span>{content}</span>',
          example: '[enabled]Text[/enabled]',
          description: 'Enabled code',
          enabled: true,
          parseInner: true,
          isBuiltin: false,
          specialHandler: null,
          createdAt: Date.now(),
        },
        {
          id: 11,
          tag: 'disabled',
          replacement: '<span>{content}</span>',
          example: '[disabled]Text[/disabled]',
          description: 'Disabled code',
          enabled: false,
          parseInner: true,
          isBuiltin: false,
          specialHandler: null,
          createdAt: Date.now(),
        },
      ]
      mockGet.mockImplementation((url: string) => {
        if (url === '/bbcodes') {
          return Promise.resolve({ data: customCodes }) as never
        }
        return Promise.resolve({ data: [] }) as never
      })

      const wrapper = createWrapper({ open: true, showCustom: true })
      await flushPromises()

      expect(wrapper.text()).toContain('[enabled]')
      expect(wrapper.text()).not.toContain('[disabled]')
    })

    it('displays empty state when no custom codes exist', async () => {
      mockGet.mockResolvedValue({ data: [] } as never)

      const wrapper = createWrapper({ open: true, showCustom: true })
      await flushPromises()

      expect(wrapper.find('.empty-state').exists()).toBe(true)
      expect(wrapper.text()).toContain('No custom BBCodes configured')
    })

    it('displays error state when fetch fails', async () => {
      mockGet.mockImplementation((url: string) => {
        if (url === '/bbcodes') {
          return Promise.reject(new Error('Network error'))
        }
        return Promise.resolve({ data: [] }) as never
      })
      vi.spyOn(console, 'error').mockImplementation(() => {})

      const wrapper = createWrapper({ open: true, showCustom: true })
      await flushPromises()

      expect(wrapper.find('.error-state').exists()).toBe(true)
      expect(wrapper.text()).toContain('Failed to load custom BBCodes')
    })
  })

  describe('caching', () => {
    it('does not refetch builtin codes if already loaded when reopening', async () => {
      // Mock returns data
      const builtinCodes: BBCode[] = [
        {
          id: 1,
          tag: 'test',
          replacement: '<span>{content}</span>',
          example: '[test]Hello[/test]',
          description: 'Test',
          enabled: true,
          parseInner: true,
          isBuiltin: true,
          specialHandler: null,
          createdAt: Date.now(),
        },
      ]
      mockGet.mockImplementation((url: string) => {
        if (url === '/bbcodes/builtin') {
          return Promise.resolve({ data: builtinCodes }) as never
        }
        return Promise.resolve({ data: [] }) as never
      })

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const callCount = mockGet.mock.calls.filter((c) => c[0] === '/bbcodes/builtin').length
      expect(callCount).toBe(1)

      // Close and reopen - since builtinDbCodes.length > 0, should not refetch
      await wrapper.setProps({ open: false })
      await wrapper.setProps({ open: true })
      await flushPromises()

      const newCallCount = mockGet.mock.calls.filter((c) => c[0] === '/bbcodes/builtin').length
      expect(newCallCount).toBe(1) // Should still be 1
    })

    it('does not refetch custom codes if already loaded when reopening', async () => {
      // Mock returns data for custom codes
      const customCodes: BBCode[] = [
        {
          id: 10,
          tag: 'custom',
          replacement: '<span>{content}</span>',
          example: '[custom]Hello[/custom]',
          description: 'Custom',
          enabled: true,
          parseInner: true,
          isBuiltin: false,
          specialHandler: null,
          createdAt: Date.now(),
        },
      ]
      mockGet.mockImplementation((url: string) => {
        if (url === '/bbcodes') {
          return Promise.resolve({ data: customCodes }) as never
        }
        return Promise.resolve({ data: [] }) as never
      })

      const wrapper = createWrapper({ open: true, showCustom: true })
      await flushPromises()

      const callCount = mockGet.mock.calls.filter((c) => c[0] === '/bbcodes').length
      expect(callCount).toBe(1)

      // Close and reopen - since customCodes.length > 0, should not refetch
      await wrapper.setProps({ open: false })
      await wrapper.setProps({ open: true })
      await flushPromises()

      const newCallCount = mockGet.mock.calls.filter((c) => c[0] === '/bbcodes').length
      expect(newCallCount).toBe(1) // Should still be 1
    })
  })

  describe('close event', () => {
    it('emits update:open event when dialog closes', async () => {
      const wrapper = createWrapper({ open: true })

      ;(wrapper.vm as unknown as { handleClose: (v: boolean) => void }).handleClose(false)

      expect(wrapper.emitted('update:open')).toBeTruthy()
      expect(wrapper.emitted('update:open')![0]).toEqual([false])
    })
  })

  describe('built-in codes content', () => {
    it('contains bold tag example', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('Font style bold')
    })

    it('contains italic tag example', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('Font style italic')
    })

    it('contains code tag example', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('[code]')
      expect(wrapper.text()).toContain('Code')
    })

    it('contains email tag example', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('[email]')
      expect(wrapper.text()).toContain('Email (clickable)')
    })

    it('contains url tag example', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('[url=http://example.com]')
      expect(wrapper.text()).toContain('URL (clickable)')
    })

    it('contains image tag example', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('[img]')
      expect(wrapper.text()).toContain('Image (not clickable)')
    })

    it('contains quote tag example', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('[quote]')
      expect(wrapper.text()).toContain('Quote')
    })

    it('contains youtube tag example', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('[youtube]')
      expect(wrapper.text()).toContain('Embedded YouTube video')
    })

    it('contains list tags examples', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('[list]')
      expect(wrapper.text()).toContain('List')
    })

    it('contains alignment tag examples', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('[left]')
      expect(wrapper.text()).toContain('[center]')
      expect(wrapper.text()).toContain('[right]')
    })
  })
})
