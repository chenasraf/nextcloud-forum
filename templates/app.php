<?php

use OCA\Forum\AppInfo\Application;
use OCP\Util;

/* @var array $_ */
$script = $_['script'];
Util::addScript(Application::APP_ID, Application::JS_DIR . "/forum-$script");
Util::addStyle(Application::APP_ID, Application::CSS_DIR . '/forum-style');
?>
<div id="forum-app"></div>
