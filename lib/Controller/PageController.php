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
		$this->logger->info('Forum page controller loaded');
		parent::__construct($appName, $request);
	}

	/**
	 * Helper to parse Vite Manifest
	 */
	private function getViteEntryScript(string $entryName): string {
		$jsDir = realpath(__DIR__ . '/../' . Application::JS_DIR);
		$manifestPath = dirname($jsDir) . '/.vite/manifest.json';

		if (!file_exists($manifestPath)) {
			return '';
		}

		$manifest = json_decode(file_get_contents($manifestPath), true);

		if (isset($manifest[$entryName]['file'])) {
			$manifestFile = $manifest[$entryName]['file'];
			$fullPath = dirname($jsDir) . '/' . $manifestFile;

			if (!file_exists($fullPath)) {
				return '';
			}

			return pathinfo($manifestFile, PATHINFO_FILENAME);
		}

		return '';
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
		$this->logger->info('Forum main page loaded');
		$mainScript = $this->getViteEntryScript('app.ts');
		return new TemplateResponse(Application::APP_ID, 'app', [
			'script' => $this->getViteEntryScript('app.ts'),
			'style' => $this->getViteEntryScript('style.css'),
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
