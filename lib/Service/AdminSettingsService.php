<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCP\AppFramework\Services\IAppConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class AdminSettingsService {
	/** Setting key for forum title */
	public const SETTING_TITLE = 'title';

	/** Setting key for forum subtitle */
	public const SETTING_SUBTITLE = 'subtitle';

	/** Setting key for guest access */
	public const SETTING_ALLOW_GUEST_ACCESS = 'allow_guest_access';

	/** @var array<string> List of valid setting keys */
	private const VALID_KEYS = [
		self::SETTING_TITLE,
		self::SETTING_SUBTITLE,
		self::SETTING_ALLOW_GUEST_ACCESS,
	];

	public function __construct(
		private IAppConfig $config,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Get default value for a setting
	 *
	 * @param string $key The setting key
	 * @return mixed The default value
	 */
	private function getDefault(string $key): mixed {
		return match ($key) {
			self::SETTING_TITLE => $this->l10n->t('Forum'),
			self::SETTING_SUBTITLE => $this->l10n->t('Welcome to the forum!'),
			self::SETTING_ALLOW_GUEST_ACCESS => false,
			default => null,
		};
	}

	/**
	 * Get all settings
	 *
	 * @return array<string, mixed> All settings
	 */
	public function getAllSettings(): array {
		$settings = [];

		foreach (self::VALID_KEYS as $key) {
			$settings[$key] = $this->getSetting($key);
		}

		return $settings;
	}

	/**
	 * Get a single setting
	 *
	 * @param string $key The setting key
	 * @return mixed The setting value
	 * @throws \InvalidArgumentException If the setting key is invalid
	 */
	public function getSetting(string $key): mixed {
		if (!in_array($key, self::VALID_KEYS, true)) {
			throw new \InvalidArgumentException("Invalid setting key: $key");
		}

		$default = $this->getDefault($key);

		return match ($key) {
			self::SETTING_ALLOW_GUEST_ACCESS => $this->config->getAppValueBool($key, $default, true),
			default => $this->config->getAppValueString($key, $default, true),
		};
	}

	/**
	 * Update multiple settings
	 *
	 * @param array<string, mixed> $settings Key-value pairs of settings to update
	 * @return array<string, mixed> All settings after update
	 * @throws \InvalidArgumentException If any setting key is invalid
	 */
	public function updateSettings(array $settings): array {
		// Validate all keys before updating
		foreach ($settings as $key => $value) {
			if (!in_array($key, self::VALID_KEYS, true)) {
				throw new \InvalidArgumentException("Invalid setting key: $key");
			}
		}

		// Update each setting
		foreach ($settings as $key => $value) {
			$this->setSetting($key, $value);
		}

		// Return all settings after update
		return $this->getAllSettings();
	}

	/**
	 * Set a single setting
	 *
	 * @param string $key The setting key
	 * @param mixed $value The setting value
	 * @throws \InvalidArgumentException If the setting key is invalid
	 */
	public function setSetting(string $key, mixed $value): void {
		if (!in_array($key, self::VALID_KEYS, true)) {
			throw new \InvalidArgumentException("Invalid setting key: $key");
		}

		if ($key === self::SETTING_ALLOW_GUEST_ACCESS) {
			$this->config->setAppValueBool($key, (bool)$value, true);
		} else {
			$this->config->setAppValueString($key, (string)$value, true);
		}
	}
}
