<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\Template;
use OCA\Forum\Db\TemplateMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TemplateController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private TemplateMapper $templateMapper,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List current user's templates
	 *
	 * @param string|null $visibility Optional visibility filter (threads, replies)
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Templates returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/templates')]
	public function index(?string $visibility = null): DataResponse {
		try {
			$templates = $this->templateMapper->findByUserId($this->userId, $visibility);
			return new DataResponse(array_map(fn ($t) => $t->jsonSerialize(), $templates));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching templates: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch templates'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a template
	 *
	 * @param string $name Template name
	 * @param string $content Template content (BBCode)
	 * @param string $visibility Visibility setting
	 * @param int $sortOrder Sort order
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Template created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/templates')]
	public function create(
		string $name,
		string $content,
		string $visibility = 'both',
		int $sortOrder = 0,
	): DataResponse {
		try {
			$now = time();
			$template = new Template();
			$template->setUserId($this->userId);
			$template->setName($name);
			$template->setContent($content);
			$template->setVisibility($visibility);
			$template->setSortOrder($sortOrder);
			$template->setCreatedAt($now);
			$template->setUpdatedAt($now);

			/** @var Template */
			$created = $this->templateMapper->insert($template);
			return new DataResponse($created->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error creating template: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create template'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update a template
	 *
	 * @param int $id Template ID
	 * @param string|null $name Template name
	 * @param string|null $content Template content
	 * @param string|null $visibility Visibility setting
	 * @param int|null $sortOrder Sort order
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Template updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/templates/{id}')]
	public function update(
		int $id,
		?string $name = null,
		?string $content = null,
		?string $visibility = null,
		?int $sortOrder = null,
	): DataResponse {
		try {
			$template = $this->templateMapper->find($id);

			if ($template->getUserId() !== $this->userId) {
				return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
			}

			if ($name !== null) {
				$template->setName($name);
			}
			if ($content !== null) {
				$template->setContent($content);
			}
			if ($visibility !== null) {
				$template->setVisibility($visibility);
			}
			if ($sortOrder !== null) {
				$template->setSortOrder($sortOrder);
			}
			$template->setUpdatedAt(time());

			/** @var Template */
			$updated = $this->templateMapper->update($template);
			return new DataResponse($updated->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Template not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating template: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update template'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a template
	 *
	 * @param int $id Template ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Template deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/templates/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$template = $this->templateMapper->find($id);

			if ($template->getUserId() !== $this->userId) {
				return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
			}

			$this->templateMapper->delete($template);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Template not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting template: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete template'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
