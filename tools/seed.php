<?php

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

// Seeds every category with threads and replies written by a pool of seed accounts.
// Appended after bootstrap.php and seed-content.php; run it through tools/seed.

use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCP\IDBConnection;
use OCP\IUserManager;

/** Deepest nesting the seed creates; stays below the forum's default limit */
const SEED_MAX_DEPTH = 3;

$threadsPerCategory = max(1, (int)(getenv('SEED_THREADS') ?: 10));
$repliesPerThread = max(0, (int)(getenv('SEED_REPLIES') ?: 20));
$password = getenv('SEED_PASSWORD') ?: null;
if (getenv('SEED_RANDOM') !== false && getenv('SEED_RANDOM') !== '') {
	mt_srand((int)getenv('SEED_RANDOM'));
}

$userManager = \OCP\Server::get(IUserManager::class);
$categoryMapper = \OCP\Server::get(CategoryMapper::class);
$threadMapper = \OCP\Server::get(ThreadMapper::class);
$postMapper = \OCP\Server::get(PostMapper::class);
$db = \OCP\Server::get(IDBConnection::class);

$existingSeedUsers = seed_user_ids();
if (!empty($existingSeedUsers) && getenv('SEED_APPEND') !== '1') {
	$qb = $db->getQueryBuilder();
	$qb->select($qb->func()->count('id', 'count'))
		->from('forum_threads')
		->where($qb->expr()->like('author_id', $qb->createNamedParameter($db->escapeLikeParameter(FORUM_SEED_USER_PREFIX) . '%')));
	$result = $qb->executeQuery();
	$seededThreads = (int)$result->fetchOne();
	$result->closeCursor();
	if ($seededThreads > 0) {
		fwrite(STDERR, "The forum already has $seededThreads seeded threads. Run tools/clear-seed first, or pass --append to add more.\n");
		exit(1);
	}
}

// Seed accounts
$people = [];
$created = 0;
foreach (seed_people() as $person) {
	$uid = FORUM_SEED_USER_PREFIX . $person['id'];
	$user = $userManager->get($uid);
	if ($user === null) {
		$user = $userManager->createUser($uid, $password ?? ('Seed-' . bin2hex(random_bytes(12)) . '!aA1'));
		if ($user === false) {
			fwrite(STDERR, "Could not create the account $uid.\n");
			exit(1);
		}
		$user->setDisplayName($person['name']);
		$created++;
	} elseif ($password !== null) {
		$user->setPassword($password);
	}
	$people[] = ['id' => $uid, 'firstName' => explode(' ', $person['name'])[0]];
}
seed_log(sprintf('Seed accounts: %d created, %d already there', $created, count($people) - $created));

$categories = $categoryMapper->findAll();
if (empty($categories)) {
	fwrite(STDERR, "The forum has no categories. Run tools/clear-seed to restore the default ones.\n");
	exit(1);
}

$topicsByTheme = [];
foreach (seed_topics() as $topic) {
	$topicsByTheme[$topic['theme']][] = $topic;
}
$usedTitles = [];

/**
 * Pick the next topic for a category: unused topics of its theme first, then unused ones of any theme,
 * and only then topics that were already used elsewhere.
 */
$pickTopic = function (string $theme) use ($topicsByTheme, &$usedTitles): array {
	$themed = $topicsByTheme[$theme] ?? [];
	$all = array_merge(...array_values($topicsByTheme));
	foreach ([$themed, $all] as $pool) {
		$unused = array_values(array_filter($pool, fn (array $t) => !isset($usedTitles[$t['title']])));
		if (!empty($unused)) {
			$topic = $unused[mt_rand(0, count($unused) - 1)];
			$usedTitles[$topic['title']] = true;
			return $topic;
		}
	}
	return $all[mt_rand(0, count($all) - 1)];
};

$pickPerson = function (array $exclude) use ($people): array {
	$candidates = array_values(array_filter($people, fn (array $p) => !in_array($p['id'], $exclude, true)));
	return $candidates[mt_rand(0, count($candidates) - 1)];
};

$slugify = function (string $title): string {
	$slug = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
	return substr($slug, 0, 80) . '-' . bin2hex(random_bytes(3));
};

/**
 * Write the replies of one thread as a conversation. The topic's own replies keep their order and are
 * spread through the thread; the author answers some of them a few replies later, usually nested under
 * them. Filler replies fill the gaps: some about the thread as a whole, some answering one of the topic's
 * replies, either nested under it or quoting it.
 *
 * @return list<array{author: string, firstName: string, content: string, parent: int|null, depth: int}>
 */
