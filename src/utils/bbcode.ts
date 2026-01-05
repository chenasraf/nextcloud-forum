/**
 * BBCode text manipulation utilities.
 *
 * These functions handle pure string/selection operations for BBCode insertion,
 * independent of DOM or Vue component logic.
 */

/**
 * Selection state in a text editor
 */
export interface TextSelection {
  /** Full text content */
  text: string
  /** Start position of selection (0-indexed) */
  start: number
  /** End position of selection (0-indexed) */
  end: number
}

/**
 * Editor state with value and selection info
 */
export interface EditorState {
  /** Full text content */
  value: string
  /** Start position of selection (0-indexed) */
  start: number
  /** End position of selection (0-indexed) */
  end: number
  /** The selected text */
  selectedText: string
}

/**
 * Result of a BBCode insertion operation
 */
export interface InsertionResult {
  /** The new full text after insertion */
  text: string
  /** The cursor position after insertion */
  cursorPosition: number
}

/**
 * BBCode template configuration
 */
export interface BBCodeTemplate {
  /** The BBCode template string with {text} and optional {value} placeholders */
  template: string
  /** Optional value to substitute for {value} placeholder */
  value?: string
  /** Fallback text if no text is selected */
  fallbackText?: string
}

/**
 * Get the selected text from a selection state.
 *
 * @param selection - The text selection state
 * @returns The selected text (empty string if no selection)
 */
export function getSelectedText(selection: TextSelection): string {
  if (selection.start === selection.end) {
    return ''
  }
  return selection.text.substring(selection.start, selection.end)
}

/**
 * Apply a BBCode template to a text selection.
 *
 * This function:
 * 1. Takes the current text and selection
 * 2. Replaces the selected text with the BBCode-wrapped version
 * 3. Returns the new text and cursor position
 *
 * Template placeholders:
 * - {text}: Replaced with selected text (or fallbackText if nothing selected)
 * - {value}: Replaced with the provided value (for tags like [url=...], [color=...])
 *
 * @param selection - Current text selection state
 * @param template - BBCode template configuration
 * @returns The insertion result with new text and cursor position
 *
 * @example
 * // Simple wrap with [b] tags
 * applyBBCodeTemplate(
 *   { text: 'Hello world', start: 6, end: 11 },
 *   { template: '[b]{text}[/b]' }
 * )
 * // Returns: { text: 'Hello [b]world[/b]', cursorPosition: 18 }
 *
 * @example
 * // URL with value
 * applyBBCodeTemplate(
 *   { text: 'Check this', start: 6, end: 10 },
 *   { template: '[url={value}]{text}[/url]', value: 'http://example.com' }
 * )
 * // Returns: { text: 'Check [url=http://example.com]this[/url]', cursorPosition: 40 }
 */
export function applyBBCodeTemplate(
  selection: TextSelection,
  template: BBCodeTemplate,
): InsertionResult {
  const { text, start, end } = selection
  const selectedText = getSelectedText(selection)

  const beforeText = text.substring(0, start)
  const afterText = text.substring(end)

  // Build the BBCode string from template
  const contentText = selectedText || template.fallbackText || ''
  const insertText = template.template
    .replace('{value}', template.value || '')
    .replace('{text}', contentText)

  const newText = beforeText + insertText + afterText
  const cursorPosition = beforeText.length + insertText.length

  return {
    text: newText,
    cursorPosition,
  }
}

/**
 * Insert text at the current selection position.
 *
 * This replaces any selected text with the new text.
 *
 * @param selection - Current text selection state
 * @param insertText - Text to insert
 * @returns The insertion result with new text and cursor position
 *
 * @example
 * insertTextAtSelection(
 *   { text: 'Hello world', start: 5, end: 5 },
 *   ' beautiful'
 * )
 * // Returns: { text: 'Hello beautiful world', cursorPosition: 15 }
 */
export function insertTextAtSelection(
  selection: TextSelection,
  insertText: string,
): InsertionResult {
  const { text, start, end } = selection

  const beforeText = text.substring(0, start)
  const afterText = text.substring(end)

  const newText = beforeText + insertText + afterText
  const cursorPosition = beforeText.length + insertText.length

  return {
    text: newText,
    cursorPosition,
  }
}

