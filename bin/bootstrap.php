<?php

/**
 * Queue worker bootstrap
 *
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @version 0.1.0
 */

use Kaecyra\AppCommon\ConfigInterface;
use Kaecyra\AppCommon\AbstractConfig;
use Kaecyra\AppCommon\ConfigCollection;

use Kaecyra\AppCommon\Event\EventAwareInterface;

use Vanilla\QueueWorker\Addon\AddonManager;

use Garden\Container\Container;
use Garden\Container\Reference;

use Garden\Daemon\Daemon;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

// Reflect on ourselves for the version
$matched = preg_match('`@version ([\w\d\.-]+)$`im', file_get_contents(__FILE__), $matches);
if (!$matched) {
    echo "Unable to read version\n";
    exit;
}
$version = $matches[1];
define('APP_VERSION', $version);

// Switch to queue root
chdir(dirname($argv[0]));

define('APP', 'queue-worker');
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
            'appnamespace'      => 'Vanilla\\QueueWorker',
            'appname'           => 'QueueWorker',
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