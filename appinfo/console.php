<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

use OCA\Forum\Command\TestNotifier;

/** @var Symfony\Component\Console\Application $application */
$application->add(\OC::$server->get(TestNotifier::class));
