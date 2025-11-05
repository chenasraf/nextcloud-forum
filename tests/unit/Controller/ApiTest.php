<?php

declare(strict_types=1);

namespace Controller;

use OCA\NextcloudAppTemplate\AppInfo\Application;
use OCA\NextcloudAppTemplate\Controller\ApiController;
use OCA\NextcloudAppTemplate\Service\FetchCurrenciesService;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase {
	public function testIndex(): void {
		$request = $this->createMock(IRequest::class);
		$config = $this->createMock(IAppConfig::class);
		$l = $this->createMock(IL10N::class);
		$service = $this->createMock(FetchCurrenciesService::class);

		$controller = new ApiController(Application::APP_ID, $request, $config, $l, $service);

		$resp = $controller->getCronInfo()->getData();
		echo json_encode($resp);
		$this->assertEquals(null, $resp['last_update']);
		$this->assertEquals(0, $resp['interval']);
	}
}
