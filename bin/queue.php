#!/usr/bin/env php
<?php

/**
 * ProductQueue is the asynchronous task running daemon for Vanilla's Hosted
 * environment.
 *
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @version 1.0
 */

namespace Vanilla\QueueWorker;

use \Garden\Daemon\Daemon;
use \Psr\Log\LogLevel;

// Switch to queue root
chdir(dirname($argv[0]));

// Include the core autoloader.

$paths = [
    __DIR__.'/../vendor/autoload.php',  // locally
    __DIR__.'/../../../autoload.php'    // dependency
];
foreach ($paths as $path) {
    if (file_exists($path)) {
        echo " including {$path}\n";
        require_once $path;
        break;
    }
}

// Run bootstrap
QueueWorker::bootstrap();

$exitCode = 0;
try {

    $daemon = $di->get(Daemon::class);
    $exitCode = $daemon->attach($argv);

} catch (\Garden\Daemon\Exception $ex) {

    $exceptionCode = $ex->getCode();
    if ($exceptionCode != 200) {

        if ($ex->getFile()) {
            $line = $ex->getLine();
            $file = $ex->getFile();
            $logger->log(LogLevel::ERROR, "Error on line {$line} of {$file}:");
        }
        $logger->log(LogLevel::ERROR, $ex->getMessage());
    }

}
catch (\Exception $ex) {
    $exitCode = 1;

    if ($ex->getFile()) {
        $line = $ex->getLine();
        $file = $ex->getFile();
        $logger->log(LogLevel::ERROR, "Error on line {$line} of {$file}:");
    }
    $logger->log(LogLevel::ERROR, $ex->getMessage());
}

exit($exitCode);
