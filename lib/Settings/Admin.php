<?php

namespace OCA\NextcloudAppTemplate\Settings;

use OCA\NextcloudAppTemplate\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public function __construct(
		private IAppConfig $config,
		private IL10N $l,
	) {
		$this->config = $config;
		$this->l = $l;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, Application::JS_DIR . '/nextcloudapptemplate-settings');
		Util::addStyle(Application::APP_ID, Application::CSS_DIR . '/nextcloudapptemplate-style');
		return new TemplateResponse(Application::APP_ID, 'settings', [], '');
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 *             the admin section. The forms are arranged in ascending order of the
	 *             priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority(): int {
		return 10;
	}
}
