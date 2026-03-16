<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\CategoryPerm;
use OCA\Forum\Db\CategoryPermMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\Server;
use Psr\Log\LoggerInterface;

class TeamController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CategoryPermMapper $categoryPermMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the CirclesManager instance, or null if the Circles app is not available
	 */
	private function getCirclesManager(): ?\OCA\Circles\CirclesManager {
		if (!class_exists(\OCA\Circles\CirclesManager::class)) {
			return null;
		}
		try {
			return Server::get(\OCA\Circles\CirclesManager::class);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * List all available teams (circles)
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array{id: string, displayName: string, owner: string, ownerDisplayName: string, memberCount: int}>, array{}>
	 *
	 * 200: Teams returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'GET', url: '/api/teams')]
	public function index(): DataResponse {
		$circlesManager = $this->getCirclesManager();
		if ($circlesManager === null) {
			return new DataResponse(['error' => 'Teams app is not available'], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		try {
			$circlesManager->startSuperSession();

			$probe = new \OCA\Circles\Model\Probes\CircleProbe();
			$probe->filterHiddenCircles()
				->filterBackendCircles()
				->filterPersonalCircles()
				->filterSingleCircles();

			$circles = $circlesManager->getCircles($probe);

			$result = array_map(function ($circle) {
				$owner = '';
				$ownerDisplayName = '';
				if ($circle->hasOwner()) {
					$owner = $circle->getOwner()->getUserId();
					$ownerDisplayName = $circle->getOwner()->getDisplayName();
				}
				return [
					'id' => $circle->getSingleId(),
					'displayName' => $circle->getDisplayName() ?: $circle->getName(),
					'owner' => $owner,
					'ownerDisplayName' => $ownerDisplayName,
					'memberCount' => $circle->getPopulation(),
				];
			}, $circles);

			// Sort by owner display name, then by team display name
			usort($result, function ($a, $b) {
				$ownerCmp = strcasecmp($a['ownerDisplayName'], $b['ownerDisplayName']);
				if ($ownerCmp !== 0) {
					return $ownerCmp;
				}
				return strcasecmp($a['displayName'], $b['displayName']);
			});

			return new DataResponse(array_values($result));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching teams: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch teams'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} finally {
			$circlesManager->stopSession();
		}
	}

	/**
	 * Get category permissions for a team (circle)
	 *
	 * @param string $id Team/circle single ID
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Permissions returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'GET', url: '/api/teams/{id}/permissions')]
	public function getPermissions(string $id): DataResponse {
		try {
			$permissions = $this->categoryPermMapper->findByTeamId($id);
			return new DataResponse(array_map(fn ($perm) => $perm->jsonSerialize(), $permissions));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching team permissions: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch permissions'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update category permissions for a team (circle)
	 *
	 * @param string $id Team/circle single ID
	 * @param list<array{categoryId: int, canView: bool, canPost: bool, canReply: bool, canModerate: bool}> $permissions Permissions array
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Permissions updated
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditCategories')]
	#[ApiRoute(verb: 'POST', url: '/api/teams/{id}/permissions')]
	public function updatePermissions(string $id, array $permissions): DataResponse {
		$circlesManager = $this->getCirclesManager();
		if ($circlesManager === null) {
			return new DataResponse(['error' => 'Teams app is not available'], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		try {
			// Verify team exists
			$circlesManager->startSuperSession();
			try {
				$circlesManager->getCircle($id);
			} catch (\OCA\Circles\Exceptions\CircleNotFoundException $e) {
				return new DataResponse(['error' => 'Team not found'], Http::STATUS_NOT_FOUND);
			} finally {
				$circlesManager->stopSession();
			}

			// Delete existing permissions for this team
			$this->categoryPermMapper->deleteByTeamId($id);

			// Insert new permissions
			foreach ($permissions as $perm) {
				$categoryPerm = new CategoryPerm();
				$categoryPerm->setCategoryId($perm['categoryId']);
				$categoryPerm->setTargetType(CategoryPerm::TARGET_TYPE_TEAM);
				$categoryPerm->setTargetId($id);
				$categoryPerm->setCanView($perm['canView'] ?? false);
				$categoryPerm->setCanPost($perm['canPost'] ?? $perm['canView'] ?? false);
				$categoryPerm->setCanReply($perm['canReply'] ?? $perm['canPost'] ?? $perm['canView'] ?? false);
				$categoryPerm->setCanModerate($perm['canModerate'] ?? false);

				$this->categoryPermMapper->insert($categoryPerm);
			}

			return new DataResponse(['success' => true]);
		} catch (\Exception $e) {
			$this->logger->error('Error updating team permissions: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update permissions'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
