<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Dashboard;

use OCA\Forum\AppInfo\Application;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;

class TopThreadsWidget implements IAPIWidgetV2, IIconWidget, IButtonWidget {
	public function __construct(
		private IL10N $l,
		private IURLGenerator $urlGenerator,
		private WidgetService $widgetService,
	) {
	}

	public function getId(): string {
		return 'forum-top-threads';
	}

	public function getTitle(): string {
		return $this->l->t('Top Forum threads');
	}

	public function getOrder(): int {
		return 12;
	}

	public function getIconClass(): string {
		return 'icon-forum';
	}

	public function getIconUrl(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');
	}

	public function getUrl(): ?string {
		return $this->widgetService->getForumUrl();
	}

	public function load(): void {
		\OCP\Util::addStyle(Application::APP_ID, 'dashboard');
	}

	public function getWidgetButtons(string $userId): array {
		return [
			new WidgetButton(
				WidgetButton::TYPE_MORE,
				$this->widgetService->getForumUrl(),
				$this->l->t('Browse forum')
			),
		];
	}

	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$threads = $this->widgetService->getTopThreads($userId, $limit);

		$items = [];
		foreach ($threads as $thread) {
			$viewCount = $thread->getViewCount();

			$items[] = new WidgetItem(
				$thread->getTitle(),
				$this->l->n('%n view', '%n views', $viewCount),
				$this->widgetService->getThreadUrl($thread),
				$this->widgetService->getThreadIconUrl(),
				(string)$thread->getId()
			);
		}

		return new WidgetItems(
			$items,
			$this->l->t('No threads available')
		);
	}
}
