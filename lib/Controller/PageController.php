<?php

declare(strict_types=1);

namespace OCA\Forum\Controller;

use OCA\Forum\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Main app page
	 *
	 * @return TemplateResponse<Http::STATUS_OK,array{}>|PublicTemplateResponse<Http::STATUS_OK,array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function index(): TemplateResponse|PublicTemplateResponse {
		$user = $this->userSession->getUser();
		$templateData = [
			'script' => Application::getViteEntryScript('app.ts'),
			'style' => Application::getViteEntryScript('style.css'),
		];

		$response = null;

		if ($user) {
			$response = new TemplateResponse(Application::APP_ID, 'app', $templateData);
		} else {
			$response = new PublicTemplateResponse(Application::APP_ID, 'app', $templateData);
		}

		// Allow loading images from external sources in forum posts
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*');
		$csp->addAllowedMediaDomain('*');
		$response->setContentSecurityPolicy($csp);

		return $response;
	}

	/**
	 * Main app page - catch all route
	 *
	 * @return TemplateResponse<Http::STATUS_OK,array{}>|PublicTemplateResponse<Http::STATUS_OK,array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function catchAll(string $path = ''): TemplateResponse|PublicTemplateResponse {
		return $this->index();
	}
}
