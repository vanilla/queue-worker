#!/usr/bin/env php
<?php

/**
 * Queue-Worker is an asynchronous task running daemon.
 *
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 */

use Garden\Daemon\Daemon;
use Psr\Log\LogLevel;
use Vanilla\QueueWorker\QueueWorker;

chdir(dirname($argv[0]));
$DIR = getcwd();

if (!file_exists($DIR.'/vendor/autoload.php') &&
    file_exists(dirname(realpath(__FILE__)).'/vendor/autoload.php')
) {
    $DIR = dirname(realpath(__FILE__));
}

require_once $DIR.'/vendor/autoload.php';

try {
    // Run bootstrap
    QueueWorker::bootstrap($DIR);

    /** @var Daemon $daemon */
    $daemon = $container->get(Daemon::class);
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

} catch (Throwable $ex) {
    $exitCode = 1;

    if ($ex->getFile()) {
        $line = $ex->getLine();
        $file = $ex->getFile();
        $logger->log(LogLevel::ERROR, "Error on line {$line} of {$file}:");
    }
    $logger->log(LogLevel::ERROR, $ex->getMessage());
}

exit($exitCode);
