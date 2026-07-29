<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\Db\BBCodeMapper;
use OCA\Forum\Db\ForumUserMapper;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Db\UserRoleMapper;
use OCA\Forum\Service\AdminSettingsService;
use OCA\Forum\Service\BBCodeService;
use OCA\Forum\Service\GuestService;
use OCA\Forum\Service\UserService;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\Accounts\PropertyDoesNotExistException;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase {
	private UserService $service;
	/** @var IUserManager&MockObject */
	private IUserManager $userManager;
	/** @var IAccountManager&MockObject */
	private IAccountManager $accountManager;

	protected function setUp(): void {
		$this->userManager = $this->createMock(IUserManager::class);
		$this->accountManager = $this->createMock(IAccountManager::class);

		$this->service = new UserService(
			$this->userManager,
			$this->createMock(ForumUserMapper::class),
			$this->createMock(RoleMapper::class),
			$this->createMock(UserRoleMapper::class),
			$this->createMock(BBCodeMapper::class),
			$this->createMock(BBCodeService::class),
			$this->createMock(AdminSettingsService::class),
			$this->createMock(GuestService::class),
			$this->createMock(IL10N::class),
			$this->accountManager,
		);
	}

	// ── getUserEmail ─────────────────────────────────────────────────

	public function testGetUserEmailReturnsEmailWhenScopeIsNotPrivate(): void {
		$user = $this->createUserWithEmailScope('alice@example.com', IAccountManager::SCOPE_LOCAL);

		$this->assertSame('alice@example.com', $this->service->getUserEmail($user));
	}

	public function testGetUserEmailReturnsNullWhenScopeIsPrivate(): void {
		$user = $this->createUserWithEmailScope('alice@example.com', IAccountManager::SCOPE_PRIVATE);

		$this->assertNull($this->service->getUserEmail($user));
	}

	public function testGetUserEmailReturnsNullWhenUserHasNoEmail(): void {
		$user = $this->createUserWithEmailScope(null, IAccountManager::SCOPE_FEDERATED);

		$this->assertNull($this->service->getUserEmail($user));
	}

	public function testGetUserEmailReturnsNullWhenEmailIsEmptyString(): void {
		$user = $this->createUserWithEmailScope('', IAccountManager::SCOPE_LOCAL);

		$this->assertNull($this->service->getUserEmail($user));
	}

	public function testGetUserEmailReturnsEmailWhenAccountPropertyMissing(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('bob@example.com');

		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')
			->with(IAccountManager::PROPERTY_EMAIL)
			->willThrowException(new PropertyDoesNotExistException(IAccountManager::PROPERTY_EMAIL));
		$this->accountManager->method('getAccount')->with($user)->willReturn($account);

		$this->assertSame('bob@example.com', $this->service->getUserEmail($user));
	}

	// ── getUserEmailById ─────────────────────────────────────────────

	public function testGetUserEmailByIdReturnsNullWhenUserDoesNotExist(): void {
		$this->userManager->method('get')->with('ghost')->willReturn(null);

		$this->assertNull($this->service->getUserEmailById('ghost'));
	}

	public function testGetUserEmailByIdDelegatesToGetUserEmail(): void {
		$user = $this->createUserWithEmailScope('carol@example.com', IAccountManager::SCOPE_LOCAL);
		$this->userManager->method('get')->with('carol')->willReturn($user);

		$this->assertSame('carol@example.com', $this->service->getUserEmailById('carol'));
	}

	/**
	 * @param string|null $email Value returned by IUser::getEMailAddress()
	 */
	private function createUserWithEmailScope(?string $email, string $scope): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn($email);

		$property = $this->createMock(IAccountProperty::class);
		$property->method('getScope')->willReturn($scope);

		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')
			->with(IAccountManager::PROPERTY_EMAIL)
			->willReturn($property);

		$this->accountManager->method('getAccount')->with($user)->willReturn($account);

		return $user;
	}
}
