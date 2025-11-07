<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\AttachmentMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class AttachmentController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private AttachmentMapper $attachmentMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get attachments by post
	 *
	 * @param int $postId Post ID
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Attachments returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/posts/{postId}/attachments')]
	public function byPost(int $postId): DataResponse {
		try {
			$attachments = $this->attachmentMapper->findByPostId($postId);
			return new DataResponse(array_map(fn ($a) => $a->jsonSerialize(), $attachments));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching attachments by post: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch attachments'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a single attachment
	 *
	 * @param int $id Attachment ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Attachment returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/attachments/{id}')]
	public function show(int $id): DataResponse {
		try {
			$attachment = $this->attachmentMapper->find($id);
			return new DataResponse($attachment->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Attachment not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching attachment: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch attachment'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new attachment
	 *
	 * @param int $postId Post ID
	 * @param int $fileid Nextcloud file ID
	 * @param string $filename Filename
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Attachment created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/attachments')]
	public function create(int $postId, int $fileid, string $filename): DataResponse {
		try {
			$attachment = new \OCA\Forum\Db\Attachment();
			$attachment->setPostId($postId);
			$attachment->setFileid($fileid);
			$attachment->setFilename($filename);
			$attachment->setCreatedAt(time());

			/** @var \OCA\Forum\Db\Attachment */
			$createdAttachment = $this->attachmentMapper->insert($attachment);
			return new DataResponse($createdAttachment->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error creating attachment: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create attachment'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete an attachment
	 *
	 * @param int $id Attachment ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Attachment deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/attachments/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$attachment = $this->attachmentMapper->find($id);
			$this->attachmentMapper->delete($attachment);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Attachment not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting attachment: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete attachment'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
