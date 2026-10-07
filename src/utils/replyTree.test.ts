import { describe, it, expect } from 'vitest'
import { createMockPost } from '@/test-mocks'
import { buildReplyTree, flattenReplyTree } from './replyTree'

const reply = (id: number, parentPostId: number | null = null, rootReplyId: number | null = null) =>
  createMockPost({ id, parentPostId, rootReplyId })

// 1
// ├─ 2
// │  └─ 3
// │     └─ 4
// └─ 5
// 6
const topLevel = [reply(1), reply(6)]
const nested = [reply(2, 1, 1), reply(3, 2, 1), reply(4, 3, 1), reply(5, 1, 1)]

const shape = (rows: ReturnType<typeof flattenReplyTree>) =>
  rows.map((r) => [r.post.id, r.depth, r.replyingTo?.id ?? null])

describe('replyTree', () => {
  describe('buildReplyTree', () => {
    it('nests replies under their parents', () => {
      const rows = flattenReplyTree(buildReplyTree(topLevel, nested, 5), new Set())

      expect(shape(rows)).toEqual([
        [1, 0, null],
        [2, 1, null],
        [3, 2, null],
        [4, 3, null],
        [5, 1, null],
        [6, 0, null],
      ])
    })

    it('shows replies past the depth limit next to their parent', () => {
      const rows = flattenReplyTree(buildReplyTree(topLevel, nested, 2), new Set())

      expect(shape(rows)).toEqual([
        [1, 0, null],
        [2, 1, null],
        [3, 2, null],
        [4, 2, 3],
        [5, 1, null],
        [6, 0, null],
      ])
    })

    it('lists nested replies after their top-level reply when nesting is disabled', () => {
      const rows = flattenReplyTree(buildReplyTree(topLevel, nested, 0), new Set())

      expect(shape(rows)).toEqual([
        [1, 0, null],
        [2, 0, 1],
        [3, 0, 2],
        [4, 0, 3],
        [5, 0, 1],
        [6, 0, null],
      ])
    })

    it('falls back to the top-level reply when the parent is not on the page', () => {
      const rows = flattenReplyTree(buildReplyTree([reply(1)], [reply(9, 8, 1)], 5), new Set())

      expect(shape(rows)).toEqual([
        [1, 0, null],
        [9, 1, null],
      ])
    })
  })

  describe('flattenReplyTree', () => {
    it('counts replies at every depth', () => {
      const rows = flattenReplyTree(buildReplyTree(topLevel, nested, 5), new Set())

      expect(rows.map((r) => r.replyCount)).toEqual([4, 2, 1, 0, 0, 0])
    })

    it('hides the replies below collapsed posts', () => {
      const rows = flattenReplyTree(buildReplyTree(topLevel, nested, 5), new Set([2]))

      expect(rows.map((r) => r.post.id)).toEqual([1, 2, 5, 6])
      expect(rows[1]).toMatchObject({ collapsed: true, replyCount: 2 })
    })

    it('does not mark posts without replies as collapsed', () => {
      const rows = flattenReplyTree(buildReplyTree(topLevel, nested, 5), new Set([6]))

      expect(rows[5].collapsed).toBe(false)
    })
  })
})
