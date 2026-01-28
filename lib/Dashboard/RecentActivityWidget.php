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

class RecentActivityWidget implements IAPIWidgetV2, IIconWidget, IButtonWidget {
	public function __construct(
		private IL10N $l,
		private IURLGenerator $urlGenerator,
		private WidgetService $widgetService,
	) {
	}

	public function getId(): string {
		return 'forum-recent-activity';
	}

	public function getTitle(): string {
		return $this->l->t('Recent Forum activity');
	}

	public function getOrder(): int {
		return 10;
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
				$this->l->t('More activity')
			),
		];
	}

	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$activity = $this->widgetService->getRecentActivity($userId, $limit);

		$items = [];
		foreach ($activity as $entry) {
			$thread = $entry['thread'];
			if ($thread === null) {
				continue;
			}

			$title = $thread->getTitle();
			$link = $this->widgetService->getThreadUrl($thread);
			$sinceId = (string)$entry['createdAt'];

			if ($entry['type'] === 'thread') {
				$subtitle = $this->l->t('New thread by %1$s', [$thread->getAuthorId()]);
			} else {
				$post = $entry['item'];
				$subtitle = $this->l->t('Reply by %1$s', [$post->getAuthorId()]);
			}

			$items[] = new WidgetItem(
				$title,
				$subtitle,
				$link,
				$this->widgetService->getThreadIconUrl(),
				$sinceId
			);
		}

		return new WidgetItems(
			$items,
			$this->l->t('No recent forum activity')
		);
	}
}
