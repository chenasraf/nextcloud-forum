<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\Draft;
use OCA\Forum\Db\DraftMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class DraftController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private DraftMapper $draftMapper,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get a thread draft for a category
	 *
	 * @param int $categoryId Category ID
	 * @return DataResponse<Http::STATUS_OK, array{draft: array<string, mixed>|null}, array{}>
	 *
	 * 200: Draft found or null
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/drafts/thread/{categoryId}')]
	public function getThreadDraft(int $categoryId): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			try {
				$draft = $this->draftMapper->findThreadDraft($user->getUID(), $categoryId);
				return new DataResponse(['draft' => $draft->jsonSerialize()]);
			} catch (DoesNotExistException) {
				return new DataResponse(['draft' => null]);
			}
		} catch (\Exception $e) {
			$this->logger->error('Error getting thread draft: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to get thread draft'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Save (create or update) a thread draft for a category
	 *
	 * @param int $categoryId Category ID
	 * @param string|null $title Draft title (can be empty)
	 * @param string $content Draft content
	 * @return DataResponse<Http::STATUS_OK, array{draft: array<string, mixed>}, array{}>
	 *
	 * 200: Draft saved
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/drafts/thread/{categoryId}')]
	public function saveThreadDraft(int $categoryId, ?string $title, string $content): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$draft = $this->draftMapper->saveThreadDraft(
				$user->getUID(),
				$categoryId,
				$title,
				$content
			);

			return new DataResponse(['draft' => $draft->jsonSerialize()]);
		} catch (\Exception $e) {
			$this->logger->error('Error saving thread draft: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to save thread draft'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a thread draft for a category
	 *
	 * @param int $categoryId Category ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Draft deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/drafts/thread/{categoryId}')]
	public function deleteThreadDraft(int $categoryId): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$this->draftMapper->deleteThreadDraft($user->getUID(), $categoryId);

			return new DataResponse(['success' => true]);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting thread draft: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete thread draft'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get all thread drafts for the current user
	 *
	 * @return DataResponse<Http::STATUS_OK, array{drafts: list<array<string, mixed>>}, array{}>
	 *
	 * 200: List of drafts returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/drafts/thread')]
	public function listThreadDrafts(): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$drafts = $this->draftMapper->findThreadDrafts($user->getUID());

			return new DataResponse([
				'drafts' => array_map(fn (Draft $d) => $d->jsonSerialize(), $drafts),
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error listing thread drafts: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to list thread drafts'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
