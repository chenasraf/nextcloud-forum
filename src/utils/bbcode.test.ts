import { describe, it, expect } from 'vitest'
import {
  getSelectedText,
  applyBBCodeTemplate,
  insertTextAtSelection,
  wrapSelection,
  getCursorPositionBetweenTags,
  isSelectionWrapped,
  unwrapSelection,
  toggleBBCodeTags,
  type TextSelection,
} from './bbcode'

describe('bbcode utilities', () => {
  describe('getSelectedText', () => {
    it('returns empty string when start equals end', () => {
      const selection: TextSelection = { text: 'Hello world', start: 5, end: 5 }
      expect(getSelectedText(selection)).toBe('')
    })

    it('returns selected text from middle of string', () => {
      const selection: TextSelection = { text: 'Hello world', start: 6, end: 11 }
      expect(getSelectedText(selection)).toBe('world')
    })

    it('returns selected text from start of string', () => {
      const selection: TextSelection = { text: 'Hello world', start: 0, end: 5 }
      expect(getSelectedText(selection)).toBe('Hello')
    })

    it('returns entire string when fully selected', () => {
      const selection: TextSelection = { text: 'Hello', start: 0, end: 5 }
      expect(getSelectedText(selection)).toBe('Hello')
    })

    it('handles empty text', () => {
      const selection: TextSelection = { text: '', start: 0, end: 0 }
      expect(getSelectedText(selection)).toBe('')
    })
  })

  describe('applyBBCodeTemplate', () => {
    it('wraps selected text with simple template', () => {
      const selection: TextSelection = { text: 'Hello world', start: 6, end: 11 }
      const result = applyBBCodeTemplate(selection, { template: '[b]{text}[/b]' })

      expect(result.text).toBe('Hello [b]world[/b]')
      expect(result.cursorPosition).toBe(18)
    })

    it('inserts template at cursor when no selection', () => {
      const selection: TextSelection = { text: 'Hello world', start: 6, end: 6 }
      const result = applyBBCodeTemplate(selection, { template: '[b]{text}[/b]' })

      expect(result.text).toBe('Hello [b][/b]world')
      expect(result.cursorPosition).toBe(13) // cursor after [/b]
    })

    it('uses fallback text when no selection', () => {
      const selection: TextSelection = { text: 'Hello world', start: 6, end: 6 }
      const result = applyBBCodeTemplate(selection, {
        template: '[b]{text}[/b]',
        fallbackText: 'bold text',
      })

      expect(result.text).toBe('Hello [b]bold text[/b]world')
      expect(result.cursorPosition).toBe(22) // cursor after [/b]
    })

    it('handles template with {value} placeholder', () => {
      const selection: TextSelection = { text: 'Click here', start: 6, end: 10 }
      const result = applyBBCodeTemplate(selection, {
        template: '[url={value}]{text}[/url]',
        value: 'http://example.com',
      })

      expect(result.text).toBe('Click [url=http://example.com]here[/url]')
      expect(result.cursorPosition).toBe(40)
    })

    it('handles template with both {value} and {text}', () => {
      const selection: TextSelection = { text: 'Red text', start: 4, end: 8 }
      const result = applyBBCodeTemplate(selection, {
        template: '[color={value}]{text}[/color]',
        value: 'red',
      })

      expect(result.text).toBe('Red [color=red]text[/color]')
      expect(result.cursorPosition).toBe(27)
    })

    it('replaces selected text at start of string', () => {
      const selection: TextSelection = { text: 'Hello world', start: 0, end: 5 }
      const result = applyBBCodeTemplate(selection, { template: '[i]{text}[/i]' })

      expect(result.text).toBe('[i]Hello[/i] world')
      expect(result.cursorPosition).toBe(12)
    })

    it('replaces selected text at end of string', () => {
      const selection: TextSelection = { text: 'Hello world', start: 6, end: 11 }
      const result = applyBBCodeTemplate(selection, { template: '[u]{text}[/u]' })

      expect(result.text).toBe('Hello [u]world[/u]')
      expect(result.cursorPosition).toBe(18)
    })

    it('handles complex template with newlines', () => {
      const selection: TextSelection = { text: 'item', start: 0, end: 4 }
      const result = applyBBCodeTemplate(selection, {
        template: '[list]\n[*]{text}\n[/list]',
      })

      expect(result.text).toBe('[list]\n[*]item\n[/list]')
      expect(result.cursorPosition).toBe(22)
    })

    it('handles empty value', () => {
      const selection: TextSelection = { text: 'text', start: 0, end: 4 }
      const result = applyBBCodeTemplate(selection, {
        template: '[size={value}]{text}[/size]',
        value: '',
      })

      expect(result.text).toBe('[size=]text[/size]')
    })
  })

  describe('insertTextAtSelection', () => {
    it('inserts text at cursor position (no selection)', () => {
      const selection: TextSelection = { text: 'Hello world', start: 5, end: 5 }
      const result = insertTextAtSelection(selection, ' beautiful')

      expect(result.text).toBe('Hello beautiful world')
      expect(result.cursorPosition).toBe(15)
    })

    it('replaces selected text', () => {
      const selection: TextSelection = { text: 'Hello world', start: 6, end: 11 }
      const result = insertTextAtSelection(selection, 'universe')

      expect(result.text).toBe('Hello universe')
      expect(result.cursorPosition).toBe(14)
    })

    it('inserts at start of string', () => {
      const selection: TextSelection = { text: 'world', start: 0, end: 0 }
      const result = insertTextAtSelection(selection, 'Hello ')

      expect(result.text).toBe('Hello world')
      expect(result.cursorPosition).toBe(6)
    })

    it('inserts at end of string', () => {
      const selection: TextSelection = { text: 'Hello', start: 5, end: 5 }
      const result = insertTextAtSelection(selection, ' world')

      expect(result.text).toBe('Hello world')
      expect(result.cursorPosition).toBe(11)
    })

    it('inserts emoji', () => {
      const selection: TextSelection = { text: 'Hello ', start: 6, end: 6 }
      const result = insertTextAtSelection(selection, '😀')

      expect(result.text).toBe('Hello 😀')
      expect(result.cursorPosition).toBe(8) // emoji is 2 UTF-16 code units
    })
  })

  describe('wrapSelection', () => {
    it('wraps selected text with tags', () => {
      const selection: TextSelection = { text: 'Hello world', start: 6, end: 11 }
      const result = wrapSelection(selection, '[b]', '[/b]')

      expect(result.text).toBe('Hello [b]world[/b]')
      expect(result.cursorPosition).toBe(18)
    })

    it('inserts empty tags when no selection', () => {
      const selection: TextSelection = { text: 'Hello', start: 5, end: 5 }
      const result = wrapSelection(selection, '[i]', '[/i]')

      expect(result.text).toBe('Hello[i][/i]')
      expect(result.cursorPosition).toBe(12)
    })

    it('uses fallback text when no selection', () => {
      const selection: TextSelection = { text: 'Hello ', start: 6, end: 6 }
      const result = wrapSelection(selection, '[code]', '[/code]', 'your code here')

      expect(result.text).toBe('Hello [code]your code here[/code]')
      expect(result.cursorPosition).toBe(33)
    })

    it('handles asymmetric tags', () => {
      const selection: TextSelection = { text: 'text', start: 0, end: 4 }
      const result = wrapSelection(selection, '**', '**')

      expect(result.text).toBe('**text**')
      expect(result.cursorPosition).toBe(8)
    })
  })

  describe('getCursorPositionBetweenTags', () => {
    it('returns position after opening tag', () => {
      const selection: TextSelection = { text: 'Hello ', start: 6, end: 6 }
      const position = getCursorPositionBetweenTags(selection, '[b]', '[/b]')

      expect(position).toBe(9) // 6 + 3 (length of '[b]')
    })

    it('works with longer tags', () => {
      const selection: TextSelection = { text: '', start: 0, end: 0 }
      const position = getCursorPositionBetweenTags(selection, '[quote]', '[/quote]')

      expect(position).toBe(7)
    })
  })

  describe('isSelectionWrapped', () => {
    it('returns true when selection is wrapped', () => {
      const selection: TextSelection = {
        text: 'Hello [b]world[/b] there',
        start: 9,
        end: 14,
      }

      expect(isSelectionWrapped(selection, '[b]', '[/b]')).toBe(true)
    })

    it('returns false when selection is not wrapped', () => {
      const selection: TextSelection = {
        text: 'Hello world there',
        start: 6,
        end: 11,
      }

      expect(isSelectionWrapped(selection, '[b]', '[/b]')).toBe(false)
    })

    it('returns false when only opening tag exists', () => {
      const selection: TextSelection = {
        text: 'Hello [b]world there',
        start: 9,
        end: 14,
      }

      expect(isSelectionWrapped(selection, '[b]', '[/b]')).toBe(false)
    })

    it('returns false when only closing tag exists', () => {
      const selection: TextSelection = {
        text: 'Hello world[/b] there',
        start: 6,
        end: 11,
      }

      expect(isSelectionWrapped(selection, '[b]', '[/b]')).toBe(false)
    })

    it('returns false when not enough text before selection', () => {
      const selection: TextSelection = {
        text: '[b]test[/b]',
        start: 0,
        end: 3,
      }

      expect(isSelectionWrapped(selection, '[b]', '[/b]')).toBe(false)
    })

    it('returns false when not enough text after selection', () => {
      const selection: TextSelection = {
        text: '[b]test[/b]',
        start: 7,
        end: 11,
      }

      expect(isSelectionWrapped(selection, '[b]', '[/b]')).toBe(false)
    })

    it('handles nested tags correctly', () => {
      const selection: TextSelection = {
        text: '[b][i]text[/i][/b]',
        start: 6,
        end: 10,
      }

      expect(isSelectionWrapped(selection, '[i]', '[/i]')).toBe(true)
      expect(isSelectionWrapped(selection, '[b]', '[/b]')).toBe(false)
    })
  })

  describe('unwrapSelection', () => {
    it('removes wrapping tags', () => {
      const selection: TextSelection = {
        text: 'Hello [b]world[/b] there',
        start: 9,
        end: 14,
      }
      const result = unwrapSelection(selection, '[b]', '[/b]')

      expect(result.text).toBe('Hello world there')
      expect(result.cursorPosition).toBe(11)
    })

    it('returns unchanged when not wrapped', () => {
      const selection: TextSelection = {
        text: 'Hello world there',
        start: 6,
        end: 11,
      }
      const result = unwrapSelection(selection, '[b]', '[/b]')

      expect(result.text).toBe('Hello world there')
      expect(result.cursorPosition).toBe(11)
    })

    it('unwraps at start of string', () => {
      const selection: TextSelection = {
        text: '[i]Hello[/i] world',
        start: 3,
        end: 8,
      }
      const result = unwrapSelection(selection, '[i]', '[/i]')

      expect(result.text).toBe('Hello world')
      expect(result.cursorPosition).toBe(5)
    })

    it('unwraps at end of string', () => {
      const selection: TextSelection = {
        text: 'Hello [u]world[/u]',
        start: 9,
        end: 14,
      }
      const result = unwrapSelection(selection, '[u]', '[/u]')

      expect(result.text).toBe('Hello world')
      expect(result.cursorPosition).toBe(11)
    })
  })

  describe('toggleBBCodeTags', () => {
    it('wraps unwrapped selection', () => {
      const selection: TextSelection = { text: 'Hello world', start: 6, end: 11 }
      const result = toggleBBCodeTags(selection, '[b]', '[/b]')

      expect(result.text).toBe('Hello [b]world[/b]')
      expect(result.cursorPosition).toBe(18)
    })

    it('unwraps wrapped selection', () => {
      const selection: TextSelection = {
        text: 'Hello [b]world[/b] there',
        start: 9,
        end: 14,
      }
      const result = toggleBBCodeTags(selection, '[b]', '[/b]')

      expect(result.text).toBe('Hello world there')
      expect(result.cursorPosition).toBe(11)
    })

    it('uses fallback text when wrapping with no selection', () => {
      const selection: TextSelection = { text: 'Hello ', start: 6, end: 6 }
      const result = toggleBBCodeTags(selection, '[code]', '[/code]', 'code')

      expect(result.text).toBe('Hello [code]code[/code]')
      expect(result.cursorPosition).toBe(23)
    })

    it('can toggle multiple times', () => {
      // Start with unwrapped
      let selection: TextSelection = { text: 'Hello world', start: 6, end: 11 }
      let result = toggleBBCodeTags(selection, '[s]', '[/s]')

      expect(result.text).toBe('Hello [s]world[/s]')

      // Now toggle off
      selection = { text: result.text, start: 9, end: 14 }
      result = toggleBBCodeTags(selection, '[s]', '[/s]')

      expect(result.text).toBe('Hello world')

      // Toggle on again
      selection = { text: result.text, start: 6, end: 11 }
      result = toggleBBCodeTags(selection, '[s]', '[/s]')

      expect(result.text).toBe('Hello [s]world[/s]')
    })
  })

  describe('edge cases', () => {
    it('handles unicode characters', () => {
      const selection: TextSelection = { text: '你好世界', start: 2, end: 4 }
      const result = wrapSelection(selection, '[b]', '[/b]')

      expect(result.text).toBe('你好[b]世界[/b]')
    })

    it('handles special characters in text', () => {
      const selection: TextSelection = { text: 'a < b && c > d', start: 0, end: 14 }
      const result = wrapSelection(selection, '[code]', '[/code]')

      expect(result.text).toBe('[code]a < b && c > d[/code]')
    })

    it('handles BBCode-like content in selection', () => {
      const selection: TextSelection = { text: 'Use [b] for bold', start: 4, end: 7 }
      const result = wrapSelection(selection, '[code]', '[/code]')

      expect(result.text).toBe('Use [code][b][/code] for bold')
    })

    it('handles empty string', () => {
      const selection: TextSelection = { text: '', start: 0, end: 0 }
      const result = wrapSelection(selection, '[b]', '[/b]')

      expect(result.text).toBe('[b][/b]')
      expect(result.cursorPosition).toBe(7)
    })

    it('handles very long text', () => {
      const longText = 'a'.repeat(10000)
      const selection: TextSelection = { text: longText, start: 5000, end: 5010 }
      const result = wrapSelection(selection, '[b]', '[/b]')

      expect(result.text.length).toBe(10000 + 7) // original + [b][/b]
      expect(result.text.substring(5000, 5017)).toBe('[b]aaaaaaaaaa[/b]')
    })
  })
})
