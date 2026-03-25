<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\AdminController;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\ForumUserMapper;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Service\AdminSettingsService;
use OCA\Forum\Service\StatsService;
use OCA\Forum\Service\UserRoleService;
use OCA\Forum\Service\UserService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AdminControllerTest extends TestCase {
	private AdminController $controller;

	/** @var ForumUserMapper&MockObject */
	private ForumUserMapper $forumUserMapper;
	/** @var UserService&MockObject */
	private UserService $userService;
	/** @var ThreadMapper&MockObject */
	private ThreadMapper $threadMapper;
	/** @var PostMapper&MockObject */
	private PostMapper $postMapper;
	/** @var CategoryMapper&MockObject */
	private CategoryMapper $categoryMapper;
	/** @var RoleMapper&MockObject */
	private RoleMapper $roleMapper;
	/** @var UserRoleService&MockObject */
	private UserRoleService $userRoleService;
	/** @var IUserManager&MockObject */
	private IUserManager $userManager;
	/** @var IUserSession&MockObject */
	private IUserSession $userSession;
	/** @var AdminSettingsService&MockObject */
	private AdminSettingsService $settingsService;
	/** @var StatsService&MockObject */
	private StatsService $statsService;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	/** @var IRequest&MockObject */
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->forumUserMapper = $this->createMock(ForumUserMapper::class);
		$this->userService = $this->createMock(UserService::class);
		$this->threadMapper = $this->createMock(ThreadMapper::class);
		$this->postMapper = $this->createMock(PostMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->roleMapper = $this->createMock(RoleMapper::class);
		$this->userRoleService = $this->createMock(UserRoleService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->settingsService = $this->createMock(AdminSettingsService::class);
		$this->statsService = $this->createMock(StatsService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new AdminController(
			Application::APP_ID,
			$this->request,
			$this->forumUserMapper,
			$this->userService,
			$this->threadMapper,
			$this->postMapper,
			$this->categoryMapper,
			$this->roleMapper,
			$this->userRoleService,
			$this->userManager,
			$this->userSession,
			$this->settingsService,
			$this->statsService,
			$this->logger
		);
	}

	// ── Settings tests ───────────────────────────────────────────────

	public function testGetSettingsReturnsAllSettings(): void {
		$allSettings = [
			'title' => 'My Forum',
			'subtitle' => 'Welcome!',
			'allow_guest_access' => false,
			'is_initialized' => true,
			'public_edit_history' => true,
			'allow_edit_history_user_override' => false,
		];

		$this->settingsService->expects($this->once())
			->method('getAllSettings')
			->willReturn($allSettings);

		$response = $this->controller->getSettings();

		$this->assertEquals(200, $response->getStatus());
		$this->assertEquals($allSettings, $response->getData());
	}

	public function testUpdateSettingsPassesAllFieldsToService(): void {
		$expectedUpdate = [
			AdminSettingsService::SETTING_TITLE => 'New Title',
			AdminSettingsService::SETTING_SUBTITLE => 'New Subtitle',
			AdminSettingsService::SETTING_ALLOW_GUEST_ACCESS => true,
			AdminSettingsService::SETTING_PUBLIC_EDIT_HISTORY => false,
			AdminSettingsService::SETTING_ALLOW_EDIT_HISTORY_USER_OVERRIDE => true,
		];

		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with($expectedUpdate)
			->willReturn(array_merge($expectedUpdate, ['is_initialized' => true]));

		$response = $this->controller->updateSettings(
			'New Title',
			'New Subtitle',
			true,
			false,
			true,
		);

		$this->assertEquals(200, $response->getStatus());
	}

	public function testUpdateSettingsOmitsNullValues(): void {
		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with($this->callback(function (array $settings) {
				// Only public_edit_history and allow_edit_history_user_override should be present
				return count($settings) === 2
					&& array_key_exists(AdminSettingsService::SETTING_PUBLIC_EDIT_HISTORY, $settings)
					&& array_key_exists(AdminSettingsService::SETTING_ALLOW_EDIT_HISTORY_USER_OVERRIDE, $settings)
					&& $settings[AdminSettingsService::SETTING_PUBLIC_EDIT_HISTORY] === true
					&& $settings[AdminSettingsService::SETTING_ALLOW_EDIT_HISTORY_USER_OVERRIDE] === true;
			}))
			->willReturn([]);

		$response = $this->controller->updateSettings(
			null,
			null,
			null,
			true,
			true,
		);

		$this->assertEquals(200, $response->getStatus());
	}

	public function testUpdateSettingsHandlesException(): void {
		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->willThrowException(new \Exception('DB error'));

		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('Error updating settings'));

		$response = $this->controller->updateSettings('Title');

		$this->assertEquals(500, $response->getStatus());
	}

	// ── Dashboard tests ─────────────────────────────────────────────

	public function testDashboardEnrichesContributorsWithDisplayNames(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($user);

		$this->forumUserMapper->method('countAll')->willReturn(5);
		$this->threadMapper->method('countAll')->willReturn(10);
		$this->postMapper->method('countAll')->willReturn(50);
		$this->categoryMapper->method('countAll')->willReturn(3);
		$this->forumUserMapper->method('countSince')->willReturn(1);
		$this->threadMapper->method('countSince')->willReturn(2);
		$this->postMapper->method('countSince')->willReturn(5);

		$this->forumUserMapper->method('getTopContributors')->willReturn([
			['userId' => 'alice', 'postCount' => 10, 'threadCount' => 3],
		]);
		$this->forumUserMapper->method('getTopContributorsSince')->willReturn([
			['userId' => 'alice', 'postCount' => 2, 'threadCount' => 1],
		]);

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->with(['alice'])
			->willReturn([
				'alice' => [
					'userId' => 'alice',
					'displayName' => 'Alice Smith',
					'isDeleted' => false,
					'roles' => [['id' => 3, 'name' => 'User', 'roleType' => 'default']],
					'signature' => null,
					'signatureRaw' => null,
				],
			]);

		$response = $this->controller->dashboard();
		$data = $response->getData();

		$this->assertEquals('Alice Smith', $data['topContributorsAllTime'][0]['displayName']);
		$this->assertFalse($data['topContributorsAllTime'][0]['isGuest']);
		$this->assertNotEmpty($data['topContributorsAllTime'][0]['roles']);

		$this->assertEquals('Alice Smith', $data['topContributorsRecent'][0]['displayName']);
		$this->assertFalse($data['topContributorsRecent'][0]['isGuest']);
	}

	public function testDashboardEnrichesGuestContributors(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($user);

		$this->forumUserMapper->method('countAll')->willReturn(5);
		$this->threadMapper->method('countAll')->willReturn(10);
		$this->postMapper->method('countAll')->willReturn(50);
		$this->categoryMapper->method('countAll')->willReturn(3);
		$this->forumUserMapper->method('countSince')->willReturn(1);
		$this->threadMapper->method('countSince')->willReturn(2);
		$this->postMapper->method('countSince')->willReturn(5);

		$guestId = 'guest:abcdef1234567890abcdef1234567890';
		$guestRole = ['id' => 4, 'name' => 'Guest', 'roleType' => 'guest'];

		$this->forumUserMapper->method('getTopContributors')->willReturn([]);
		$this->forumUserMapper->method('getTopContributorsSince')->willReturn([
			['userId' => $guestId, 'postCount' => 3, 'threadCount' => 1],
		]);

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->with([$guestId])
			->willReturn([
				$guestId => [
					'userId' => $guestId,
					'displayName' => 'BrightMountain42',
					'isDeleted' => false,
					'isGuest' => true,
					'roles' => [$guestRole],
					'signature' => null,
					'signatureRaw' => null,
				],
			]);

		$response = $this->controller->dashboard();
		$data = $response->getData();

		$this->assertCount(1, $data['topContributorsRecent']);
		$contributor = $data['topContributorsRecent'][0];
		$this->assertEquals('BrightMountain42', $contributor['displayName']);
		$this->assertTrue($contributor['isGuest']);
		$this->assertEquals([$guestRole], $contributor['roles']);
		$this->assertEquals(3, $contributor['postCount']);
		$this->assertEquals(1, $contributor['threadCount']);
	}
}
