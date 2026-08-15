<?php

declare(strict_types=1);

// Detect Nextcloud bootstrap location
// Priority: 1) NEXTCLOUD_ROOT env var, 2) Docker location, 3) Standard location
$nextcloudBootstrap = null;

// Check both $_ENV and getenv() as they can differ depending on PHP configuration
$nextcloudRoot = $_ENV['NEXTCLOUD_ROOT'] ?? getenv('NEXTCLOUD_ROOT');

if (!empty($nextcloudRoot)) {
	// Use NEXTCLOUD_ROOT environment variable (set by Makefile for local testing)
	$nextcloudBootstrap = $nextcloudRoot . '/tests/bootstrap.php';
} elseif (file_exists(__DIR__ . '/../../../tests/bootstrap.php')) {
	// Standard location (Docker/installed in Nextcloud apps directory)
	$nextcloudBootstrap = __DIR__ . '/../../../tests/bootstrap.php';
}

if ($nextcloudBootstrap && file_exists($nextcloudBootstrap)) {
	// Running with full Nextcloud environment
	// Define OC_CONSOLE to bypass installation check during tests
	if (!defined('OC_CONSOLE')) {
		define('OC_CONSOLE', 1);
	}
	require_once $nextcloudBootstrap;
	require_once __DIR__ . '/../vendor/autoload.php';
	\OC_App::loadApp(OCA\Forum\AppInfo\Application::APP_ID);
	OC_Hook::clear();

	// When app repos share a parent directory (see NC_TEST_APPS_PATH), Nextcloud
	// loads every sibling app's `vendor/autoload.php` too. Composer registers each
	// loader with prepend=true, so the last one loaded wins — a sibling app pinned
	// to a different PHPUnit major then shadows ours and PHPUnit dies building the
	// suite with a class/method mismatch (e.g. TestDirectory::groups()). Find our
	// own Composer loader (the one that resolves PHPUnit into this app's vendor)
	// and re-prepend it so this app's vendored PHPUnit wins.
	if (class_exists(\Composer\Autoload\ClassLoader::class, false)) {
		$forumVendor = realpath(__DIR__ . '/../vendor');
		foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader) {
			$phpunitFile = $loader->findFile(\PHPUnit\TextUI\Application::class);
			if ($phpunitFile !== false && str_starts_with((string)realpath($phpunitFile), (string)$forumVendor)) {
				$loader->unregister();
				$loader->register(true);
				break;
			}
		}
	}
} else {
	// Cannot find Nextcloud bootstrap
	echo "\n\033[31mError: Nextcloud bootstrap not found.\033[0m\n";
	echo "For local testing, set NEXTCLOUD_ROOT environment variable:\n";
	echo "  NEXTCLOUD_ROOT=~/Dev/nextcloud-docker-dev/workspace/server make test\n";
	echo "\nOr run tests in Docker:\n";
	echo "  make test-docker\n\n";
	exit(1);
}
