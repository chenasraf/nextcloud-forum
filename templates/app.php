<?php

use OCA\NextcloudAppTemplate\AppInfo\Application;
use OCP\Util;

/* @var array $_ */
$script = $_['script'];
Util::addScript(Application::APP_ID, Application::JS_DIR . "/nextcloudapptemplate-$script");
Util::addStyle(Application::APP_ID, Application::CSS_DIR . '/nextcloudapptemplate-style');
?>
<div id="nextcloudapptemplate-app"></div>