/**
 * Wrap selected text with opening and closing strings.
 *
 * This is a convenience function for simple wrapping operations.
 *
 * @param selection - Current text selection state
 * @param openTag - Opening string (e.g., '[b]')
 * @param closeTag - Closing string (e.g., '[/b]')
 * @param fallbackText - Text to use if nothing is selected
 * @returns The insertion result with new text and cursor position
 *
 * @example
 * wrapSelection(
 *   { text: 'Hello world', start: 6, end: 11 },
 *   '[b]',
 *   '[/b]'
 * )
 * // Returns: { text: 'Hello [b]world[/b]', cursorPosition: 18 }
 */
export function wrapSelection(
  selection: TextSelection,
  openTag: string,
  closeTag: string,
  fallbackText = '',
): InsertionResult {
  return applyBBCodeTemplate(selection, {
    template: `${openTag}{text}${closeTag}`,
    fallbackText,
  })
}

/**
 * Calculate cursor position after inserting BBCode.
 *
 * When inserting BBCode without selected text, the cursor should be placed
 * between the opening and closing tags so the user can type content.
 *
 * @param selection - Current text selection state
 * @param openTag - Opening BBCode tag
 * @param closeTag - Closing BBCode tag
 * @returns Cursor position between the tags
 *
 * @example
 * getCursorPositionBetweenTags(
 *   { text: 'Hello ', start: 6, end: 6 },
 *   '[b]',
 *   '[/b]'
 * )
 * // Returns: 9 (position right after '[b]')
 */
export function getCursorPositionBetweenTags(
  selection: TextSelection,
  openTag: string,
  _closeTag: string,
): number {
  return selection.start + openTag.length
}

/**
 * Check if a BBCode tag is already applied around the selection.
 *
 * This checks if the text immediately before and after the selection
 * contains the specified opening and closing tags.
 *
 * @param selection - Current text selection state
 * @param openTag - Opening BBCode tag (e.g., '[b]')
 * @param closeTag - Closing BBCode tag (e.g., '[/b]')
 * @returns True if the selection is wrapped with the tags
 *
 * @example
 * isSelectionWrapped(
 *   { text: 'Hello [b]world[/b] there', start: 9, end: 14 },
 *   '[b]',
 *   '[/b]'
 * )
 * // Returns: true
 */
export function isSelectionWrapped(
  selection: TextSelection,
  openTag: string,
  closeTag: string,
): boolean {
  const { text, start, end } = selection

  // Check if there's enough text before and after for the tags
  if (start < openTag.length || end + closeTag.length > text.length) {
    return false
  }

  const beforeSelection = text.substring(start - openTag.length, start)
  const afterSelection = text.substring(end, end + closeTag.length)

  return beforeSelection === openTag && afterSelection === closeTag
}

/**
 * Remove BBCode tags from around the selection.
 *
 * This is the inverse of wrapSelection - it removes the tags if they exist.
 *
 * @param selection - Current text selection state
 * @param openTag - Opening BBCode tag to remove
 * @param closeTag - Closing BBCode tag to remove
 * @returns The result with tags removed, or unchanged if tags weren't present
 *
 * @example
 * unwrapSelection(
 *   { text: 'Hello [b]world[/b] there', start: 9, end: 14 },
 *   '[b]',
 *   '[/b]'
 * )
 * // Returns: { text: 'Hello world there', cursorPosition: 11 }
 */
export function unwrapSelection(
  selection: TextSelection,
  openTag: string,
  closeTag: string,
): InsertionResult {
  if (!isSelectionWrapped(selection, openTag, closeTag)) {
    // Not wrapped, return unchanged
    return {
      text: selection.text,
      cursorPosition: selection.end,
    }
  }

  const { text, start, end } = selection
  const selectedText = getSelectedText(selection)

  // Remove the tags
  const beforeText = text.substring(0, start - openTag.length)
  const afterText = text.substring(end + closeTag.length)

  const newText = beforeText + selectedText + afterText
  const cursorPosition = beforeText.length + selectedText.length

  return {
    text: newText,
    cursorPosition,
  }
}

