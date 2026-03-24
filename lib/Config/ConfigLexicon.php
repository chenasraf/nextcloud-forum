<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Config;

use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\ILexicon;
use OCP\Config\Lexicon\Strictness;
use OCP\Config\ValueType;

class ConfigLexicon implements ILexicon {
	public function getStrictness(): Strictness {
		return Strictness::WARNING;
	}

	public function getAppConfigs(): array {
		return [
			new Entry('title', ValueType::STRING, 'Forum', 'Forum title displayed at the top of the page', lazy: true),
			new Entry('subtitle', ValueType::STRING, 'Welcome to the forum!', 'Forum subtitle displayed below the title', lazy: true),
			new Entry('allow_guest_access', ValueType::BOOL, false, 'Whether unauthenticated users can access the forum', lazy: true),
			new Entry('is_initialized', ValueType::BOOL, false, 'Whether the forum has been initialized with seed data', lazy: true),
		];
	}

	public function getUserConfigs(): array {
		return [
			new Entry('auto_subscribe_created_threads', ValueType::BOOL, true, 'Automatically subscribe to threads the user creates'),
			new Entry('auto_subscribe_replied_threads', ValueType::BOOL, false, 'Automatically subscribe to threads the user replies to'),
			new Entry('upload_directory', ValueType::STRING, 'Forum', 'Directory in user storage for forum file uploads'),
		];
	}
}
