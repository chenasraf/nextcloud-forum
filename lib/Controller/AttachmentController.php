<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\AttachmentMapper;
use OCA\Forum\Db\PostMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class AttachmentController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private AttachmentMapper $attachmentMapper,
		private PostMapper $postMapper,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
		private ?string $userId,
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

	/**
	 * Download a BBCode attachment file
	 *
	 * This serves files embedded in posts via [attachment] tags.
	 * Verifies the file is referenced in the post and serves it to any user who can view the post.
	 *
	 * @param int $postId The post ID
	 * @param string $filePath The file path relative to author's home directory
	 * @return StreamResponse<Http::STATUS_OK, array{Content-Type: string}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: File content returned
	 * 400: Invalid file
	 * 403: File not attached to post
	 * 404: Post or file not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/posts/{postId}/files')]
	public function download(int $postId, string $filePath): StreamResponse|DataResponse {
		try {
			// Get the post
			$post = $this->postMapper->find($postId);

			// Verify the file path is actually in the post content
			$attachmentTag = '[attachment]' . $filePath . '[/attachment]';
			if (strpos($post->getContent(), $attachmentTag) === false) {
				$this->logger->warning('Attempted to download file not attached to post', [
					'postId' => $postId,
					'filePath' => $filePath,
					'userId' => $this->userId,
				]);
				return new DataResponse(['error' => 'File not attached to this post'], Http::STATUS_FORBIDDEN);
			}

			// Get the author's folder
			$authorId = $post->getAuthorId();
			$userFolder = $this->rootFolder->getUserFolder($authorId);

			// Get the file
			$file = $userFolder->get($filePath);

			// Verify it's a file
			if (!($file instanceof \OCP\Files\File)) {
				return new DataResponse(['error' => 'Invalid file'], Http::STATUS_BAD_REQUEST);
			}

			// Create a stream response to serve the file
			$response = new StreamResponse($file->fopen('rb'));
			$response->addHeader('Content-Type', $file->getMimeType());
			$response->addHeader('Content-Disposition', 'inline; filename="' . rawurlencode($file->getName()) . '"');
			$response->addHeader('Content-Length', (string)$file->getSize());
			$response->addHeader('Cache-Control', 'public, max-age=3600');

			return $response;
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (NotFoundException $e) {
			$this->logger->warning('Attachment file not found', [
				'postId' => $postId,
				'filePath' => $filePath,
			]);
			return new DataResponse(['error' => 'File not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error serving attachment: ' . $e->getMessage());
			return new DataResponse(['error' => 'Error loading file'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get preview/thumbnail for a BBCode attachment
	 *
	 * @param int $postId The post ID
	 * @param string $filePath The file path relative to author's home directory
	 * @param int $x Preview width
	 * @param int $y Preview height
	 * @return StreamResponse<Http::STATUS_OK, array{Content-Type: string}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Preview image returned
	 * 400: Invalid file
	 * 403: File not attached to post
	 * 404: Post or file not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/posts/{postId}/preview')]
	public function preview(int $postId, string $filePath, int $x = 1920, int $y = 1080): StreamResponse|DataResponse {
		try {
			// Get the post
			$post = $this->postMapper->find($postId);

			// Verify the file path is actually in the post content
			$attachmentTag = '[attachment]' . $filePath . '[/attachment]';
			if (strpos($post->getContent(), $attachmentTag) === false) {
				return new DataResponse(['error' => 'File not attached to this post'], Http::STATUS_FORBIDDEN);
			}

			// Get the author's folder
			$authorId = $post->getAuthorId();
			$userFolder = $this->rootFolder->getUserFolder($authorId);

			// Get the file
			$file = $userFolder->get($filePath);

			// Verify it's a file
			if (!($file instanceof \OCP\Files\File)) {
				return new DataResponse(['error' => 'Invalid file'], Http::STATUS_BAD_REQUEST);
			}

			// Only generate previews for images
			if (!str_starts_with($file->getMimeType(), 'image/')) {
				// For non-images, redirect to download
				return $this->download($postId, $filePath);
			}

			// Get preview from Nextcloud's preview system
			try {
				/** @var \OCP\IPreview $previewManager */
				$previewManager = \OC::$server->get(\OCP\IPreview::class);

				if ($previewManager->isMimeSupported($file->getMimeType())) {
					$preview = $previewManager->getPreview($file, $x, $y, false);

					$response = new StreamResponse($preview->getContent());
					$response->addHeader('Content-Type', $preview->getMimeType());
					$response->addHeader('Cache-Control', 'public, max-age=86400');

					return $response;
				}
			} catch (\Exception $e) {
				$this->logger->warning('Preview generation failed, falling back to original', [
					'error' => $e->getMessage(),
				]);
			}

			// Fallback to serving original file
			return $this->download($postId, $filePath);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => 'File not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error serving preview: ' . $e->getMessage());
			return new DataResponse(['error' => 'Error loading preview'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
