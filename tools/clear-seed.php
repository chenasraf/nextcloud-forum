<?php

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

// Removes everything tools/seed.php created and restores the forum's default seed.
// Appended after bootstrap.php; run it through tools/clear-seed.

use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Migration\SeedHelper;
use OCA\Forum\Service\ModerationService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Migration\IOutput;

$db = \OCP\Server::get(IDBConnection::class);
$threadMapper = \OCP\Server::get(ThreadMapper::class);
$postMapper = \OCP\Server::get(PostMapper::class);
$moderation = \OCP\Server::get(ModerationService::class);
$userManager = \OCP\Server::get(IUserManager::class);

$seedUserLike = $db->escapeLikeParameter(FORUM_SEED_USER_PREFIX) . '%';

/**
 * @return list<int>
 */
$findIds = function (string $table, string $column, ?callable $extra = null) use ($db, $seedUserLike): array {
	$qb = $db->getQueryBuilder();
	$qb->select('id')
		->from($table)
		->where($qb->expr()->like($column, $qb->createNamedParameter($seedUserLike)));
	if ($extra !== null) {
		$extra($qb);
	}
	$result = $qb->executeQuery();
	$ids = array_map('intval', $result->fetchAll(\PDO::FETCH_COLUMN));
	$result->closeCursor();
	return $ids;
};

// Seeded threads, with everything attached to them. Permanent deletion only applies to deleted threads,
// so threads are moved to the trash first.
$threadIds = $findIds('forum_threads', 'author_id');
foreach ($threadIds as $threadId) {
	$thread = $threadMapper->findIncludingDeleted($threadId);
	if ($thread->getDeletedAt() === null) {
		$thread->setDeletedAt(time());
		$threadMapper->update($thread);
	}
	$moderation->permanentlyDeleteThread($threadId);
}
seed_log(sprintf('Deleted %d seeded threads', count($threadIds)));

// Replies the seed accounts wrote in other threads, for example while someone tested with them
$replyIds = $findIds('forum_posts', 'author_id', function (IQueryBuilder $qb): void {
	$qb->andWhere($qb->expr()->eq('is_first_post', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));
});
foreach ($replyIds as $postId) {
	$post = $postMapper->findIncludingDeleted($postId);
	if ($post->getDeletedAt() === null) {
		$post->setDeletedAt(time());
		$postMapper->update($post);
	}
	$moderation->permanentlyDeleteReply($postId, ModerationService::REPLIES_KEEP);
}
if (!empty($replyIds)) {
	seed_log(sprintf('Deleted %d replies by seed accounts in other threads', count($replyIds)));
}

// What the seed accounts left elsewhere
foreach (['forum_reactions', 'forum_bookmarks', 'forum_read_markers', 'forum_thread_subs', 'forum_drafts', 'forum_templates', 'forum_user_roles', 'forum_users'] as $table) {
	if (!$db->tableExists($table)) {
		continue;
	}
	$qb = $db->getQueryBuilder();
	$qb->delete($table)
		->where($qb->expr()->like('user_id', $qb->createNamedParameter($seedUserLike)))
		->executeStatement();
}

$deletedUsers = 0;
foreach (seed_user_ids() as $uid) {
	$user = $userManager->get($uid);
	if ($user !== null && $user->delete()) {
		$deletedUsers++;
	}
}
// Deleting an account marks its forum user as deleted instead of removing it
$qb = $db->getQueryBuilder();
$qb->delete('forum_users')
	->where($qb->expr()->like('user_id', $qb->createNamedParameter($seedUserLike)))
	->executeStatement();
seed_log(sprintf('Deleted %d seed accounts', $deletedUsers));

seed_log('Restoring the default forum seed …');
SeedHelper::seedAll(new class implements IOutput {
	public function debug(string $message): void {
	}

	public function info($message): void {
		seed_log((string)$message);
	}

	public function warning($message): void {
		seed_log((string)$message);
	}

	public function startProgress($max = 0): void {
	}

	public function advance($step = 1, $description = ''): void {
	}

	public function finishProgress(): void {
	}
}, true);

seed_rebuild_stats();
seed_log('Done.');
