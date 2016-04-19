#!/usr/bin/env php
<?php
/**
 * Copyright 2015 Eric Enold <zyberspace@zyberware.org>
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
require('vendor/autoload.php');

$telegram = new \Zyberspace\Telegram\Cli\Client('unix:///tmp/tg.sck');

$discoverShell = new \Zyberspace\DiscoveryShell($telegram, 'telegram', __DIR__ . '/.developer-shell-history');
$discoverShell->run();
