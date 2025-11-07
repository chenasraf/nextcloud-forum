<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\ReactionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ReactionController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ReactionMapper $reactionMapper,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get reactions by post
	 *
	 * @param int $postId Post ID
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Reactions returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/posts/{postId}/reactions')]
	public function byPost(int $postId): DataResponse {
		try {
			$reactions = $this->reactionMapper->findByPostId($postId);
			return new DataResponse(array_map(fn ($r) => $r->jsonSerialize(), $reactions));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching reactions by post: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch reactions'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a single reaction
	 *
	 * @param int $id Reaction ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Reaction returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/reactions/{id}')]
	public function show(int $id): DataResponse {
		try {
			$reaction = $this->reactionMapper->find($id);
			return new DataResponse($reaction->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Reaction not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching reaction: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch reaction'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new reaction
	 *
	 * @param int $postId Post ID
	 * @param string $reactionType Type of reaction
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Reaction created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/reactions')]
	public function create(int $postId, string $reactionType): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$reaction = new \OCA\Forum\Db\Reaction();
			$reaction->setPostId($postId);
			$reaction->setUserId($user->getUID());
			$reaction->setReactionType($reactionType);
			$reaction->setCreatedAt(time());

			/** @var \OCA\Forum\Db\Reaction */
			$createdReaction = $this->reactionMapper->insert($reaction);
			return new DataResponse($createdReaction->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error creating reaction: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create reaction'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a reaction
	 *
	 * @param int $id Reaction ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Reaction deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/reactions/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$reaction = $this->reactionMapper->find($id);
			$this->reactionMapper->delete($reaction);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Reaction not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting reaction: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete reaction'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
