import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { handleAttachmentClick } from './attachmentViewer'

describe('handleAttachmentClick', () => {
  const open = vi.fn()
  let link: HTMLAnchorElement

  const click = (init: MouseEventInit = {}) => {
    const event = new MouseEvent('click', { bubbles: true, cancelable: true, ...init })
    link.dispatchEvent(event)
    return event
  }

  beforeEach(() => {
    open.mockClear()
    ;(window as unknown as Record<string, unknown>).OCA = {
      Viewer: { mimetypes: ['application/pdf'], open },
    }
    document.body.innerHTML =
      '<a href="https://example.com/file" class="attachment-name" data-viewer-mime="application/pdf" data-file-id="45">report.pdf</a>'
    link = document.querySelector('a')!
    document.addEventListener('click', handleAttachmentClick)
  })

  afterEach(() => {
    document.removeEventListener('click', handleAttachmentClick)
    delete (window as unknown as Record<string, unknown>).OCA
  })

  it('should open the attachment in the Viewer through the forum endpoint', () => {
    const event = click()

    const fileInfo = {
      fileid: 45,
      filename: '/report.pdf',
      basename: 'report.pdf',
      mime: 'application/pdf',
      source: 'https://example.com/file',
    }
    expect(event.defaultPrevented).toBe(true)
    expect(open).toHaveBeenCalledWith({ fileInfo, list: [fileInfo], enableSidebar: false })
  })

  it('should follow the link without the Viewer', () => {
    delete (window as unknown as Record<string, unknown>).OCA
    expect(click().defaultPrevented).toBe(false)
  })

  it('should follow the link when the Viewer has no handler for the file', () => {
    ;(window as unknown as Record<string, unknown>).OCA = { Viewer: { mimetypes: [], open } }
    expect(click().defaultPrevented).toBe(false)
    expect(open).not.toHaveBeenCalled()
  })

  it('should leave modified clicks to the browser', () => {
    expect(click({ ctrlKey: true }).defaultPrevented).toBe(false)
    expect(click({ metaKey: true }).defaultPrevented).toBe(false)
    expect(open).not.toHaveBeenCalled()
  })

  it('should ignore other links', () => {
    link.removeAttribute('data-viewer-mime')
    expect(click().defaultPrevented).toBe(false)
  })
})
