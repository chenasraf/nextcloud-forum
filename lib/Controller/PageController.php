<?php

declare(strict_types=1);

namespace OCA\Forum\Controller;

use OCA\Forum\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Main app page
	 *
	 * @return TemplateResponse<Http::STATUS_OK,array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$mainScript = Application::getViteEntryScript('app.ts');
		return new TemplateResponse(Application::APP_ID, 'app', [
			'script' => Application::getViteEntryScript('app.ts'),
			'style' => Application::getViteEntryScript('style.css'),
		]);
	}

	/**
	 * Main app page - catch all route
	 *
	 * @return TemplateResponse<Http::STATUS_OK,array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function catchAll(string $path = ''): TemplateResponse {
		return $this->index();
	}
}
