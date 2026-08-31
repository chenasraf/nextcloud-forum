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

	// Nextcloud loads every installed app's `vendor/autoload.php`, and Composer
	// registers each loader with prepend=true, so the last one loaded wins. An app
	// that vendors a different PHPUnit major then shadows the copy this run started
	// from, and PHPUnit dies building the suite on a class/method mismatch. Find the
	// loader that owns the running PHPUnit and re-prepend it so it keeps winning.
	if (class_exists(\Composer\Autoload\ClassLoader::class, false)) {
		$runningPhpunit = realpath((new \ReflectionClass(\PHPUnit\TextUI\Application::class))->getFileName());
		foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader) {
			$candidate = $loader->findFile(\PHPUnit\TextUI\Application::class);
			if ($candidate !== false && realpath($candidate) === $runningPhpunit) {
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
