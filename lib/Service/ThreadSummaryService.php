<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Db\BBCodeMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\Thread;
use OCP\TaskProcessing\IManager;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Summarizes threads through the Nextcloud Task Processing API, so whichever
 * summary provider the administration configured does the work.
 */
class ThreadSummaryService {
	/** Upper bound for the text sent to the provider; the oldest replies are dropped beyond it */
	public const MAX_INPUT_LENGTH = 50000;

	/** How many of the newest replies are considered for a summary */
	private const MAX_REPLIES = 500;

	private const CUSTOM_ID_PREFIX = 'thread-summary:';

	public function __construct(
		private ContainerInterface $container,
		private AdminSettingsService $adminSettingsService,
		private PostMapper $postMapper,
		private BBCodeMapper $bbCodeMapper,
		private BBCodeService $bbCodeService,
		private UserService $userService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Whether summaries are enabled in the forum settings and a summary provider is available
	 */
	public function isAvailable(?string $userId = null): bool {
		if ($this->adminSettingsService->getSetting(AdminSettingsService::SETTING_ENABLE_THREAD_SUMMARIES) !== true) {
			return false;
		}
		return $this->isProviderAvailable($userId);
	}

	/**
	 * Whether the server has a provider for text summaries, regardless of the forum settings
	 */
	public function isProviderAvailable(?string $userId = null): bool {
		$manager = $this->getManager();
		if ($manager === null) {
			return false;
		}
		try {
			return array_key_exists(TextToTextSummary::ID, $manager->getAvailableTaskTypes(false, $userId));
		} catch (\Throwable $e) {
			$this->logger->warning('Could not list task processing types: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Schedule a summary of a thread for a user, reusing an earlier task for identical content
	 *
	 * @param string $userId The requesting user, the only one who can read the result
	 * @param Thread $thread The thread to summarize
	 * @param int $afterPostId Summarize only replies newer than this post (0 for the whole thread)
	 * @return Task|null The scheduled or reused task, or null when there is nothing to summarize
	 * @throws \RuntimeException When summaries are unavailable
	 */
	public function schedule(string $userId, Thread $thread, int $afterPostId = 0): ?Task {
		$manager = $this->getManager();
		if ($manager === null || !$this->isAvailable($userId)) {
			throw new \RuntimeException('Thread summaries are not available');
		}

		$text = $this->buildInput($thread, $afterPostId);
		if ($text === null) {
			return null;
		}

		// Keyed by the content so unchanged threads reuse the earlier summary,
		// while new replies or edits produce a fresh one
		$customId = self::CUSTOM_ID_PREFIX . $thread->getId() . ':' . md5($text);
		foreach (array_reverse($manager->getUserTasksByApp($userId, Application::APP_ID, $customId)) as $existing) {
			if (!in_array($existing->getStatus(), [Task::STATUS_FAILED, Task::STATUS_CANCELLED], true)) {
				return $existing;
			}
		}

		$task = new Task(TextToTextSummary::ID, ['input' => $text], Application::APP_ID, $userId, $customId);
		$manager->scheduleTask($task);
		return $task;
	}

	/**
	 * Get a summary task of a thread that belongs to the user
	 *
	 * @throws \OCP\TaskProcessing\Exception\NotFoundException When the task does not exist, belongs to someone else or to another thread
	 * @throws \RuntimeException When the Task Processing API is unavailable
	 */
	public function getTask(string $userId, int $threadId, int $taskId): Task {
		$manager = $this->getManager();
		if ($manager === null) {
			throw new \RuntimeException('Thread summaries are not available');
		}

		$task = $manager->getUserTask($taskId, $userId);
		if ($task->getAppId() !== Application::APP_ID
			|| !str_starts_with((string)$task->getCustomId(), self::CUSTOM_ID_PREFIX . $threadId . ':')) {
			throw new \OCP\TaskProcessing\Exception\NotFoundException('Task not found');
		}
		return $task;
	}

	/**
	 * Serialize a summary task for the API
	 *
	 * @return array{taskId: int|null, status: string, summary: string|null}
	 */
	public function serializeTask(Task $task): array {
		$status = match ($task->getStatus()) {
			Task::STATUS_SUCCESSFUL => 'successful',
			Task::STATUS_FAILED, Task::STATUS_CANCELLED => 'failed',
			Task::STATUS_RUNNING => 'running',
			default => 'scheduled',
		};
		$output = $task->getOutput()['output'] ?? null;
		$summary = $status === 'successful' && is_string($output) ? trim($output) : null;

		return [
			'taskId' => $task->getId(),
			'status' => $status,
			'summary' => $summary,
		];
	}

	/**
	 * Build the plain text of a thread that is sent to the summary provider
	 *
	 * The text always starts with the opening post, so a summary of unread
	 * replies (those newer than $afterPostId) knows what the thread is about.
	 * When the text grows past MAX_INPUT_LENGTH the oldest replies are left out.
	 *
	 * @return string|null The text, or null when there are no posts to summarize
	 */
	public function buildInput(Thread $thread, int $afterPostId = 0): ?string {
		$replies = $this->postMapper->findNewestReplies($thread->getId(), $afterPostId, self::MAX_REPLIES);
		if ($afterPostId > 0 && count($replies) === 0) {
			return null;
		}
		$firstPost = $this->postMapper->findFirstPostByThreadId($thread->getId());
		if ($firstPost !== null && $firstPost->getDeletedAt() !== null) {
			$firstPost = null;
		}
		if ($firstPost === null && count($replies) === 0) {
			return null;
		}

		$posts = $firstPost !== null ? [$firstPost, ...$replies] : $replies;
		$authors = $this->userService->enrichMultipleUsers(array_values(array_unique(array_map(fn (Post $p) => $p->getAuthorId(), $posts))));
		$bbcodes = $this->bbCodeMapper->findAllEnabled();

		$header = '# ' . $thread->getTitle() . "\n\n";
		$length = mb_strlen($header);

		$opening = null;
		if ($firstPost !== null) {
			$opening = $this->formatPost($firstPost, $authors, $bbcodes);
			$length += mb_strlen($opening);
		}

		// Replies arrive newest first, so the oldest ones are the ones that do not fit
		$included = [];
		foreach ($replies as $reply) {
			$formatted = $this->formatPost($reply, $authors, $bbcodes);
			if ($length + mb_strlen($formatted) > self::MAX_INPUT_LENGTH && ($opening !== null || count($included) > 0)) {
				break;
			}
			$included[] = $formatted;
			$length += mb_strlen($formatted);
		}

		$sections = array_reverse($included);
		if ($opening !== null) {
			array_unshift($sections, $opening);
		}
		return mb_substr($header . implode('', $sections), 0, self::MAX_INPUT_LENGTH);
	}

	/**
	 * @param array<string, array<string, mixed>> $authors
	 * @param array<\OCA\Forum\Db\BBCode> $bbcodes
	 */
	private function formatPost(Post $post, array $authors, array $bbcodes): string {
		$author = (string)($authors[$post->getAuthorId()]['displayName'] ?? $post->getAuthorId());
		$date = gmdate('Y-m-d H:i', $post->getCreatedAt());
		$html = $this->bbCodeService->parse($post->getContent(), $bbcodes, $post->getAuthorId(), $post->getId());
		return $author . ' (' . $date . "):\n" . $this->htmlToText($html) . "\n\n";
	}

	private function htmlToText(string $html): string {
		$text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
		$text = preg_replace('/<\/(p|div|li|blockquote|pre|h[1-6]|tr)>/i', "$0\n", $text) ?? $text;
		$text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
		$text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
		return trim($text);
	}

	/**
	 * The Task Processing manager, resolved lazily since servers older than
	 * Nextcloud 30 do not have it
	 */
	private function getManager(): ?IManager {
		if (!interface_exists(IManager::class)) {
			return null;
		}
		try {
			/** @var IManager */
			return $this->container->get(IManager::class);
		} catch (\Throwable $e) {
			$this->logger->warning('Task processing manager is unavailable: ' . $e->getMessage());
			return null;
		}
	}
}
