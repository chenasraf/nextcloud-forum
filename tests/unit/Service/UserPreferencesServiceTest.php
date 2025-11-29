<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Db\ForumUserMapper;
use OCA\Forum\Service\UserPreferencesService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UserPreferencesServiceTest extends TestCase {
	private UserPreferencesService $service;
	private IConfig $config;
	private ForumUserMapper $forumUserMapper;
	private LoggerInterface $logger;

	protected function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->forumUserMapper = $this->createMock(ForumUserMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		// By default, mock no forum user (no signature)
		$this->forumUserMapper->method('find')
			->willThrowException(new DoesNotExistException(''));

		$this->service = new UserPreferencesService(
			$this->config,
			$this->forumUserMapper,
			$this->logger
		);
	}

	public function testGetAllPreferencesReturnsAllPreferences(): void {
		$userId = 'user1';

		// Only config-based preferences (signature is from forum_users)
		$this->config->expects($this->exactly(2))
			->method('getUserValue')
			->willReturnCallback(function ($uid, $appId, $key, $default) use ($userId) {
				$this->assertEquals($userId, $uid);
				$this->assertEquals(Application::APP_ID, $appId);

				return match ($key) {
					UserPreferencesService::PREF_AUTO_SUBSCRIBE_CREATED_THREADS => 'true',
					UserPreferencesService::PREF_UPLOAD_DIRECTORY => 'Forum',
					default => $default,
				};
			});

		$result = $this->service->getAllPreferences($userId);

		$this->assertIsArray($result);
		$this->assertCount(3, $result);
		$this->assertTrue($result[UserPreferencesService::PREF_AUTO_SUBSCRIBE_CREATED_THREADS]);
		$this->assertEquals('Forum', $result[UserPreferencesService::PREF_UPLOAD_DIRECTORY]);
		$this->assertEquals('', $result[UserPreferencesService::PREF_SIGNATURE]);
	}

	public function testGetPreferenceReturnsCorrectValue(): void {
		$userId = 'user1';
		$key = UserPreferencesService::PREF_AUTO_SUBSCRIBE_CREATED_THREADS;

		$this->config->expects($this->once())
			->method('getUserValue')
			->with($userId, Application::APP_ID, $key, true)
			->willReturn('false');

		$result = $this->service->getPreference($userId, $key);

		$this->assertFalse($result);
	}

	public function testGetPreferenceReturnsDefaultWhenNotSet(): void {
		$userId = 'user1';
		$key = UserPreferencesService::PREF_UPLOAD_DIRECTORY;

		$this->config->expects($this->once())
			->method('getUserValue')
			->with($userId, Application::APP_ID, $key, 'Forum')
			->willReturn('Forum');

		$result = $this->service->getPreference($userId, $key);

		$this->assertEquals('Forum', $result);
	}

	public function testGetPreferenceThrowsExceptionForInvalidKey(): void {
		$userId = 'user1';
		$invalidKey = 'invalid_key';

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid preference key: $invalidKey");

		$this->service->getPreference($userId, $invalidKey);
	}

	public function testSetPreferenceSetsValue(): void {
		$userId = 'user1';
		$key = UserPreferencesService::PREF_AUTO_SUBSCRIBE_CREATED_THREADS;
		$value = false;

		$this->config->expects($this->once())
			->method('setUserValue')
			->with($userId, Application::APP_ID, $key, 'false');

		$this->service->setPreference($userId, $key, $value);
	}

	public function testSetPreferenceThrowsExceptionForInvalidKey(): void {
		$userId = 'user1';
		$invalidKey = 'invalid_key';

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid preference key: $invalidKey");

		$this->service->setPreference($userId, $invalidKey, 'value');
	}

	public function testUpdatePreferencesUpdatesMultipleValues(): void {
		$userId = 'user1';
		$preferences = [
			UserPreferencesService::PREF_AUTO_SUBSCRIBE_CREATED_THREADS => false,
			UserPreferencesService::PREF_UPLOAD_DIRECTORY => 'Documents',
		];

		$this->config->expects($this->exactly(2))
			->method('setUserValue')
			->willReturnCallback(function ($uid, $appId, $key, $value) use ($userId, $preferences) {
				$this->assertEquals($userId, $uid);
				$this->assertEquals(Application::APP_ID, $appId);

				if ($key === UserPreferencesService::PREF_AUTO_SUBSCRIBE_CREATED_THREADS) {
					$this->assertEquals('false', $value);
				} elseif ($key === UserPreferencesService::PREF_UPLOAD_DIRECTORY) {
					$this->assertEquals('Documents', $value);
				}
			});

		$this->config->expects($this->exactly(2))
			->method('getUserValue')
			->willReturnCallback(function ($uid, $appId, $key, $default) use ($userId) {
				$this->assertEquals($userId, $uid);
				$this->assertEquals(Application::APP_ID, $appId);

				return match ($key) {
					UserPreferencesService::PREF_AUTO_SUBSCRIBE_CREATED_THREADS => 'false',
					UserPreferencesService::PREF_UPLOAD_DIRECTORY => 'Documents',
					default => $default,
				};
			});

		$result = $this->service->updatePreferences($userId, $preferences);

		$this->assertIsArray($result);
		$this->assertCount(3, $result);
		$this->assertFalse($result[UserPreferencesService::PREF_AUTO_SUBSCRIBE_CREATED_THREADS]);
		$this->assertEquals('Documents', $result[UserPreferencesService::PREF_UPLOAD_DIRECTORY]);
		$this->assertEquals('', $result[UserPreferencesService::PREF_SIGNATURE]);
	}

	public function testUpdatePreferencesThrowsExceptionForInvalidKey(): void {
		$userId = 'user1';
		$preferences = [
			'invalid_key' => 'value',
		];

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid preference key: invalid_key');

		$this->service->updatePreferences($userId, $preferences);
	}

	public function testParseValueHandlesBooleans(): void {
		$userId = 'user1';
		$key = UserPreferencesService::PREF_AUTO_SUBSCRIBE_CREATED_THREADS;

		// Test 'true'
		$this->config->expects($this->once())
			->method('getUserValue')
			->with($userId, Application::APP_ID, $key, true)
			->willReturn('true');

		$result = $this->service->getPreference($userId, $key);
		$this->assertTrue($result);
	}

	public function testParseValueHandlesStrings(): void {
		$userId = 'user1';
		$key = UserPreferencesService::PREF_UPLOAD_DIRECTORY;

		$this->config->expects($this->once())
			->method('getUserValue')
			->with($userId, Application::APP_ID, $key, 'Forum')
			->willReturn('MyFolder');

		$result = $this->service->getPreference($userId, $key);
		$this->assertEquals('MyFolder', $result);
	}

	public function testStringifyValueHandlesBooleans(): void {
		$userId = 'user1';
		$key = UserPreferencesService::PREF_AUTO_SUBSCRIBE_CREATED_THREADS;

		// Test true
		$this->config->expects($this->once())
			->method('setUserValue')
			->with($userId, Application::APP_ID, $key, 'true');

		$this->service->setPreference($userId, $key, true);
	}

	public function testStringifyValueHandlesStrings(): void {
		$userId = 'user1';
		$key = UserPreferencesService::PREF_UPLOAD_DIRECTORY;

		$this->config->expects($this->once())
			->method('setUserValue')
			->with($userId, Application::APP_ID, $key, 'Documents');

		$this->service->setPreference($userId, $key, 'Documents');
	}
}
