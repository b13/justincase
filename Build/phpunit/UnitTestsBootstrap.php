<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "JustInCase" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

$autoloader = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    die('Please run "composer install" before running the unit tests.' . PHP_EOL);
}
require $autoloader;
