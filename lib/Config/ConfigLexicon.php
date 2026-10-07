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
			new Entry('public_edit_history', ValueType::BOOL, true, 'Whether all users can view edit history of posts', lazy: true),
			new Entry('allow_edit_history_user_override', ValueType::BOOL, false, 'Whether users can hide their own edit history from others', lazy: true),
			new Entry('enable_signatures', ValueType::BOOL, true, 'Whether signatures are displayed on posts', lazy: true),
			new Entry('count_subcategory_in_category_counts', ValueType::BOOL, true, 'Whether category counts include threads/replies from subcategories', lazy: true),
			new Entry('max_reply_depth', ValueType::INT, 5, 'How many levels replies can nest below a top-level reply (0 disables nested replies)', lazy: true),
		];
	}

	public function getUserConfigs(): array {
		return [
			new Entry('auto_subscribe_created_threads', ValueType::BOOL, true, 'Automatically subscribe to threads the user creates'),
			new Entry('auto_subscribe_replied_threads', ValueType::BOOL, false, 'Automatically subscribe to threads the user replies to'),
			new Entry('upload_directory', ValueType::STRING, 'Forum', 'Directory in user storage for forum file uploads'),
			new Entry('upload_directory_folder_id', ValueType::INT, null, 'Nextcloud file ID of the user upload directory (authoritative)'),
			new Entry('use_category_upload_path', ValueType::BOOL, true, 'Whether to honour category-specific attachment upload paths'),
			new Entry('upload_behavior', ValueType::STRING, 'configured', 'Upload routing behavior: configured or prompt'),
			new Entry('hide_edit_history', ValueType::BOOL, false, 'Whether to hide edit history from other users'),
			new Entry('notify_post_replies', ValueType::BOOL, true, 'Notify the user when someone replies directly to one of their posts'),
		];
	}
}