/**
 * Toggle BBCode tags around the selection.
 *
 * If the selection is already wrapped, unwrap it.
 * If not, wrap it with the tags.
 *
 * @param selection - Current text selection state
 * @param openTag - Opening BBCode tag
 * @param closeTag - Closing BBCode tag
 * @param fallbackText - Text to use if nothing is selected (when wrapping)
 * @returns The result with tags toggled
 *
 * @example
 * // Wrap unformatted text
 * toggleBBCodeTags(
 *   { text: 'Hello world', start: 6, end: 11 },
 *   '[b]',
 *   '[/b]'
 * )
 * // Returns: { text: 'Hello [b]world[/b]', cursorPosition: 18 }
 *
 * @example
 * // Unwrap already formatted text
 * toggleBBCodeTags(
 *   { text: 'Hello [b]world[/b]', start: 9, end: 14 },
 *   '[b]',
 *   '[/b]'
 * )
 * // Returns: { text: 'Hello world', cursorPosition: 11 }
 */
export function toggleBBCodeTags(
  selection: TextSelection,
  openTag: string,
  closeTag: string,
  fallbackText = '',
): InsertionResult {
  if (isSelectionWrapped(selection, openTag, closeTag)) {
    return unwrapSelection(selection, openTag, closeTag)
  }
  return wrapSelection(selection, openTag, closeTag, fallbackText)
}

// =============================================================================
// DOM-based editor utilities
// =============================================================================

/**
 * Check if an element is a textarea
 */
export function isTextarea(el: HTMLElement): el is HTMLTextAreaElement {
  return el.tagName === 'TEXTAREA'
}

/**
 * Get editor state from a textarea element
 *
 * @param textarea - The textarea element
 * @returns Editor state with value and selection info
 */
export function getEditorStateFromTextarea(textarea: HTMLTextAreaElement): EditorState {
  const start = textarea.selectionStart
  const end = textarea.selectionEnd
  return {
    value: textarea.value,
    start,
    end,
    selectedText: textarea.value.substring(start, end),
  }
}

/**
 * Get editor state from a contenteditable element
 *
 * @param element - The contenteditable element
 * @param modelValue - The model value (source of truth for text content)
 * @returns Editor state with value and selection info
 */
export function getEditorStateFromContenteditable(
  element: HTMLElement,
  modelValue: string,
): EditorState {
  // Remove zero-width spaces that may be added for cursor positioning
  const text = (modelValue || '').replace(/\u200B/g, '')
  const selection = window.getSelection()

  if (!selection || selection.rangeCount === 0) {
    return { value: text, start: text.length, end: text.length, selectedText: '' }
  }

  const range = selection.getRangeAt(0)

  // Check if selection is within this element
  if (!element.contains(range.commonAncestorContainer)) {
    return { value: text, start: text.length, end: text.length, selectedText: '' }
  }

  // Get the selected text from the DOM range
  const domSelectedText = range.toString()

  if (!domSelectedText) {
    // No selection - put cursor at end of text
    return { value: text, start: text.length, end: text.length, selectedText: '' }
  }

  // Find the selected text in the modelValue
  // The DOM selection text should match exactly what's in the model
  // We search for it to get the correct position
  const trimmedSelection = domSelectedText.trim()

  if (!trimmedSelection) {
    return { value: text, start: text.length, end: text.length, selectedText: '' }
  }

  // Find the position of the trimmed selection in the model
  const foundIndex = text.indexOf(trimmedSelection)

  if (foundIndex === -1) {
    // Selected text not found - append at end
    return { value: text, start: text.length, end: text.length, selectedText: '' }
  }

  // If there are multiple occurrences, find the best match using DOM position estimate
  let start = foundIndex
  const nextIndex = text.indexOf(trimmedSelection, foundIndex + 1)

  if (nextIndex !== -1) {
    // Multiple occurrences - use DOM position to pick the closest one
    const preCaretRange = range.cloneRange()
    preCaretRange.selectNodeContents(element)
    preCaretRange.setEnd(range.startContainer, range.startOffset)
    const domStartEstimate = preCaretRange.toString().length

    // Check all occurrences and pick closest to DOM estimate
    let bestMatch = foundIndex
    let bestDiff = Math.abs(foundIndex - domStartEstimate)

    let idx = foundIndex
    while (idx !== -1) {
      const diff = Math.abs(idx - domStartEstimate)
      if (diff < bestDiff) {
        bestDiff = diff
        bestMatch = idx
      }
      idx = text.indexOf(trimmedSelection, idx + 1)
    }
    start = bestMatch
  }

  const end = start + trimmedSelection.length

  return {
    value: text,
    start,
    end,
    selectedText: trimmedSelection,
  }
}

