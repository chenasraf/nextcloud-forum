import { describe, it, expect, vi, beforeEach } from 'vitest'

const isRTL = vi.fn(() => false)
vi.mock('@nextcloud/l10n', () => ({ isRTL: () => isRTL() }))

import {
  useNavigationWidth,
  DEFAULT_NAVIGATION_WIDTH,
  MIN_NAVIGATION_WIDTH,
  MAX_NAVIGATION_WIDTH,
} from '../useNavigationWidth'

const STORAGE_KEY = 'forum_navigation_width'

function drag(nav: ReturnType<typeof useNavigationWidth>, fromX: number, toX: number) {
  nav.onPointerDown({
    button: 0,
    pointerId: 1,
    clientX: fromX,
    currentTarget: { setPointerCapture: vi.fn() },
  } as unknown as PointerEvent)
  nav.onPointerMove({ clientX: toX } as PointerEvent)
  nav.onPointerUp()
}

describe('useNavigationWidth', () => {
  beforeEach(() => {
    localStorage.clear()
    isRTL.mockReturnValue(false)
    vi.stubGlobal('innerWidth', 1920)
  })

  it('should start at the default width', () => {
    expect(useNavigationWidth().width.value).toBe(DEFAULT_NAVIGATION_WIDTH)
  })

  it('should restore a saved width', () => {
    localStorage.setItem(STORAGE_KEY, '420')
    expect(useNavigationWidth().width.value).toBe(420)
  })

  it('should fall back to the default for an invalid saved width', () => {
    localStorage.setItem(STORAGE_KEY, 'wide')
    expect(useNavigationWidth().width.value).toBe(DEFAULT_NAVIGATION_WIDTH)
  })

  it('should follow a drag and save the result on release', () => {
    const nav = useNavigationWidth()
    drag(nav, 300, 400)
    expect(nav.width.value).toBe(400)
    expect(nav.isResizing.value).toBe(false)
    expect(localStorage.getItem(STORAGE_KEY)).toBe('400')
  })

  it('should invert the drag direction in RTL', () => {
    isRTL.mockReturnValue(true)
    const nav = useNavigationWidth()
    drag(nav, 500, 400)
    expect(nav.width.value).toBe(400)
  })

  it('should ignore pointer movement without a drag', () => {
    const nav = useNavigationWidth()
    nav.onPointerMove({ clientX: 900 } as PointerEvent)
    expect(nav.width.value).toBe(DEFAULT_NAVIGATION_WIDTH)
  })

  it('should stay within the minimum and maximum', () => {
    const nav = useNavigationWidth()
    drag(nav, 300, 0)
    expect(nav.width.value).toBe(MIN_NAVIGATION_WIDTH)
    drag(nav, 0, 5000)
    expect(nav.width.value).toBe(MAX_NAVIGATION_WIDTH)
  })

  it('should not exceed half of a narrow window', () => {
    vi.stubGlobal('innerWidth', 1000)
    const nav = useNavigationWidth()
    drag(nav, 300, 5000)
    expect(nav.width.value).toBe(500)
  })

  it('should resize with the keyboard', () => {
    const nav = useNavigationWidth()
    const press = (key: string) =>
      nav.onKeydown({ key, preventDefault: vi.fn() } as unknown as KeyboardEvent)

    press('ArrowRight')
    expect(nav.width.value).toBe(DEFAULT_NAVIGATION_WIDTH + 16)
    press('ArrowLeft')
    expect(nav.width.value).toBe(DEFAULT_NAVIGATION_WIDTH)
    press('Home')
    expect(nav.width.value).toBe(MIN_NAVIGATION_WIDTH)
    press('End')
    expect(nav.width.value).toBe(MAX_NAVIGATION_WIDTH)
    expect(localStorage.getItem(STORAGE_KEY)).toBe(String(MAX_NAVIGATION_WIDTH))
  })

  it('should forget the saved width on reset', () => {
    const nav = useNavigationWidth()
    drag(nav, 300, 400)
    nav.reset()
    expect(nav.width.value).toBe(DEFAULT_NAVIGATION_WIDTH)
    expect(localStorage.getItem(STORAGE_KEY)).toBeNull()
  })
})
