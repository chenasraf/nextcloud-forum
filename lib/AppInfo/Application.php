<?php

declare(strict_types=1);

namespace OCA\Forum\AppInfo;

use OCA\Forum\Dashboard\RecentActivityWidget;
use OCA\Forum\Dashboard\TopActivityWidget;
use OCA\Forum\Dashboard\TopCategoriesWidget;
use OCA\Forum\Dashboard\TopThreadsWidget;
use OCA\Forum\Listener\UserEventListener;
use OCA\Forum\Middleware\PermissionMiddleware;
use OCA\Forum\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\UserChangedEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'forum';
	public const DIST_DIR = '../dist';
	public const JS_DIR = self::DIST_DIR . '/js';
	public const CSS_DIR = self::DIST_DIR . '/css';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		// Register middleware for permission checks
		$context->registerMiddleware(PermissionMiddleware::class);

		// Register user event listeners for syncing forum users with Nextcloud users
		$context->registerEventListener(UserCreatedEvent::class, UserEventListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserEventListener::class);
		$context->registerEventListener(UserChangedEvent::class, UserEventListener::class);

		// Register notification notifier
		$context->registerNotifierService(Notifier::class);

		// Register dashboard widgets
		$context->registerDashboardWidget(RecentActivityWidget::class);
		$context->registerDashboardWidget(TopActivityWidget::class);
		$context->registerDashboardWidget(TopCategoriesWidget::class);
		$context->registerDashboardWidget(TopThreadsWidget::class);
	}

	public function boot(IBootContext $context): void {
	}

	/**
	 * Helper to parse Vite Manifest
	 */
	public static function getViteEntryScript(string $entryName): string {
		$jsDir = realpath(__DIR__ . '/../' . Application::JS_DIR);
		$manifestPath = dirname($jsDir) . '/.vite/manifest.json';

		if (!file_exists($manifestPath)) {
			return '';
		}

		$manifest = json_decode(file_get_contents($manifestPath), true);

		if (isset($manifest[$entryName]['file'])) {
			$manifestFile = $manifest[$entryName]['file'];
			$fullPath = dirname($jsDir) . '/' . $manifestFile;

			if (!file_exists($fullPath)) {
				return '';
			}

			return pathinfo($manifestFile, PATHINFO_FILENAME);
		}

		return '';
	}

	public static function tableName(string $name): string {
		// return self::APP_ID . '_' . $name;
		return $name;
	}
}
