#!/usr/bin/env php
<?php

/**
 * ProductQueue is the asynchronous task running daemon for Vanilla's Hosted
 * environment.
 *
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */

namespace Vanilla\ProductQueue;

use \Garden\Daemon\Daemon;

use \Psr\Log\LogLevel;

// Reflect on ourselves for the version
$matched = preg_match('`@version ([\w\d\.-]+)$`im', file_get_contents(__FILE__), $matches);
if (!$matched) {
    echo "Unable to read version\n";
    exit;
}
$version = $matches[1];
define('APP_VERSION', $version);

include("bootstrap.php");

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
