<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Db\ForumUserMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class UserPreferencesService {
	/** Preference key for auto-subscribing to created threads */
	public const PREF_AUTO_SUBSCRIBE_CREATED_THREADS = 'auto_subscribe_created_threads';

	/** Preference key for upload directory path */
	public const PREF_UPLOAD_DIRECTORY = 'upload_directory';

	/** Preference key for user signature (stored in forum_users table) */
	public const PREF_SIGNATURE = 'signature';

	/** @var array<string, mixed> Default preference values */
	private const DEFAULTS = [
		self::PREF_AUTO_SUBSCRIBE_CREATED_THREADS => true,
		self::PREF_UPLOAD_DIRECTORY => 'Forum',
		self::PREF_SIGNATURE => '',
	];

	/** @var array<string> List of valid preference keys */
	private const VALID_KEYS = [
		self::PREF_AUTO_SUBSCRIBE_CREATED_THREADS,
		self::PREF_UPLOAD_DIRECTORY,
		self::PREF_SIGNATURE,
	];

	/** @var array<string> Keys stored in forum_users table instead of config */
	private const FORUM_USER_KEYS = [
		self::PREF_SIGNATURE,
	];

	public function __construct(
		private IConfig $config,
		private ForumUserMapper $forumUserMapper,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Get all user preferences
	 *
	 * @param string $userId The user ID
	 * @return array<string, mixed> All user preferences
	 */
	public function getAllPreferences(string $userId): array {
		$preferences = [];

		foreach (self::VALID_KEYS as $key) {
			$preferences[$key] = $this->getPreference($userId, $key);
		}

		return $preferences;
	}

	/**
	 * Get a single user preference
	 *
	 * @param string $userId The user ID
	 * @param string $key The preference key
	 * @return mixed The preference value
	 * @throws \InvalidArgumentException If the preference key is invalid
	 */
	public function getPreference(string $userId, string $key): mixed {
		if (!in_array($key, self::VALID_KEYS, true)) {
			throw new \InvalidArgumentException("Invalid preference key: $key");
		}

		// Handle keys stored in forum_users table
		if (in_array($key, self::FORUM_USER_KEYS, true)) {
			return $this->getForumUserValue($userId, $key);
		}

		$default = self::DEFAULTS[$key] ?? null;
		$value = $this->config->getUserValue($userId, Application::APP_ID, $key, $default);

		return $this->parseValue($value);
	}

	/**
	 * Update multiple user preferences
	 *
	 * @param string $userId The user ID
	 * @param array<string, mixed> $preferences Key-value pairs of preferences to update
	 * @return array<string, mixed> All user preferences after update
	 * @throws \InvalidArgumentException If any preference key is invalid
	 */
	public function updatePreferences(string $userId, array $preferences): array {
		// Validate all keys before updating
		foreach ($preferences as $key => $value) {
			if (!in_array($key, self::VALID_KEYS, true)) {
				throw new \InvalidArgumentException("Invalid preference key: $key");
			}
		}

		// Update each preference
		foreach ($preferences as $key => $value) {
			$this->setPreference($userId, $key, $value);
		}

		// Return all preferences after update
		return $this->getAllPreferences($userId);
	}

	/**
	 * Set a single user preference
	 *
	 * @param string $userId The user ID
	 * @param string $key The preference key
	 * @param mixed $value The preference value
	 * @throws \InvalidArgumentException If the preference key is invalid
	 */
	public function setPreference(string $userId, string $key, mixed $value): void {
		if (!in_array($key, self::VALID_KEYS, true)) {
			throw new \InvalidArgumentException("Invalid preference key: $key");
		}

		// Handle keys stored in forum_users table
		if (in_array($key, self::FORUM_USER_KEYS, true)) {
			$this->setForumUserValue($userId, $key, $value);
			return;
		}

		$stringValue = $this->stringifyValue($value);
		$this->config->setUserValue($userId, Application::APP_ID, $key, $stringValue);
	}

	/**
	 * Parse a string value back to its proper type
	 *
	 * @param mixed $value The value to parse
	 * @return mixed The parsed value
	 */
	private function parseValue(mixed $value): mixed {
		if ($value === 'true') {
			return true;
		}
		if ($value === 'false') {
			return false;
		}
		if (is_numeric($value)) {
			return strpos($value, '.') !== false ? (float)$value : (int)$value;
		}
		return $value;
	}

	/**
	 * Convert a value to string for storage
	 *
	 * @param mixed $value The value to stringify
	 * @return string The stringified value
	 */
	private function stringifyValue(mixed $value): string {
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}
		return (string)$value;
	}

	/**
	 * Get a value from forum_users table
	 *
	 * @param string $userId The user ID
	 * @param string $key The preference key
	 * @return mixed The value
	 */
	private function getForumUserValue(string $userId, string $key): mixed {
		try {
			$forumUser = $this->forumUserMapper->find($userId);
			return match ($key) {
				self::PREF_SIGNATURE => $forumUser->getSignature() ?? '',
				default => self::DEFAULTS[$key] ?? null,
			};
		} catch (DoesNotExistException $e) {
			return self::DEFAULTS[$key] ?? null;
		}
	}

	/**
	 * Set a value in forum_users table
	 *
	 * @param string $userId The user ID
	 * @param string $key The preference key
	 * @param mixed $value The value to set
	 */
	private function setForumUserValue(string $userId, string $key, mixed $value): void {
		$forumUser = $this->forumUserMapper->createOrUpdate($userId);

		match ($key) {
			self::PREF_SIGNATURE => $forumUser->setSignature((string)$value),
			default => null,
		};

		$forumUser->setUpdatedAt(time());
		$this->forumUserMapper->update($forumUser);
	}
}
