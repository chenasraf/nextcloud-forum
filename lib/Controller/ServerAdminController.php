<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Migration\SeedHelper;
use OCA\Forum\Service\StatsService;
use OCA\Forum\Service\UserRoleService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use Psr\Log\LoggerInterface;

class ServerAdminController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private RoleMapper $roleMapper,
		private UserRoleService $userRoleService,
		private IUserManager $userManager,
		private StatsService $statsService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all available roles (for server admin panel)
	 *
	 * @return DataResponse<Http::STATUS_OK, array{roles: list<array<string, mixed>>}, array{}>
	 *
	 * 200: Roles list returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/server-admin/roles')]
	public function getRoles(): DataResponse {
		try {
			$roles = $this->roleMapper->findAll();
			$rolesData = array_map(fn ($role) => [
				'id' => $role->getId(),
				'name' => $role->getName(),
				'roleType' => $role->getRoleType(),
			], $roles);
			return new DataResponse(['roles' => $rolesData]);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching roles: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch roles'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Assign a role to a user (from server admin panel)
	 *
	 * @param string $userId The user ID
	 * @param int $roleId The role ID to assign
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, message: string}, array{}>
	 *
	 * 200: Role assigned successfully
	 */
	#[ApiRoute(verb: 'POST', url: '/api/server-admin/users/{userId}/roles')]
	public function assignRole(string $userId, int $roleId): DataResponse {
		try {
			$user = $this->userManager->get($userId);
			if ($user === null) {
				return new DataResponse([
					'success' => false,
					'message' => "User '$userId' does not exist.",
				], Http::STATUS_NOT_FOUND);
			}

			try {
				$role = $this->roleMapper->find($roleId);
			} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
				return new DataResponse([
					'success' => false,
					'message' => "Role with ID '$roleId' does not exist.",
				], Http::STATUS_NOT_FOUND);
			}

			if ($this->userRoleService->hasRole($userId, $roleId)) {
				return new DataResponse([
					'success' => true,
					'message' => "User '$userId' already has the role '{$role->getName()}'.",
				]);
			}

			$this->userRoleService->assignRole($userId, $roleId, skipIfExists: false);
			$this->logger->info("Assigned role '{$role->getName()}' to user '$userId'");

			return new DataResponse([
				'success' => true,
				'message' => "Successfully assigned role '{$role->getName()}' to user '$userId'.",
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error assigning role: ' . $e->getMessage());
			return new DataResponse([
				'success' => false,
				'message' => 'Failed to assign role: ' . $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Run the repair seeds command to restore default forum data
	 *
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, message: string}, array{}>
	 *
	 * 200: Seeds repaired successfully
	 */
	#[ApiRoute(verb: 'POST', url: '/api/server-admin/repair-seeds')]
	public function repairSeeds(): DataResponse {
		try {
			$messages = [];
			$migrationOutput = new class($messages) implements IOutput {
				/** @var array<string> */
				private array $messages;

				public function __construct(array &$messages) {
					$this->messages = &$messages;
				}

				public function info($message): void {
					$this->messages[] = $message;
				}

				public function warning($message): void {
					$this->messages[] = '[Warning] ' . $message;
				}

				public function debug($message): void {
					$this->messages[] = '[Debug] ' . $message;
				}

				public function startProgress($max = 0): void {
				}

				public function advance($step = 1, $description = ''): void {
				}

				public function finishProgress(): void {
				}
			};

			SeedHelper::seedAll($migrationOutput, true);

			$this->logger->info('Forum repair seeds completed successfully');
			return new DataResponse([
				'success' => true,
				'message' => implode("\n", $messages),
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error running repair seeds: ' . $e->getMessage());
			return new DataResponse([
				'success' => false,
				'message' => 'Failed to repair seeds: ' . $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Rebuild all forum statistics (users, categories, threads)
	 *
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, message: string}, array{}>
	 *
	 * 200: Stats rebuilt successfully
	 */
	#[ApiRoute(verb: 'POST', url: '/api/server-admin/rebuild-stats')]
	public function rebuildStats(): DataResponse {
		try {
			$userResult = $this->statsService->rebuildAllUserStats();
			$categoryResult = $this->statsService->rebuildAllCategoryStats();
			$threadResult = $this->statsService->rebuildAllThreadStats();

			$messages = [];
			$messages[] = sprintf(
				'Users processed: %d, created: %d, updated: %d',
				$userResult['users'],
				$userResult['created'],
				$userResult['updated']
			);
			$messages[] = sprintf(
				'Categories processed: %d, updated: %d',
				$categoryResult['categories'],
				$categoryResult['updated']
			);
			$messages[] = sprintf(
				'Threads processed: %d, updated: %d',
				$threadResult['threads'],
				$threadResult['updated']
			);

			$this->logger->info('Forum stats rebuild completed successfully');
			return new DataResponse([
				'success' => true,
				'message' => implode("\n", $messages),
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error rebuilding stats: ' . $e->getMessage());
			return new DataResponse([
				'success' => false,
				'message' => 'Failed to rebuild stats: ' . $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
