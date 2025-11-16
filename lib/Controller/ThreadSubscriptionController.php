<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\ThreadSubscriptionMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ThreadSubscriptionController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ThreadSubscriptionMapper $subscriptionMapper,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Subscribe current user to a thread to receive notifications
	 *
	 * @param int $threadId Thread ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: User subscribed to thread
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/threads/{threadId}/subscribe')]
	public function subscribe(int $threadId): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$subscription = $this->subscriptionMapper->subscribe($user->getUID(), $threadId);
			return new DataResponse([
				'success' => true,
				'subscription' => $subscription->jsonSerialize(),
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error subscribing user to thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to subscribe to thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Unsubscribe current user from a thread
	 *
	 * @param int $threadId Thread ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: User unsubscribed from thread
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/threads/{threadId}/subscribe')]
	public function unsubscribe(int $threadId): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$this->subscriptionMapper->unsubscribe($user->getUID(), $threadId);
			return new DataResponse(['success' => true]);
		} catch (\Exception $e) {
			$this->logger->error('Error unsubscribing user from thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to unsubscribe from thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Check if current user is subscribed to a thread
	 *
	 * @param int $threadId Thread ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Subscription status returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/threads/{threadId}/subscribe')]
	public function isSubscribed(int $threadId): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$isSubscribed = $this->subscriptionMapper->isUserSubscribed($user->getUID(), $threadId);
			return new DataResponse(['isSubscribed' => $isSubscribed]);
		} catch (\Exception $e) {
			$this->logger->error('Error checking thread subscription status: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to check subscription status'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get all threads the current user is subscribed to
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Thread subscriptions returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/thread-subscriptions')]
	public function getUserSubscriptions(): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$subscriptions = $this->subscriptionMapper->findByUserId($user->getUID());
			return new DataResponse(array_map(fn ($r) => $r->jsonSerialize(), $subscriptions));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching user thread subscriptions: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch thread subscriptions'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
