<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Service\EditHistoryVisibilityService;
use OCA\Forum\Service\PermissionService;
use OCA\Forum\Service\PostHistoryService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class PostHistoryController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private PostHistoryService $postHistoryService,
		private PostMapper $postMapper,
		private PermissionService $permissionService,
		private EditHistoryVisibilityService $editHistoryVisibilityService,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get edit history for a post
	 *
	 * Returns the current version of the post and all historical versions,
	 * ordered from newest to oldest.
	 *
	 * @param int $postId Post ID
	 * @return DataResponse<Http::STATUS_OK, array{current: array<string, mixed>, history: list<array<string, mixed>>}, array{}>
	 *
	 * 200: Post history returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canView', resourceType: 'category', resourceIdFromPostId: 'postId')]
	#[ApiRoute(verb: 'GET', url: '/api/posts/{postId}/history')]
	public function getHistory(int $postId): DataResponse {
		try {
			$post = $this->postMapper->find($postId);
			$categoryId = $this->permissionService->getCategoryIdFromPost($postId);

			if (!$this->editHistoryVisibilityService->canViewEditHistory($this->userId, $post->getAuthorId(), $categoryId)) {
				return new DataResponse(['error' => 'Insufficient permissions to view edit history'], Http::STATUS_FORBIDDEN);
			}

			$history = $this->postHistoryService->getPostHistory($postId);
			return new DataResponse($history);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching post history: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch post history'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
