import type { Category, Post, Role, Thread, User } from '@/types'

// ============================================================================
// MODEL FACTORIES - Safe to use in tests (not in vi.mock factories)
// ============================================================================

/**
 * Create a mock Role object with sensible defaults.
 *
 * @param overrides - Partial Role object to override defaults
 *
 * @example
 * const role = createMockRole({ name: 'Admin', roleType: 'admin' })
 */
export function createMockRole(overrides: Partial<Role> = {}): Role {
  return {
    id: 100,
    name: 'Test Role',
    description: null,
    colorLight: null,
    colorDark: null,
    canAccessAdminTools: false,
    canEditRoles: false,
    canEditCategories: false,
    isSystemRole: false,
    roleType: 'custom',
    createdAt: Date.now(),
    ...overrides,
  }
}

/**
 * Create a mock User object with sensible defaults.
 *
 * @param overrides - Partial User object to override defaults
 *
 * @example
 * const user = createMockUser({ userId: 'john', displayName: 'John Doe' })
 */
export function createMockUser(overrides: Partial<User> = {}): User {
  return {
    userId: 'testuser',
    displayName: 'Test User',
    isDeleted: false,
    roles: [],
    signature: null,
    signatureRaw: null,
    ...overrides,
  }
}

/**
 * Create a mock Thread object with sensible defaults.
 *
 * @param overrides - Partial Thread object to override defaults
 *
 * @example
 * const thread = createMockThread({ title: 'My Thread', isPinned: true })
 */
export function createMockThread(overrides: Partial<Thread> = {}): Thread {
  return {
    id: 1,
    categoryId: 1,
    authorId: 'testuser',
    title: 'Test Thread',
    slug: 'test-thread',
    viewCount: 100,
    postCount: 10,
    lastPostId: null,
    isLocked: false,
    isPinned: false,
    isHidden: false,
    createdAt: Date.now() / 1000,
    updatedAt: Date.now() / 1000,
    author: createMockUser(),
    ...overrides,
  }
}

/**
 * Create a mock Post object with sensible defaults.
 *
 * @param overrides - Partial Post object to override defaults
 *
 * @example
 * const post = createMockPost({ content: '<p>Hello world</p>' })
 */
export function createMockPost(overrides: Partial<Post> = {}): Post {
  return {
    id: 1,
    threadId: 1,
    authorId: 'testuser',
    content: '<p>This is a test post content.</p>',
    contentRaw: 'This is a test post content.',
    isEdited: false,
    isFirstPost: false,
    editedAt: null,
    createdAt: Date.now() / 1000,
    updatedAt: Date.now() / 1000,
    threadTitle: 'Test Thread',
    threadSlug: 'test-thread',
    author: createMockUser(),
    ...overrides,
  }
}

/**
 * Create a mock Category object with sensible defaults.
 *
 * @param overrides - Partial Category object to override defaults
 *
 * @example
 * const category = createMockCategory({ name: 'General Discussion' })
 */
export function createMockCategory(overrides: Partial<Category> = {}): Category {
  return {
    id: 1,
    headerId: 1,
    name: 'Test Category',
    description: 'Test description',
    slug: 'test-category',
    sortOrder: 0,
    threadCount: 10,
    postCount: 50,
    createdAt: Date.now(),
    updatedAt: Date.now(),
    ...overrides,
  }
}
