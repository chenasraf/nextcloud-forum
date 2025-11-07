<?php

declare(strict_types=1);

namespace OCA\Forum\AppInfo;

use OCA\Forum\Listener\UserEventListener;
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
		// Register user event listeners for syncing forum users with Nextcloud users
		$context->registerEventListener(UserCreatedEvent::class, UserEventListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserEventListener::class);
		$context->registerEventListener(UserChangedEvent::class, UserEventListener::class);
	}

	public function boot(IBootContext $context): void {
	}

	public static function tableName(string $name): string {
		// return self::APP_ID . '_' . $name;
		return $name;
	}
}