$planReplies = function (array $topic, array $op, int $count) use ($pickPerson): array {
	$topicReplies = $topic['replies'];
	$nextTopicReply = 0;
	$followUps = [];
	foreach ($topic['op'] as [$answers, $text]) {
		$followUps[$answers][] = $text;
	}
	$fillers = seed_filler_replies($topic['theme']);
	$standalone = [];
	$answerFillers = [];
	$late = seed_late_replies();
	shuffle($late);
	$closers = seed_op_closers();

	// Follow-ups from the author waiting to be posted: [due position, text, position of the reply it answers]
	$pending = [];
	$replies = [];
	$lastTopicReply = null;
	$lastFollowUp = -100;
	$previousAuthor = $op['id'];
	for ($i = 0; $i < $count; $i++) {
		$parent = null;
		$quote = null;
		$substantive = true;
		$remaining = $count - $i;
		$topicLeft = count($topicReplies) - $nextTopicReply;
		$followUpsLeft = count($pending);
		for ($k = $nextTopicReply; $k < count($topicReplies); $k++) {
			$followUpsLeft += count($followUps[$k] ?? []);
		}

		if (!empty($pending) && ($pending[0][0] <= $i || $remaining <= count($pending))) {
			[, $content, $parent] = array_shift($pending);
			$author = $op;
			// Sometimes answered as a separate reply instead of nested under the reply it is about
			if (mt_rand(1, 100) <= 30) {
				$parent = null;
			}
			$lastFollowUp = $i;
		} elseif ($remaining === 1 && empty($pending) && $topicLeft === 0 && $i - $lastFollowUp >= 4 && mt_rand(1, 100) <= 60) {
			$author = $op;
			$content = $closers[mt_rand(0, count($closers) - 1)];
			$substantive = false;
		} else {
			$author = $pickPerson([$op['id'], $previousAuthor]);
			// Topic replies are spread evenly over the positions not needed for follow-ups,
			// and a thread always opens with them
			$free = max(1, $remaining - $followUpsLeft);
			$useTopic = $topicLeft > 0 && ($i < 2 || mt_rand(1, $free) <= $topicLeft);

			$answerable = array_keys(array_filter(
				$replies,
				fn (array $r) => $r['substantive'] && $r['author'] !== $author['id'],
			));
			if ($useTopic) {
				$index = $nextTopicReply++;
				$content = $topicReplies[$index];
				if ($lastTopicReply !== null && mt_rand(1, 100) <= 10) {
					$parent = $lastTopicReply;
				}
				$lastTopicReply = $i;
				$due = $i;
				foreach ($followUps[$index] ?? [] as $text) {
					$due += mt_rand(1, 3);
					$pending[] = [$due, $text, $i];
				}
				usort($pending, fn (array $a, array $b) => $a[0] <=> $b[0]);
			} elseif (!empty($answerable) && mt_rand(1, 100) <= 60) {
				$substantive = false;
				if (empty($answerFillers)) {
					$answerFillers = $fillers['answer'];
					shuffle($answerFillers);
				}
				// People mostly answer something recent
				$target = $answerable[mt_rand(max(0, count($answerable) - 4), count($answerable) - 1)];
				$content = str_replace('{name}', $replies[$target]['firstName'], array_shift($answerFillers));
				if (mt_rand(1, 100) <= 35) {
					$quote = $target;
				} else {
					$parent = $target;
				}
			} elseif ($i >= $count * 0.6 && !empty($late)) {
				$substantive = false;
				$content = array_shift($late);
			} else {
				$substantive = false;
				if (empty($standalone)) {
					$standalone = $fillers['standalone'];
					shuffle($standalone);
				}
				$content = array_shift($standalone);
			}
		}

		$depth = 0;
		if ($parent !== null) {
			$depth = $replies[$parent]['depth'] + 1;
			if ($depth > SEED_MAX_DEPTH) {
				// Too deep to nest, so quote it instead
				$quote = $parent;
				$parent = null;
				$depth = 0;
			}
		}
		if ($quote !== null) {
			$quoted = trim((string)preg_replace('/\[quote\].*?\[\/quote\]/s', '', $replies[$quote]['content']));
			$content = '[quote]' . $quoted . "[/quote]\n" . $content;
		}

		$replies[] = [
			'author' => $author['id'],
			'firstName' => $author['firstName'],
			'content' => $content,
			'parent' => $parent,
			'depth' => $depth,
			'substantive' => $substantive,
		];
		$previousAuthor = $author['id'];
	}
	return $replies;
};

