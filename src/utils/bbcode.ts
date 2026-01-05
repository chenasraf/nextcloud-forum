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
