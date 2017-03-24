<?php

/**
 * ProductQueue bootstrap
 *
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 */

use Kaecyra\AppCommon\ConfigInterface;
use Kaecyra\AppCommon\AbstractConfig;
use Kaecyra\AppCommon\ConfigCollection;

use Kaecyra\AppCommon\Event\EventAwareInterface;

use Vanilla\ProductQueue\Addon\AddonManager;

use Garden\Container\Container;
use Garden\Container\Reference;

use Garden\Daemon\Daemon;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

chdir(dirname(__FILE__));

define('APP', 'productqueue');
define('PATH_ROOT', getcwd());
date_default_timezone_set('UTC');

// Check environment

if (PHP_VERSION_ID < 70000) {
    die(APP." requires PHP 7.0 or greater.");
}

if (posix_getuid() != 0) {
    echo "Must be root.\n";
    exit;
}

// Report and track all errors

error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
ini_set('display_errors', 0);
ini_set('track_errors', 1);

define('PATH_CONFIG', PATH_ROOT.'/conf');

// Include the core autoloader.

$autoloader = __DIR__.'/vendor/autoload.php';
if (!file_exists($autoloader)) {
    echo "Generating autoloader...\n";
    $compose = "sudo composer install --no-dev --prefer-dist --optimize-autoloader";
    exec($compose);

    if (!file_exists($autoloader)) {
        echo "Unable to automatically generate autoloader.\n";
        echo " Try: {$compose}\n";
        exit;
    }
}
require_once $autoloader;

// Prepare Dependency Injection

$di = new Container;
$di
    ->setInstance(Container::class, $di)

    ->defaultRule()
    ->setShared(true)

    ->rule(ConfigCollection::class)
    ->addAlias(AbstractConfig::class)
    ->addAlias(ConfigInterface::class)
    ->addCall('addFile', [paths(PATH_ROOT, 'conf/config.json'), false])
    ->addCall('addFolder', [paths(PATH_ROOT, 'conf/conf.d'), 'json'])

    ->rule(LoggerAwareInterface::class)
    ->addCall('setLogger')

    ->rule(EventAwareInterface::class)
    ->addCall('setEventManager')

    ->rule(AddonManager::class)
    ->setConstructorArgs([new Reference([AbstractConfig::class, 'addons.scan'])])

    ->rule(Daemon::class)
    ->setConstructorArgs([
        [
            'appversion'        => APP_VERSION,
            'appdir'            => PATH_ROOT,
            'appdescription'    => 'Vanilla Product Queue',
            'appnamespace'      => 'Vanilla\\ProductQueue',
            'appname'           => 'ProductQueue',
            'authorname'        => 'Tim Gunter',
            'authoremail'       => 'tim@vanillaforums.com'
        ],
        new Reference([AbstractConfig::class, 'daemon'])
    ])
    ->addCall('configure', [new Reference([AbstractConfig::class, "daemon"])]);

// Set up loggers

$logger = new \Kaecyra\AppCommon\Log\AggregateLogger;
$logLevel = $di->get(AbstractConfig::class)->get('log.level');
$loggers = $di->get(AbstractConfig::class)->get('log.loggers');
foreach ($loggers as $logConfig) {
    $loggerClass = "Kaecyra\\AppCommon\\Log\\".ucfirst($logConfig['destination']).'Logger';
    if ($di->has($loggerClass)) {
        $subLogger = $di->getArgs($loggerClass, [PATH_ROOT, $logConfig]);
        $logger->addLogger($subLogger, $logConfig['level'] ?? $logLevel, $logConfig['key'] ?? null);
    }
}

$logger->disableLogger('persist');
$di->setInstance(LoggerInterface::class, $logger);