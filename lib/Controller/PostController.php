<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\BBCodeMapper;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\ForumUserMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Service\BBCodeService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PostController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private PostMapper $postMapper,
		private ThreadMapper $threadMapper,
		private CategoryMapper $categoryMapper,
		private ForumUserMapper $forumUserMapper,
		private BBCodeService $bbCodeService,
		private BBCodeMapper $bbCodeMapper,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get posts by thread
	 *
	 * @param int $threadId Thread ID
	 * @param int $limit Maximum number of posts to return
	 * @param int $offset Offset for pagination
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Posts returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/threads/{threadId}/posts')]
	public function byThread(int $threadId, int $limit = 50, int $offset = 0): DataResponse {
		try {
			$posts = $this->postMapper->findByThreadId($threadId, $limit, $offset);
			// Prefetch BBCodes once for all posts to avoid repeated queries
			$bbcodes = $this->bbCodeMapper->findAllEnabled();
			return new DataResponse(array_map(fn ($p) => Post::enrichPostContent($p, $bbcodes), $posts));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching posts by thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch posts'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a single post
	 *
	 * @param int $id Post ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Post returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/posts/{id}')]
	public function show(int $id): DataResponse {
		try {
			$post = $this->postMapper->find($id);
			return new DataResponse(Post::enrichPostContent($post));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching post: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch post'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a post by slug
	 *
	 * @param string $slug Post slug
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Post returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/posts/slug/{slug}')]
	public function bySlug(string $slug): DataResponse {
		try {
			$post = $this->postMapper->findBySlug($slug);
			return new DataResponse(Post::enrichPostContent($post));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching post by slug: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch post'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new post
	 *
	 * @param int $threadId Thread ID
	 * @param string $content Post content
	 * @param string|null $slug Post slug (auto-generated if not provided)
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Post created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/posts')]
	public function create(int $threadId, string $content, ?string $slug = null): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			// Ensure forum user exists - do not auto-create
			try {
				$forumUser = $this->forumUserMapper->findByUserId($user->getUID());
			} catch (DoesNotExistException $e) {
				// User must be registered in the forum before posting
				return new DataResponse(['error' => 'User not registered in forum'], Http::STATUS_FORBIDDEN);
			}

			// Auto-generate slug if not provided
			if ($slug === null || $slug === '') {
				$slug = 'post-' . uniqid();
			}

			$post = new \OCA\Forum\Db\Post();
			$post->setThreadId($threadId);
			$post->setAuthorId($user->getUID());
			$post->setContent($content);
			$post->setSlug($slug);
			$post->setIsEdited(false);
			$post->setCreatedAt(time());
			$post->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Post */
			$createdPost = $this->postMapper->insert($post);

			// Update the thread's post count and timestamps
			try {
				$thread = $this->threadMapper->find($threadId);
				$thread->setPostCount($thread->getPostCount() + 1);
				$thread->setLastPostId($createdPost->getId());
				$thread->setUpdatedAt(time());
				$this->threadMapper->update($thread);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update thread post count: ' . $e->getMessage());
				// Don't fail the request if thread update fails
			}

			// Update the forum user's post count
			try {
				$forumUser->setPostCount($forumUser->getPostCount() + 1);
				$forumUser->setUpdatedAt(time());
				$this->forumUserMapper->update($forumUser);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update forum user post count: ' . $e->getMessage());
				// Don't fail the request if user update fails
			}

			// Update the category's post count
			try {
				$category = $this->categoryMapper->find($thread->getCategoryId());
				$category->setPostCount($category->getPostCount() + 1);
				$this->categoryMapper->update($category);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update category post count: ' . $e->getMessage());
				// Don't fail the request if category update fails
			}

			return new DataResponse(Post::enrichPostContent($createdPost), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error creating post: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create post'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update a post
	 *
	 * @param int $id Post ID
	 * @param string|null $content Post content
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Post updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/posts/{id}')]
	public function update(int $id, ?string $content = null): DataResponse {
		try {
			$post = $this->postMapper->find($id);

			if ($content !== null) {
				$post->setContent($content);
				$post->setIsEdited(true);
				$post->setEditedAt(time());
			}
			$post->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Post */
			$updatedPost = $this->postMapper->update($post);
			return new DataResponse(Post::enrichPostContent($updatedPost));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating post: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update post'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a post
	 *
	 * @param int $id Post ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Post deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/posts/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$post = $this->postMapper->find($id);
			$this->postMapper->delete($post);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting post: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete post'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
