<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Dashboard;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Service\UserService;
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
		private UserService $userService,
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

		// Collect all author IDs and resolve display names in batch
		$authorIds = [];
		foreach ($activity as $entry) {
			if ($entry['type'] === 'thread' && $entry['thread'] !== null) {
				$authorIds[] = $entry['thread']->getAuthorId();
			} elseif ($entry['type'] === 'reply') {
				$authorIds[] = $entry['item']->getAuthorId();
			}
		}
		$authorIds = array_unique($authorIds);
		$enrichedAuthors = !empty($authorIds) ? $this->userService->enrichMultipleUsers($authorIds) : [];

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
				$authorId = $thread->getAuthorId();
				$authorData = $enrichedAuthors[$authorId] ?? null;
				$displayName = $authorData['displayName'] ?? $authorId;
				if (!empty($authorData['isGuest'])) {
					$displayName = $this->l->t('%1$s (Guest)', [$displayName]);
				}
				$subtitle = $this->l->t('New thread by %1$s', [$displayName]);
			} else {
				$post = $entry['item'];
				$authorId = $post->getAuthorId();
				$authorData = $enrichedAuthors[$authorId] ?? null;
				$displayName = $authorData['displayName'] ?? $authorId;
				if (!empty($authorData['isGuest'])) {
					$displayName = $this->l->t('%1$s (Guest)', [$displayName]);
				}
				$subtitle = $this->l->t('Reply by %1$s', [$displayName]);
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
