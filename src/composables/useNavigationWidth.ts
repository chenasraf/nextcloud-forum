import { ref } from 'vue'
import { isRTL } from '@nextcloud/l10n'

export const DEFAULT_NAVIGATION_WIDTH = 300
export const MIN_NAVIGATION_WIDTH = 200
export const MAX_NAVIGATION_WIDTH = 600
const KEYBOARD_STEP = 16
const STORAGE_KEY = 'forum_navigation_width'

function clamp(width: number): number {
  // Half the window at most, so a wide sidebar leaves room for the content
  const max = Math.min(MAX_NAVIGATION_WIDTH, window.innerWidth / 2)
  return Math.round(Math.max(MIN_NAVIGATION_WIDTH, Math.min(max, width)))
}

function loadWidth(): number {
  try {
    const saved = Number(localStorage.getItem(STORAGE_KEY))
    return saved > 0 ? clamp(saved) : DEFAULT_NAVIGATION_WIDTH
  } catch {
    return DEFAULT_NAVIGATION_WIDTH
  }
}

function saveWidth(width: number | null): void {
  try {
    if (width === null) {
      localStorage.removeItem(STORAGE_KEY)
    } else {
      localStorage.setItem(STORAGE_KEY, String(width))
    }
  } catch (e) {
    console.error('Failed to save navigation width to local storage:', e)
  }
}

/**
 * Width of the navigation sidebar, driven by a drag handle on its edge and remembered per browser.
 */
export function useNavigationWidth() {
  const width = ref(loadWidth())
  const isResizing = ref(false)
  let dragStartX = 0
  let dragStartWidth = 0

  function onPointerDown(event: PointerEvent): void {
    if (event.button !== 0) {
      return
    }
    ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)
    dragStartX = event.clientX
    dragStartWidth = width.value
    isResizing.value = true
  }

  function onPointerMove(event: PointerEvent): void {
    if (!isResizing.value) {
      return
    }
    // The sidebar sits on the right in RTL layouts, where dragging left widens it
    const delta = event.clientX - dragStartX
    width.value = clamp(dragStartWidth + (isRTL() ? -delta : delta))
  }

  function onPointerUp(): void {
    if (!isResizing.value) {
      return
    }
    isResizing.value = false
    saveWidth(width.value)
  }

  function onKeydown(event: KeyboardEvent): void {
    const step = isRTL() ? -KEYBOARD_STEP : KEYBOARD_STEP
    const targets: Record<string, number> = {
      ArrowLeft: width.value - step,
      ArrowRight: width.value + step,
      Home: MIN_NAVIGATION_WIDTH,
      End: MAX_NAVIGATION_WIDTH,
    }
    const target = targets[event.key]
    if (target === undefined) {
      return
    }
    event.preventDefault()
    width.value = clamp(target)
    saveWidth(width.value)
  }

  function reset(): void {
    width.value = DEFAULT_NAVIGATION_WIDTH
    saveWidth(null)
  }

  return { width, isResizing, onPointerDown, onPointerMove, onPointerUp, onKeydown, reset }
}
