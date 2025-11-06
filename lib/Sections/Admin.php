<?php

namespace OCA\Forum\Sections;

use OCA\Forum\AppInfo;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class Admin implements IIconSection {
	public function __construct(
		private IL10N $l,
		private IURLGenerator $urlGenerator,
	) {
		$this->l = $l;
		$this->urlGenerator = $urlGenerator;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath(AppInfo\Application::APP_ID, 'app-dark.svg');
	}

	public function getID(): string {
		return AppInfo\Application::APP_ID;
	}

	public function getName(): string {
		return $this->l->t('Forum');
	}

	public function getPriority(): int {
		return 80;
	}
}