// Plan every thread first, so threads and posts can be inserted in the order they were written
$now = time();
$plannedThreads = [];
foreach ($categories as $category) {
	$theme = seed_theme_for_category($category->getName() . ' ' . $category->getSlug() . ' ' . ($category->getDescription() ?? ''));
	for ($t = 0; $t < $threadsPerCategory; $t++) {
		$topic = $pickTopic($theme);
		$op = $pickPerson([]);
		$startedAt = $now - mt_rand(3600, 60 * 86400);

		$replies = $planReplies($topic, $op, $repliesPerThread);

		// Spread the replies between the start and a point up to now; some threads are still active
		$span = (int)(($now - $startedAt) * mt_rand(30, 100) / 100);
		$weights = [];
		foreach ($replies as $r => $_) {
			// Conversations are busy at first and slow down later
			$weights[] = mt_rand(1, 100) * (1 + $r / 4);
		}
		$total = array_sum($weights) ?: 1;
		$time = $startedAt;
		foreach ($replies as $r => &$reply) {
			$time += max(60, (int)($span * $weights[$r] / $total));
			$reply['createdAt'] = min($time, $now - 30);
		}
		unset($reply);

		$plannedThreads[] = [
			'categoryId' => $category->getId(),
			'author' => $op['id'],
			'title' => $topic['title'],
			'body' => $topic['body'],
			'createdAt' => $startedAt,
			'views' => mt_rand(max(1, $repliesPerThread) * 3, max(1, $repliesPerThread) * 40 + 10),
			'replies' => $replies,
		];
	}
}

usort($plannedThreads, fn (array $a, array $b) => $a['createdAt'] <=> $b['createdAt']);

$db->beginTransaction();
try {
	// Threads, oldest first
	$posts = [];
	foreach ($plannedThreads as $index => $planned) {
		$thread = new Thread();
		$thread->setCategoryId($planned['categoryId']);
		$thread->setAuthorId($planned['author']);
		$thread->setTitle($planned['title']);
		$thread->setSlug($slugify($planned['title']));
		$thread->setViewCount($planned['views']);
		$thread->setPostCount(0);
		$thread->setIsLocked(false);
		$thread->setIsPinned(false);
		$thread->setIsHidden(false);
		$thread->setCreatedAt($planned['createdAt']);
		$thread->setUpdatedAt($planned['createdAt']);
		$plannedThreads[$index]['id'] = $threadMapper->insert($thread)->getId();

		$posts[] = ['thread' => $index, 'reply' => null, 'createdAt' => $planned['createdAt']];
		foreach ($planned['replies'] as $r => $reply) {
			$posts[] = ['thread' => $index, 'reply' => $r, 'createdAt' => $reply['createdAt']];
		}
	}

	// Posts across all threads in the order they were written, so post IDs grow with time.
	// A reply is always written after the post it answers, so its parent already has an ID.
	usort($posts, fn (array $a, array $b) => [$a['createdAt'], $a['reply'] ?? -1] <=> [$b['createdAt'], $b['reply'] ?? -1]);
	$postIds = [];
	$rootIds = [];
	foreach ($posts as $entry) {
		$planned = $plannedThreads[$entry['thread']];
		$post = new Post();
		$post->setThreadId($planned['id']);
		$post->setIsEdited(false);
		$post->setCreatedAt($entry['createdAt']);
		$post->setUpdatedAt($entry['createdAt']);

		if ($entry['reply'] === null) {
			$post->setAuthorId($planned['author']);
			$post->setContent($planned['body']);
			$post->setIsFirstPost(true);
			$postMapper->insert($post);
			continue;
		}

		$reply = $planned['replies'][$entry['reply']];
		$post->setAuthorId($reply['author']);
		$post->setContent($reply['content']);
		$post->setIsFirstPost(false);
		$key = $entry['thread'] . ':' . $entry['reply'];
		if ($reply['parent'] !== null) {
			$parentKey = $entry['thread'] . ':' . $reply['parent'];
			$post->setParentPostId($postIds[$parentKey]);
			$post->setRootReplyId($rootIds[$parentKey]);
		}
		$id = $postMapper->insert($post)->getId();
		$postIds[$key] = $id;
		$rootIds[$key] = $reply['parent'] !== null ? $rootIds[$entry['thread'] . ':' . $reply['parent']] : $id;
	}

	$db->commit();
} catch (\Throwable $e) {
	$db->rollBack();
	fwrite(STDERR, 'Seeding failed: ' . $e->getMessage() . "\n");
	exit(1);
}

seed_log(sprintf(
	'Created %d threads with %d replies each in %d categories',
	count($plannedThreads),
	$repliesPerThread,
	count($categories),
));

seed_rebuild_stats();
seed_log('Done.');
