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
			$this->logger
		);
	}

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