/**
 * Get editor state from either a textarea or contenteditable element
 *
 * @param element - The editor element (textarea or contenteditable)
 * @param modelValue - The model value (used for contenteditable elements)
 * @returns Editor state with value and selection info, or null if element is null
 */
export function getEditorState(
  element: HTMLTextAreaElement | HTMLElement | null,
  modelValue = '',
): EditorState | null {
  if (!element) {
    return null
  }

  if (isTextarea(element)) {
    return getEditorStateFromTextarea(element)
  }
  return getEditorStateFromContenteditable(element, modelValue)
}

/**
 * Set cursor position in a textarea element
 *
 * @param textarea - The textarea element
 * @param position - The cursor position to set
 */
export function setCursorInTextarea(textarea: HTMLTextAreaElement, position: number): void {
  textarea.setSelectionRange(position, position)
}

/**
 * Set cursor position in a contenteditable element
 *
 * @param element - The contenteditable element
 * @param position - The cursor position to set (in visible characters, excluding zero-width spaces)
 */
export function setCursorInContenteditable(element: HTMLElement, position: number): void {
  const selection = window.getSelection()
  if (!selection) return

  // Find the text node at the position, accounting for zero-width spaces
  const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null)
  let currentPos = 0
  let node: Node | null = walker.nextNode()

  while (node) {
    const nodeText = node.textContent || ''
    // Count only visible characters (exclude zero-width spaces for position calculation)
    const visibleLength = nodeText.replace(/\u200B/g, '').length
    const actualLength = nodeText.length

    if (currentPos + visibleLength >= position) {
      // Found the node containing our position
      // Calculate offset within this node, accounting for zero-width spaces
      let targetOffset = position - currentPos
      let actualOffset = 0

      // Map visible position to actual offset (skipping zero-width spaces)
      for (let i = 0; i < actualLength && targetOffset > 0; i++) {
        if (nodeText[i] !== '\u200B') {
          targetOffset--
        }
        actualOffset++
      }

      const range = document.createRange()
      try {
        range.setStart(node, Math.min(actualOffset, actualLength))
        range.collapse(true)
        selection.removeAllRanges()
        selection.addRange(range)
      } catch {
        // If setting range fails, fall through to end placement
      }
      return
    }
    currentPos += visibleLength
    node = walker.nextNode()
  }

  // If we couldn't find the position, put cursor at end
  const range = document.createRange()
  range.selectNodeContents(element)
  range.collapse(false)
  selection.removeAllRanges()
  selection.addRange(range)
}

/**
 * Set cursor position in either a textarea or contenteditable element
 *
 * @param element - The editor element (textarea or contenteditable)
 * @param position - The cursor position to set
 */
export function setCursorPosition(
  element: HTMLTextAreaElement | HTMLElement | null,
  position: number,
): void {
  if (!element) {
    return
  }

  if (isTextarea(element)) {
    setCursorInTextarea(element, position)
  } else {
    setCursorInContenteditable(element, position)
  }
}

/**
 * Convert EditorState to TextSelection for use with text manipulation functions
 *
 * @param state - The editor state
 * @returns TextSelection object
 */
export function editorStateToSelection(state: EditorState): TextSelection {
  return {
    text: state.value,
    start: state.start,
    end: state.end,
  }
}

/**
 * Extract relative path from Nextcloud file picker path
 *
 * File picker returns: /username/files/path/to/file.pdf
 * We need: path/to/file.pdf (relative to user's files directory)
 *
 * @param path - The full path from file picker
 * @returns The relative path
 */
export function extractRelativePathFromFilePicker(path: string): string {
  const pathParts = path.split('/')
  if (pathParts.length >= 3 && pathParts[2] === 'files') {
    // Remove first 3 parts: ['', 'username', 'files']
    return pathParts.slice(3).join('/')
  }
  return path
}
