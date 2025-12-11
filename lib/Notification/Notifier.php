<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Notification;

use OCA\Forum\AppInfo\Application;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public function __construct(
		private IFactory $l10nFactory,
	) {
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 */
	public function getID(): string {
		return Application::APP_ID;
	}

	/**
	 * Human-readable name describing the notifier
	 */
	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Forum');
	}

	/**
	 * Prepare the notification for display
	 *
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by this app or is not of the expected type
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}

		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);

		switch ($notification->getSubject()) {
			case 'new_posts':
				$parameters = $notification->getSubjectParameters();
				$threadId = $parameters['threadId'] ?? 0;
				$threadTitle = $parameters['threadTitle'] ?? 'Unknown Thread';
				$postCount = $parameters['postCount'] ?? 1;

				// Set the rich subject with thread title
				$notification->setRichSubject(
					$l->n(
						'{count} new reply in {thread}',
						'{count} new replies in {thread}',
						$postCount
					),
					[
						'thread' => [
							'type' => 'highlight',
							'id' => (string)$threadId,
							'name' => $threadTitle,
						],
						'count' => [
							'type' => 'highlight',
							'id' => (string)$postCount,
							'name' => (string)$postCount,
						],
					]
				);

				// Set the parsed subject from rich subject
				$this->setParsedSubjectFromRichSubject($notification);

				return $notification;

			case 'mention':
				$parameters = $notification->getSubjectParameters();
				$threadId = $parameters['threadId'] ?? 0;
				$threadTitle = $parameters['threadTitle'] ?? 'Unknown Thread';
				$authorDisplayName = $parameters['authorDisplayName'] ?? 'Someone';

				// Set the rich subject: "{user} mentioned you in {thread}"
				$notification->setRichSubject(
					$l->t('{user} mentioned you in {thread}'),
					[
						'user' => [
							'type' => 'user',
							'id' => $parameters['authorId'] ?? '',
							'name' => $authorDisplayName,
						],
						'thread' => [
							'type' => 'highlight',
							'id' => (string)$threadId,
							'name' => $threadTitle,
						],
					]
				);

				// Set the parsed subject from rich subject
				$this->setParsedSubjectFromRichSubject($notification);

				return $notification;

			default:
				throw new UnknownNotificationException();
		}
	}

	/**
	 * Helper function to set the parsed subject from the rich subject
	 * This extracts the parameter names from rich subject placeholders
	 *
	 * @param INotification $notification
	 */
	protected function setParsedSubjectFromRichSubject(INotification $notification): void {
		$placeholders = $replacements = [];
		$richParams = $notification->getRichSubjectParameters();
		$richSubject = $notification->getRichSubject();

		foreach ($richParams as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if (isset($parameter['type']) && $parameter['type'] === 'file') {
				$replacements[] = $parameter['path'] ?? $parameter['name'] ?? '';
			} else {
				$replacements[] = $parameter['name'] ?? '';
			}
		}

		$parsedSubject = str_replace($placeholders, $replacements, $richSubject);
		$notification->setParsedSubject($parsedSubject);
	}
}
