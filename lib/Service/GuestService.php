<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\GuestSession;
use OCA\Forum\Db\GuestSessionMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class GuestService {
	private const ADJECTIVES = [
		'Bright', 'Swift', 'Calm', 'Bold', 'Keen',
		'Wise', 'Fair', 'Warm', 'Cool', 'Pure',
		'Sharp', 'Brave', 'Clear', 'Quick', 'Glad',
		'Kind', 'Free', 'Noble', 'Proud', 'True',
		'Lucky', 'Merry', 'Jolly', 'Lively', 'Gentle',
		'Vivid', 'Quiet', 'Eager', 'Happy', 'Witty',
		'Daring', 'Clever', 'Humble', 'Cosmic', 'Sunny',
		'Golden', 'Silver', 'Crystal', 'Mystic', 'Velvet',
		'Amber', 'Azure', 'Coral', 'Ivory', 'Jade',
		'Ruby', 'Sage', 'Teal', 'Rustic', 'Nimble',
		'Polar', 'Lunar', 'Solar', 'Stellar', 'Radiant',
		'Serene', 'Dapper', 'Frosty', 'Mossy', 'Dusty',
		'Misty', 'Breezy', 'Stormy', 'Snowy', 'Rainy',
		'Zesty', 'Peppy', 'Perky', 'Chipper', 'Plucky',
		'Hardy', 'Sturdy', 'Steady', 'Loyal', 'Fierce',
		'Savvy', 'Crafty', 'Nifty', 'Handy', 'Zippy',
		'Glossy', 'Sleek', 'Crisp', 'Fresh', 'Rosy',
		'Dusky', 'Ashen', 'Oaken', 'Marble', 'Pewter',
		'Copper', 'Bronze', 'Cobalt', 'Scarlet', 'Indigo',
	];

	private const NOUNS = [
		'Mountain', 'River', 'Forest', 'Meadow', 'Ocean',
		'Valley', 'Desert', 'Island', 'Canyon', 'Prairie',
		'Falcon', 'Eagle', 'Raven', 'Phoenix', 'Tiger',
		'Dolphin', 'Panther', 'Wolf', 'Fox', 'Hawk',
		'Storm', 'Thunder', 'Breeze', 'Frost', 'Aurora',
		'Comet', 'Star', 'Nebula', 'Galaxy', 'Cosmos',
		'Oak', 'Cedar', 'Willow', 'Maple', 'Birch',
		'Spark', 'Flame', 'Wave', 'Stone', 'Crystal',
		'Arrow', 'Shield', 'Crown', 'Compass', 'Lantern',
		'Harbor', 'Summit', 'Ridge', 'Cliff', 'Shore',
		'Heron', 'Otter', 'Panda', 'Lynx', 'Crane',
		'Badger', 'Osprey', 'Wren', 'Finch', 'Condor',
		'Glacier', 'Lagoon', 'Tundra', 'Steppe', 'Reef',
		'Plateau', 'Ravine', 'Basin', 'Delta', 'Fjord',
		'Pebble', 'Ember', 'Geyser', 'Prism', 'Quartz',
		'Beacon', 'Anchor', 'Vessel', 'Scepter', 'Banner',
		'Thistle', 'Clover', 'Orchid', 'Lotus', 'Fern',
		'Ivy', 'Aspen', 'Cypress', 'Spruce', 'Sequoia',
		'Dusk', 'Dawn', 'Zenith', 'Horizon', 'Eclipse',
		'Cascade', 'Torrent', 'Zephyr', 'Tempest', 'Monsoon',
	];

	public function __construct(
		private GuestSessionMapper $guestSessionMapper,
	) {
	}

	/**
	 * Resolve a guest token to a guest author ID.
	 * Creates a new guest session if the token does not exist yet.
	 *
	 * @param string $guestToken 32-character hex token
	 * @return string Author ID in the format "guest:<token>"
	 * @throws \InvalidArgumentException If token is invalid
	 */
	public function resolveGuestIdentity(string $guestToken): string {
		// Validate token format: 32 hex characters
		if (!preg_match('/^[0-9a-f]{32}$/', $guestToken)) {
			throw new \InvalidArgumentException('Invalid guest token format');
		}

		try {
			$this->guestSessionMapper->findByToken($guestToken);
		} catch (DoesNotExistException $e) {
			// Create new guest session
			$session = new GuestSession();
			$session->setSessionToken($guestToken);
			$session->setDisplayName($this->generateGuestName());
			$session->setCreatedAt(time());
			$this->guestSessionMapper->insert($session);
		}

		return 'guest:' . $guestToken;
	}

	/**
	 * Get the display name for a guest author ID
	 *
	 * @param string $authorId Author ID in the format "guest:<token>"
	 * @return string|null Display name, or null if not found
	 */
	public function getGuestDisplayName(string $authorId): ?string {
		if (!self::isGuestAuthor($authorId)) {
			return null;
		}

		$token = substr($authorId, 6); // Remove "guest:" prefix
		try {
			$session = $this->guestSessionMapper->findByToken($token);
			return $session->getDisplayName();
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * Get guest session by token, creating one if it does not exist
	 *
	 * @param string $guestToken 32-character hex token
	 * @return GuestSession
	 * @throws \InvalidArgumentException If token is invalid
	 */
	public function getOrCreateSession(string $guestToken): GuestSession {
		if (!preg_match('/^[0-9a-f]{32}$/', $guestToken)) {
			throw new \InvalidArgumentException('Invalid guest token format');
		}

		try {
			return $this->guestSessionMapper->findByToken($guestToken);
		} catch (DoesNotExistException $e) {
			$session = new GuestSession();
			$session->setSessionToken($guestToken);
			$session->setDisplayName($this->generateGuestName());
			$session->setCreatedAt(time());
			/** @var GuestSession */
			return $this->guestSessionMapper->insert($session);
		}
	}

	/**
	 * Check if an author ID represents a guest user
	 */
	public static function isGuestAuthor(string $authorId): bool {
		return str_starts_with($authorId, 'guest:');
	}

	/**
	 * Generate a unique guest display name
	 * Format: AdjectiveNounNN (e.g., "BrightMountain42")
	 */
	private function generateGuestName(): string {
		$maxAttempts = 20;
		for ($i = 0; $i < $maxAttempts; $i++) {
			$adjective = self::ADJECTIVES[array_rand(self::ADJECTIVES)];
			$noun = self::NOUNS[array_rand(self::NOUNS)];
			$number = str_pad((string)random_int(0, 99), 2, '0', STR_PAD_LEFT);
			$name = $adjective . $noun . $number;

			if (!$this->guestSessionMapper->displayNameExists($name)) {
				return $name;
			}
		}

		// Fallback: use timestamp-based name
		return 'Guest' . dechex(time()) . str_pad((string)random_int(0, 99), 2, '0', STR_PAD_LEFT);
	}
}
