<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\Service\AdminSettingsService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AdminSettingsServiceTest extends TestCase {
	private AdminSettingsService $service;
	/** @var IAppConfig&MockObject */
	private IAppConfig $config;
	/** @var IL10N&MockObject */
	private IL10N $l10n;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;

	protected function setUp(): void {
		$this->config = $this->createMock(IAppConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		// Mock translations
		$this->l10n->method('t')
			->willReturnCallback(function ($text) {
				return $text;
			});

		$this->service = new AdminSettingsService(
			$this->config,
			$this->l10n,
			$this->logger
		);
	}

	public function testGetAllSettingsReturnsAllSettings(): void {
		$this->config->expects($this->exactly(2))
			->method('getAppValueString')
			->willReturnCallback(function ($key, $default, $lazy) {
				return match ($key) {
					AdminSettingsService::SETTING_TITLE => 'My Forum',
					AdminSettingsService::SETTING_SUBTITLE => 'Welcome!',
					default => $default,
				};
			});

		$this->config->expects($this->once())
			->method('getAppValueBool')
			->with(AdminSettingsService::SETTING_ALLOW_GUEST_ACCESS, false, true)
			->willReturn(true);

		$result = $this->service->getAllSettings();

		$this->assertIsArray($result);
		$this->assertCount(3, $result);
		$this->assertEquals('My Forum', $result[AdminSettingsService::SETTING_TITLE]);
		$this->assertEquals('Welcome!', $result[AdminSettingsService::SETTING_SUBTITLE]);
		$this->assertTrue($result[AdminSettingsService::SETTING_ALLOW_GUEST_ACCESS]);
	}

	public function testGetSettingReturnsCorrectStringValue(): void {
		$key = AdminSettingsService::SETTING_TITLE;

		$this->config->expects($this->once())
			->method('getAppValueString')
			->with($key, 'Forum', true)
			->willReturn('Custom Title');

		$result = $this->service->getSetting($key);

		$this->assertEquals('Custom Title', $result);
	}

	public function testGetSettingReturnsCorrectBoolValue(): void {
		$key = AdminSettingsService::SETTING_ALLOW_GUEST_ACCESS;

		$this->config->expects($this->once())
			->method('getAppValueBool')
			->with($key, false, true)
			->willReturn(true);

		$result = $this->service->getSetting($key);

		$this->assertTrue($result);
	}

	public function testGetSettingReturnsDefaultWhenNotSet(): void {
		$key = AdminSettingsService::SETTING_TITLE;

		$this->config->expects($this->once())
			->method('getAppValueString')
			->with($key, 'Forum', true)
			->willReturn('Forum');

		$result = $this->service->getSetting($key);

		$this->assertEquals('Forum', $result);
	}

	public function testGetSettingThrowsExceptionForInvalidKey(): void {
		$invalidKey = 'invalid_key';

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid setting key: $invalidKey");

		$this->service->getSetting($invalidKey);
	}

	public function testSetSettingSetsStringValue(): void {
		$key = AdminSettingsService::SETTING_TITLE;
		$value = 'New Title';

		$this->config->expects($this->once())
			->method('setAppValueString')
			->with($key, $value, true)
			->willReturn(true);

		$this->service->setSetting($key, $value);
	}

	public function testSetSettingSetsBoolValue(): void {
		$key = AdminSettingsService::SETTING_ALLOW_GUEST_ACCESS;
		$value = true;

		$this->config->expects($this->once())
			->method('setAppValueBool')
			->with($key, $value, true)
			->willReturn(true);

		$this->service->setSetting($key, $value);
	}

	public function testSetSettingThrowsExceptionForInvalidKey(): void {
		$invalidKey = 'invalid_key';

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid setting key: $invalidKey");

		$this->service->setSetting($invalidKey, 'value');
	}

	public function testUpdateSettingsUpdatesMultipleValues(): void {
		$settings = [
			AdminSettingsService::SETTING_TITLE => 'New Title',
			AdminSettingsService::SETTING_SUBTITLE => 'New Subtitle',
			AdminSettingsService::SETTING_ALLOW_GUEST_ACCESS => true,
		];

		$this->config->expects($this->exactly(2))
			->method('setAppValueString')
			->willReturnCallback(function ($key, $value, $lazy) use ($settings) {
				if ($key === AdminSettingsService::SETTING_TITLE) {
					$this->assertEquals($settings[AdminSettingsService::SETTING_TITLE], $value);
				} elseif ($key === AdminSettingsService::SETTING_SUBTITLE) {
					$this->assertEquals($settings[AdminSettingsService::SETTING_SUBTITLE], $value);
				}
				return true;
			});

		$this->config->expects($this->once())
			->method('setAppValueBool')
			->with(AdminSettingsService::SETTING_ALLOW_GUEST_ACCESS, true, true)
			->willReturn(true);

		$this->config->expects($this->exactly(2))
			->method('getAppValueString')
			->willReturnCallback(function ($key, $default, $lazy) use ($settings) {
				return match ($key) {
					AdminSettingsService::SETTING_TITLE => $settings[AdminSettingsService::SETTING_TITLE],
					AdminSettingsService::SETTING_SUBTITLE => $settings[AdminSettingsService::SETTING_SUBTITLE],
					default => $default,
				};
			});

		$this->config->expects($this->once())
			->method('getAppValueBool')
			->with(AdminSettingsService::SETTING_ALLOW_GUEST_ACCESS, false, true)
			->willReturn(true);

		$result = $this->service->updateSettings($settings);

		$this->assertIsArray($result);
		$this->assertEquals('New Title', $result[AdminSettingsService::SETTING_TITLE]);
		$this->assertEquals('New Subtitle', $result[AdminSettingsService::SETTING_SUBTITLE]);
		$this->assertTrue($result[AdminSettingsService::SETTING_ALLOW_GUEST_ACCESS]);
	}

	public function testUpdateSettingsThrowsExceptionForInvalidKey(): void {
		$settings = [
			'invalid_key' => 'value',
		];

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid setting key: invalid_key');

		$this->service->updateSettings($settings);
	}

	public function testUpdateSettingsWithPartialUpdate(): void {
		$settings = [
			AdminSettingsService::SETTING_TITLE => 'Updated Title',
		];

		$this->config->expects($this->once())
			->method('setAppValueString')
			->with(AdminSettingsService::SETTING_TITLE, 'Updated Title', true)
			->willReturn(true);

		$this->config->expects($this->exactly(2))
			->method('getAppValueString')
			->willReturnCallback(function ($key, $default, $lazy) {
				return match ($key) {
					AdminSettingsService::SETTING_TITLE => 'Updated Title',
					AdminSettingsService::SETTING_SUBTITLE => 'Welcome to the forum!',
					default => $default,
				};
			});

		$this->config->expects($this->once())
			->method('getAppValueBool')
			->with(AdminSettingsService::SETTING_ALLOW_GUEST_ACCESS, false, true)
			->willReturn(false);

		$result = $this->service->updateSettings($settings);

		$this->assertIsArray($result);
		$this->assertEquals('Updated Title', $result[AdminSettingsService::SETTING_TITLE]);
		$this->assertEquals('Welcome to the forum!', $result[AdminSettingsService::SETTING_SUBTITLE]);
		$this->assertFalse($result[AdminSettingsService::SETTING_ALLOW_GUEST_ACCESS]);
	}

	public function testGetDefaultReturnsCorrectDefaults(): void {
		// Test title default
		$this->config->expects($this->once())
			->method('getAppValueString')
			->with(AdminSettingsService::SETTING_TITLE, 'Forum', true)
			->willReturn('Forum');

		$result = $this->service->getSetting(AdminSettingsService::SETTING_TITLE);
		$this->assertEquals('Forum', $result);
	}

	public function testSetSettingCoercesBooleanValue(): void {
		$key = AdminSettingsService::SETTING_ALLOW_GUEST_ACCESS;

		// Test with integer 1 (should be coerced to true)
		$this->config->expects($this->once())
			->method('setAppValueBool')
			->with($key, true, true)
			->willReturn(true);

		$this->service->setSetting($key, 1);
	}

	public function testSetSettingCoercesStringValue(): void {
		$key = AdminSettingsService::SETTING_TITLE;

		// Test with integer (should be coerced to string)
		$this->config->expects($this->once())
			->method('setAppValueString')
			->with($key, '123', true)
			->willReturn(true);

		$this->service->setSetting($key, 123);
	}
}
