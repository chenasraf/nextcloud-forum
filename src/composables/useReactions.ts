import { ref, type Ref } from 'vue'
import { ocs } from '@/axios'
import type { Reaction } from '@/types'

export interface ReactionGroup {
  emoji: string
  count: number
  userIds: string[]
  hasReacted: boolean
}

export function useReactions() {
  const loading: Ref<boolean> = ref(false)
  const error: Ref<string | null> = ref(null)

  /**
   * Fetch reactions for multiple posts at once (for performance)
   */
  const fetchReactionsForPosts = async (postIds: number[]): Promise<Reaction[]> => {
    if (!postIds.length) return []

    try {
      loading.value = true
      error.value = null
      const response = await ocs.post<Reaction[]>('/reactions/by-posts', { postIds })
      return response.data || []
    } catch (e) {
      console.error('Failed to fetch reactions for posts', e)
      error.value = (e as Error).message
      return []
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch reactions for a single post
   */
  const fetchReactionsForPost = async (postId: number): Promise<Reaction[]> => {
    try {
      loading.value = true
      error.value = null
      const response = await ocs.get<Reaction[]>(`/posts/${postId}/reactions`)
      return response.data || []
    } catch (e) {
      console.error('Failed to fetch reactions for post', e)
      error.value = (e as Error).message
      return []
    } finally {
      loading.value = false
    }
  }

  /**
   * Toggle a reaction (add if not exists, remove if exists)
   */
  const toggleReaction = async (
    postId: number,
    emoji: string,
  ): Promise<{ action: 'added' | 'removed'; reaction?: Reaction }> => {
    try {
      loading.value = true
      error.value = null
      const response = await ocs.post<{ action: 'added' | 'removed'; reaction?: Reaction }>(
        '/reactions/toggle',
        {
          postId,
          reactionType: emoji,
        },
      )
      return response.data
    } catch (e) {
      console.error('Failed to toggle reaction', e)
      error.value = (e as Error).message
      throw e
    } finally {
      loading.value = false
    }
  }

  /**
   * Group reactions by emoji and calculate counts
   */
  const groupReactions = (reactions: Reaction[], currentUserId: string | null): ReactionGroup[] => {
    const groups = new Map<string, ReactionGroup>()

    reactions.forEach((reaction) => {
      const existing = groups.get(reaction.reactionType)
      if (existing) {
        existing.count++
        existing.userIds.push(reaction.userId)
        if (currentUserId && reaction.userId === currentUserId) {
          existing.hasReacted = true
        }
      } else {
        groups.set(reaction.reactionType, {
          emoji: reaction.reactionType,
          count: 1,
          userIds: [reaction.userId],
          hasReacted: currentUserId ? reaction.userId === currentUserId : false,
        })
      }
    })

    // Sort by count (descending), then alphabetically
    return Array.from(groups.values()).sort((a, b) => {
      if (a.count !== b.count) {
        return b.count - a.count
      }
      return a.emoji.localeCompare(b.emoji)
    })
  }

  /**
   * Connect reactions to posts (for efficient batch loading)
   */
  const connectReactionsToPosts = <T extends { id: number }>(
    posts: T[],
    reactions: Reaction[],
    currentUserId: string | null,
  ): Array<T & { reactions: ReactionGroup[] }> => {
    // Group reactions by post ID
    const reactionsByPost = new Map<number, Reaction[]>()
    reactions.forEach((reaction) => {
      const existing = reactionsByPost.get(reaction.postId) || []
      existing.push(reaction)
      reactionsByPost.set(reaction.postId, existing)
    })

    // Connect reactions to each post
    return posts.map((post) => {
      const postReactions = reactionsByPost.get(post.id) || []
      return {
        ...post,
        reactions: groupReactions(postReactions, currentUserId),
      }
    })
  }

  return {
    loading,
    error,
    fetchReactionsForPosts,
    fetchReactionsForPost,
    toggleReaction,
    groupReactions,
    connectReactionsToPosts,
  }
}
