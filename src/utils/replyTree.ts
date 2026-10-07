import type { Post } from '@/types'

export interface ReplyNode {
  post: Post
  /** Indentation level; top-level replies are 0 */
  depth: number
  children: ReplyNode[]
  /**
   * The post this one answers, when it is not shown directly under it
   * because the nesting depth limit was reached
   */
  replyingTo: Post | null
}

export interface ReplyRow {
  post: Post
  depth: number
  replyingTo: Post | null
  /** Number of replies shown below this post, at any depth */
  replyCount: number
  collapsed: boolean
}

/**
 * Arrange a page of replies into a tree.
 *
 * Replies deeper than `maxDepth` are shown next to their parent, under the
 * deepest ancestor still within the limit, and keep a `replyingTo` reference
 * to the post they answer. With `maxDepth` 0 every nested reply is listed
 * right after its top-level reply.
 *
 * @param topLevel Top-level replies, in display order
 * @param nested Nested replies below them, oldest first
 * @param maxDepth How many levels replies can nest below a top-level reply
 */
export function buildReplyTree(topLevel: Post[], nested: Post[], maxDepth: number): ReplyNode[] {
  const nodes = new Map<number, ReplyNode>()
  const shownUnder = new Map<number, ReplyNode | null>()
  const listedAfterRoot = new Map<number, ReplyNode[]>()

  const roots = topLevel.map((post) => {
    const node: ReplyNode = { post, depth: 0, children: [], replyingTo: null }
    nodes.set(post.id, node)
    shownUnder.set(post.id, null)
    return node
  })

  for (const post of nested) {
    const parent =
      (post.parentPostId != null ? nodes.get(post.parentPostId) : undefined) ??
      (post.rootReplyId != null ? nodes.get(post.rootReplyId) : undefined)
    if (!parent) {
      continue
    }

    let target: ReplyNode | null = parent
    while (target && target.depth >= maxDepth) {
      target = shownUnder.get(target.post.id) ?? null
    }

    const node: ReplyNode = {
      post,
      depth: target ? target.depth + 1 : 0,
      children: [],
      replyingTo: target === parent ? null : parent.post,
    }
    nodes.set(post.id, node)
    shownUnder.set(post.id, target)

    if (target) {
      target.children.push(node)
    } else {
      const rootId = post.rootReplyId ?? parent.post.id
      const list = listedAfterRoot.get(rootId) ?? []
      list.push(node)
      listedAfterRoot.set(rootId, list)
    }
  }

  return roots.flatMap((root) => [root, ...(listedAfterRoot.get(root.post.id) ?? [])])
}

/**
 * Flatten a reply tree into display rows, leaving out the replies below
 * collapsed posts
 */
export function flattenReplyTree(tree: ReplyNode[], collapsedIds: ReadonlySet<number>): ReplyRow[] {
  const rows: ReplyRow[] = []

  const visit = (node: ReplyNode): void => {
    const collapsed = collapsedIds.has(node.post.id) && node.children.length > 0
    rows.push({
      post: node.post,
      depth: node.depth,
      replyingTo: node.replyingTo,
      replyCount: countReplies(node),
      collapsed,
    })
    if (!collapsed) {
      node.children.forEach(visit)
    }
  }

  tree.forEach(visit)
  return rows
}

function countReplies(node: ReplyNode): number {
  return node.children.reduce((sum, child) => sum + 1 + countReplies(child), 0)
}
