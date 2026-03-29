<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\PostMapper;
use OCA\Forum\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\Route;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class FileController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private PostMapper $postMapper,
		private PermissionService $permissionService,
		private IRootFolder $rootFolder,
		private IAppConfig $config,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Check if the current user can view a post's attachments.
	 * For authenticated users, checks category canView permission.
	 * For guests, also checks that guest access is globally enabled.
	 * Returns a 403 response if denied, or null if access is allowed.
	 */
	private function checkFileAccess(int $postId): ?DataResponse {
		// Guests: check global guest access first
		if ($this->userId === null) {
			$guestAccessEnabled = $this->config->getAppValueBool('allow_guest_access', false, true);
			if (!$guestAccessEnabled) {
				return new DataResponse(['error' => 'Authentication required'], Http::STATUS_FORBIDDEN);
			}
		}

		// Check canView on the post's category (works for both guests and authenticated users)
		try {
			$categoryId = $this->permissionService->getCategoryIdFromPost($postId);
			if (!$this->permissionService->hasCategoryPermission($this->userId, $categoryId, 'canView')) {
				return new DataResponse(['error' => 'Access denied'], Http::STATUS_FORBIDDEN);
			}
		} catch (\Exception $e) {
			// If we can't determine the category, deny access for safety
			return new DataResponse(['error' => 'Access denied'], Http::STATUS_FORBIDDEN);
		}

		return null;
	}

	/**
	 * Download a BBCode attachment file
	 */
	#[PublicPage]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[Route(type: Route::TYPE_FRONTPAGE, verb: 'GET', url: '/api/posts/{postId}/files')]
	public function download(int $postId, string $filePath): FileDisplayResponse|DataResponse {
		$denied = $this->checkFileAccess($postId);
		if ($denied) {
			return $denied;
		}

		try {
			$post = $this->postMapper->find($postId);

			// Verify the file path is in the post content
			$attachmentTag = '[attachment]' . $filePath . '[/attachment]';
			if (strpos($post->getContent(), $attachmentTag) === false) {
				return new DataResponse(['error' => 'File not attached to this post'], Http::STATUS_FORBIDDEN);
			}

			// Get the author's folder
			$authorId = $post->getAuthorId();
			$userFolder = $this->rootFolder->getUserFolder($authorId);

			// Get the file
			$file = $userFolder->get($filePath);

			if (!($file instanceof \OCP\Files\File)) {
				return new DataResponse(['error' => 'Invalid file'], Http::STATUS_BAD_REQUEST);
			}

			$mimeType = $file->getMimeType();

			// Support Range requests for media files (video/audio) to enable seeking
			if (str_starts_with($mimeType, 'video/') || str_starts_with($mimeType, 'audio/')) {
				return $this->serveWithRangeSupport($file);
			}

			$response = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => $mimeType]);
			$response->addHeader('Cache-Control', 'public, max-age=3600');

			return $response;
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => 'File not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error serving attachment: ' . $e->getMessage());
			return new DataResponse(['error' => 'Error loading file'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get preview for a BBCode attachment
	 */
	#[PublicPage]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[Route(type: Route::TYPE_FRONTPAGE, verb: 'GET', url: '/api/posts/{postId}/preview')]
	public function preview(int $postId, string $filePath, int $x = 1920, int $y = 1080): FileDisplayResponse|DataResponse {
		$denied = $this->checkFileAccess($postId);
		if ($denied) {
			return $denied;
		}

		try {
			$post = $this->postMapper->find($postId);

			// Verify the file path is in the post content
			$attachmentTag = '[attachment]' . $filePath . '[/attachment]';
			if (strpos($post->getContent(), $attachmentTag) === false) {
				return new DataResponse(['error' => 'File not attached to this post'], Http::STATUS_FORBIDDEN);
			}

			// Get the author's folder
			$authorId = $post->getAuthorId();
			$userFolder = $this->rootFolder->getUserFolder($authorId);

			// Get the file
			$file = $userFolder->get($filePath);

			if (!($file instanceof \OCP\Files\File)) {
				return new DataResponse(['error' => 'Invalid file'], Http::STATUS_BAD_REQUEST);
			}

			// Only for images
			if (!str_starts_with($file->getMimeType(), 'image/')) {
				return $this->download($postId, $filePath);
			}

			$response = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => $file->getMimeType()]);
			$response->addHeader('Cache-Control', 'public, max-age=86400');

			return $response;
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => 'File not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error serving preview: ' . $e->getMessage());
			return new DataResponse(['error' => 'Error loading preview'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Stream a media file with HTTP Range support for seeking
	 *
	 * Bypasses Nextcloud's response framework to stream directly,
	 * enabling proper range request handling for video/audio seeking.
	 *
	 * @return never
	 */
	private function serveWithRangeSupport(\OCP\Files\File $file): never {
		$fileSize = $file->getSize();
		$mimeType = $file->getMimeType();

		$rangeHeader = $this->request->getHeader('Range');
		$start = 0;
		$end = $fileSize - 1;
		$statusCode = Http::STATUS_OK;

		if ($rangeHeader && preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
			$start = (int)$matches[1];
			$end = $matches[2] !== '' ? (int)$matches[2] : $end;
			$statusCode = Http::STATUS_PARTIAL_CONTENT;
		}

		$length = $end - $start + 1;

		// Clear any previous output buffers
		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		http_response_code($statusCode);
		header('Content-Type: ' . $mimeType);
		header('Content-Length: ' . $length);
		header('Accept-Ranges: bytes');
		header('Cache-Control: public, max-age=3600');
		header('Content-Disposition: inline; filename="' . basename($file->getName()) . '"');

		if ($statusCode === Http::STATUS_PARTIAL_CONTENT) {
			header("Content-Range: bytes $start-$end/$fileSize");
		}

		// Stream the file in chunks
		$handle = $file->fopen('r');
		if ($handle === false) {
			http_response_code(500);
			exit;
		}

		if ($start > 0) {
			fseek($handle, $start);
		}

		$remaining = $length;
		$chunkSize = 1024 * 1024; // 1MB chunks

		while ($remaining > 0 && !feof($handle)) {
			$readSize = min($chunkSize, $remaining);
			$data = fread($handle, $readSize);
			if ($data === false) {
				break;
			}
			echo $data;
			flush();
			$remaining -= strlen($data);
		}

		fclose($handle);
		exit;
	}
}
