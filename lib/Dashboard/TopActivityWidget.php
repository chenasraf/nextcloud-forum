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

class TopActivityWidget implements IAPIWidgetV2, IIconWidget, IButtonWidget {
	public function __construct(
		private IL10N $l,
		private IURLGenerator $urlGenerator,
		private WidgetService $widgetService,
	) {
	}

	public function getId(): string {
		return 'forum-top-activity';
	}

	public function getTitle(): string {
		return $this->l->t('Top Forum activity');
	}

	public function getOrder(): int {
		return 13;
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
		$items = [];

		// Get top categories (half of limit, rounded up)
		$categoryLimit = (int)ceil($limit / 2);
		$categories = $this->widgetService->getTopCategories($userId, $categoryLimit);

		foreach ($categories as $category) {
			$threadCount = $category->getThreadCount();

			$items[] = new WidgetItem(
				$category->getName(),
				$this->l->n('%n thread', '%n threads', $threadCount),
				$this->widgetService->getCategoryUrl($category),
				$this->widgetService->getCategoryIconUrl(),
				'cat-' . $category->getId()
			);
		}

		// Get top threads (remaining slots)
		$threadLimit = $limit - count($items);
		if ($threadLimit > 0) {
			$threads = $this->widgetService->getTopThreads($userId, $threadLimit);

			foreach ($threads as $thread) {
				$viewCount = $thread->getViewCount();

				$items[] = new WidgetItem(
					$thread->getTitle(),
					$this->l->n('%n view', '%n views', $viewCount),
					$this->widgetService->getThreadUrl($thread),
					$this->widgetService->getThreadIconUrl(),
					'thread-' . $thread->getId()
				);
			}
		}

		return new WidgetItems(
			$items,
			$this->l->t('No forum activity')
		);
	}
}
