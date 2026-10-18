<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

// Boots Nextcloud the way occ does. lib.sh prepends this file to every tools/ PHP script.

$nextcloudRoot = getenv('NEXTCLOUD_ROOT') ?: '';
if ($nextcloudRoot === '' || !is_file($nextcloudRoot . '/lib/base.php')) {
	fwrite(STDERR, "NEXTCLOUD_ROOT does not point to a Nextcloud installation: $nextcloudRoot\n");
	exit(1);
}

define('OC_CONSOLE', 1);
require_once $nextcloudRoot . '/lib/base.php';

if (\OCP\Server::get(\OCP\IConfig::class)->getSystemValueBool('maintenance', false)) {
	fwrite(STDERR, "Nextcloud is in maintenance mode.\n");
	exit(1);
}

$appManager = \OCP\Server::get(\OCP\App\IAppManager::class);
if (!$appManager->isEnabledForAnyone('forum')) {
	fwrite(STDERR, "The forum app is not enabled on this instance.\n");
	exit(1);
}
$appManager->loadApps();

/** Prefix of the user IDs created by the seed; also how seeded threads are recognized */
const FORUM_SEED_USER_PREFIX = 'seed-';

function seed_log(string $message): void {
	echo $message . PHP_EOL;
}

/**
 * Rebuild every user, category and thread counter after rows were inserted or removed directly
 */
function seed_rebuild_stats(): void {
	$stats = \OCP\Server::get(\OCA\Forum\Service\StatsService::class);
	seed_log('Rebuilding forum statistics …');
	$stats->rebuildAllThreadStats();
	$stats->rebuildAllCategoryStats();
	$stats->rebuildAllUserStats();
}

/**
 * @return list<string> IDs of the accounts created by the seed
 */
function seed_user_ids(): array {
	$ids = [];
	foreach (\OCP\Server::get(\OCP\IUserManager::class)->search(FORUM_SEED_USER_PREFIX) as $user) {
		if (str_starts_with($user->getUID(), FORUM_SEED_USER_PREFIX)) {
			$ids[] = $user->getUID();
		}
	}
	return $ids;
}
