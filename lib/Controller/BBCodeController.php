<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\BBCodeMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class BBCodeController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private BBCodeMapper $bbCodeMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all BBCodes (excludes builtin codes)
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: BBCodes returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'GET', url: '/api/bbcodes')]
	public function index(): DataResponse {
		try {
			$bbcodes = $this->bbCodeMapper->findAllNonBuiltin();
			return new DataResponse(array_map(fn ($b) => $b->jsonSerialize(), $bbcodes));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching BBCodes: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch BBCodes'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get enabled BBCodes
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Enabled BBCodes returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/bbcodes/enabled')]
	public function enabled(): DataResponse {
		try {
			$bbcodes = $this->bbCodeMapper->findAllEnabled();
			return new DataResponse(array_map(fn ($b) => $b->jsonSerialize(), $bbcodes));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching enabled BBCodes: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch BBCodes'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get builtin BBCodes (for help dialog)
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Builtin BBCodes returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/bbcodes/builtin')]
	public function builtin(): DataResponse {
		try {
			$bbcodes = $this->bbCodeMapper->findAllBuiltin();
			return new DataResponse(array_map(fn ($b) => $b->jsonSerialize(), $bbcodes));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching builtin BBCodes: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch BBCodes'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a single BBCode
	 *
	 * @param int $id BBCode ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: BBCode returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'GET', url: '/api/bbcodes/{id}')]
	public function show(int $id): DataResponse {
		try {
			$bbcode = $this->bbCodeMapper->find($id);

			// Prevent access to builtin BBCodes
			if ($bbcode->getIsBuiltin()) {
				return new DataResponse(['error' => 'Cannot access builtin BBCode'], Http::STATUS_FORBIDDEN);
			}

			return new DataResponse($bbcode->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'BBCode not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching BBCode: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch BBCode'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new BBCode
	 *
	 * @param string $tag BBCode tag name
	 * @param string $replacement Replacement pattern
	 * @param string $example Example usage
	 * @param string|null $description Optional description
	 * @param bool $enabled Whether BBCode is enabled
	 * @param bool $parseInner Whether to parse inner BBCode tags
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: BBCode created
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'POST', url: '/api/bbcodes')]
	public function create(string $tag, string $replacement, string $example, ?string $description = null, bool $enabled = true, bool $parseInner = true): DataResponse {
		try {
			$bbcode = new \OCA\Forum\Db\BBCode();
			$bbcode->setTag($tag);
			$bbcode->setReplacement($replacement);
			$bbcode->setExample($example);
			$bbcode->setDescription($description);
			$bbcode->setEnabled($enabled);
			$bbcode->setParseInner($parseInner);
			$bbcode->setIsBuiltin(false); // User-created BBCodes are never builtin
			$bbcode->setCreatedAt(time());

			/** @var \OCA\Forum\Db\BBCode */
			$createdBBCode = $this->bbCodeMapper->insert($bbcode);
			return new DataResponse($createdBBCode->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error creating BBCode: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create BBCode'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update a BBCode
	 *
	 * @param int $id BBCode ID
	 * @param string|null $tag BBCode tag name
	 * @param string|null $replacement Replacement pattern
	 * @param string|null $example Example usage
	 * @param string|null $description Description
	 * @param bool|null $enabled Whether BBCode is enabled
	 * @param bool|null $parseInner Whether to parse inner BBCode tags
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: BBCode updated
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'PUT', url: '/api/bbcodes/{id}')]
	public function update(int $id, ?string $tag = null, ?string $replacement = null, ?string $example = null, ?string $description = null, ?bool $enabled = null, ?bool $parseInner = null): DataResponse {
		try {
			$bbcode = $this->bbCodeMapper->find($id);

			// Prevent updating builtin BBCodes
			if ($bbcode->getIsBuiltin()) {
				return new DataResponse(['error' => 'Cannot update builtin BBCode'], Http::STATUS_FORBIDDEN);
			}

			if ($tag !== null) {
				$bbcode->setTag($tag);
			}
			if ($replacement !== null) {
				$bbcode->setReplacement($replacement);
			}
			if ($example !== null) {
				$bbcode->setExample($example);
			}
			if ($description !== null) {
				$bbcode->setDescription($description);
			}
			if ($enabled !== null) {
				$bbcode->setEnabled($enabled);
			}
			if ($parseInner !== null) {
				$bbcode->setParseInner($parseInner);
			}

			/** @var \OCA\Forum\Db\BBCode */
			$updatedBBCode = $this->bbCodeMapper->update($bbcode);
			return new DataResponse($updatedBBCode->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'BBCode not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating BBCode: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update BBCode'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a BBCode
	 *
	 * @param int $id BBCode ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: BBCode deleted
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'DELETE', url: '/api/bbcodes/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$bbcode = $this->bbCodeMapper->find($id);

			// Prevent deleting builtin BBCodes
			if ($bbcode->getIsBuiltin()) {
				return new DataResponse(['error' => 'Cannot delete builtin BBCode'], Http::STATUS_FORBIDDEN);
			}

			$this->bbCodeMapper->delete($bbcode);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'BBCode not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting BBCode: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete BBCode'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
