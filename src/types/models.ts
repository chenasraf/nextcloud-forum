/**
 * Forum TypeScript Models
 * These interfaces match the backend JSON responses
 */

export interface Category {
  id: number
  headerId: number
  name: string
  description: string | null
  slug: string
  sortOrder: number
  threadCount: number
  postCount: number
  createdAt: number
  updatedAt: number
  lastActivityAt?: number | null
  readAt?: number | null
}

export interface CategoryHeader {
  id: number
  name: string
  description: string | null
  sortOrder: number
  createdAt: number
  categories?: Category[]
}

export interface User {
  userId: string
  displayName: string
  isDeleted: boolean
  roles: Role[]
  signature: string | null
  signatureRaw: string | null
}

export interface Thread {
  id: number
  categoryId: number
  authorId: string
  title: string
  slug: string
  viewCount: number
  postCount: number
  lastPostId: number | null
  isLocked: boolean
  isPinned: boolean
  isHidden: boolean
  createdAt: number
  updatedAt: number
  // Enriched fields
  author?: User
  categorySlug?: string | null
  categoryName?: string | null
  isSubscribed?: boolean
}

export interface Post {
  id: number
  threadId: number
  authorId: string
  content: string
  contentRaw: string
  isEdited: boolean
  isFirstPost: boolean
  editedAt: number | null
  createdAt: number
  updatedAt: number
  // Enriched fields
  author?: User
  // Thread context (added by SearchController for search results)
  threadTitle?: string
  threadSlug?: string
  // Client-side enrichment
  reactions?: Array<{
    emoji: string
    count: number
    userIds: string[]
    hasReacted: boolean
  }>
}

export interface ForumUser {
  userId: string
  postCount: number
  threadCount: number
  lastPostAt: number | null
  deletedAt: number | null
  createdAt: number
  updatedAt: number
}

export interface BBCode {
  id: number
  tag: string
  replacement: string
  example: string
  description: string | null
  enabled: boolean
  parseInner: boolean
  isBuiltin: boolean
  specialHandler: string | null
  createdAt: number
}

export interface ReadMarker {
  id: number
  userId: string
  entityId: number
  markerType: string
  lastReadPostId: number | null
  readAt: number
}

export interface Role {
  id: number
  name: string
  description: string | null
  colorLight: string | null
  colorDark: string | null
  canAccessAdminTools: boolean
  canEditRoles: boolean
  canEditCategories: boolean
  isSystemRole: boolean
  roleType: 'admin' | 'moderator' | 'default' | 'guest' | 'custom'
  createdAt: number
}

export interface UserRole {
  id: number
  userId: string
  roleId: number
  createdAt: number
}

export interface Reaction {
  id: number
  postId: number
  userId: string
  reactionType: string
  createdAt: number
}

export interface CatHeader {
  id: number
  name: string
  description: string | null
  sortOrder: number
  createdAt: number
}

export interface SearchResult {
  threads: Thread[]
  posts: Post[]
  threadCount: number
  postCount: number
  query: string
}

export interface SearchParams {
  q: string
  searchThreads: boolean
  searchPosts: boolean
  categoryId?: number
  limit: number
  offset: number
}

export interface Draft {
  id: number
  userId: string
  entityType: 'thread' | 'post'
  parentId: number
  title: string | null
  content: string
  createdAt: number
  updatedAt: number
}

export interface PostHistoryEntry {
  id: number
  postId: number
  content: string
  editedBy: string
  editedAt: number
  // Enriched fields
  editor?: User
}

export interface PostHistoryResponse {
  current: Post
  history: PostHistoryEntry[]
}
