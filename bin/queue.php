#!/usr/bin/env php
<?php

/**
 * Queue-Worker is an asynchronous task running daemon.
 *
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @version 2.0.0
 *
 */

use Garden\Cli\Cli;
use Garden\Container\Container;
use Garden\Daemon\Daemon;
use Kaecyra\AppCommon\AbstractConfig;
use Kaecyra\AppCommon\ConfigCollection;
use Kaecyra\AppCommon\ConfigInterface;
use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventManager;
use Kaecyra\AppCommon\Log\AggregateLogger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Vanilla\QueueWorker\Addon\AddonManager;

// Check environment
if (PHP_VERSION_ID < 70000) {
    die(APP." requires PHP 7.0 or greater.");
}

// Reflect on ourselves for the version
$matched = preg_match('`@version ([\w\d\.-]+)$`im', file_get_contents(__FILE__), $matches);
if (!$matched) {
    echo "Unable to read version\n";
    exit;
}

$version = $matches[1];
define('APP_VERSION', $version);
define('APP', 'queue-worker');
date_default_timezone_set('UTC');

// PATH_ROOT
chdir(dirname($argv[0]));
$DIR = getcwd();

if (!file_exists($DIR.'/vendor/autoload.php') &&
    file_exists(dirname(realpath(__FILE__)).'/vendor/autoload.php')
) {
    $DIR = dirname(realpath(__FILE__));
}

require_once $DIR.'/vendor/autoload.php';
define('PATH_ROOT', $DIR);
define('PATH_CONFIG', PATH_ROOT.'/conf');

try {
    // Report and track all errors
    error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
    ini_set('display_errors', 0);
    ini_set('track_errors', 1);

    // Prepare Dependency Injection
    $container = new Container();
    $container
        // No singletons by default.
        ->defaultRule()
        ->setShared(false)
        // Container
        ->setInstance(Container::class, $container)
        ->setInstance(ContainerInterface::class, $container)
        // LoggerAwareInterface
        ->rule(LoggerAwareInterface::class)
        ->addCall('setLogger')
        // EventAwareInterface
        ->rule(EventAwareInterface::class)
        ->addCall('setEventManager')
    ;

    // Set up config
    $config = new ConfigCollection();
    $config->addFile(PATH_CONFIG.'/config.json', false);
    $config->addFolder(PATH_CONFIG.'/conf.d', 'json');
    $container->setInstance(ConfigCollection::class, $config);
    $container->setInstance(AbstractConfig::class, $config);
    $container->setInstance(ConfigInterface::class, $config);

    // Set up loggers
    $logger = new AggregateLogger();
    $container->setInstance(LoggerInterface::class, $logger);

    $logLevel = $config->get('log.level');
    $loggers = $config->get('log.loggers');
    foreach ($loggers as $logConfig) {
        $loggerClass = "Kaecyra\\AppCommon\\Log\\".ucfirst($logConfig['destination']).'Logger';
        if ($container->has($loggerClass)) {
            $subLogger = $container->getArgs($loggerClass, [PATH_ROOT, $logConfig]);
            $logger->addLogger($subLogger, $logConfig['level'] ?? $logLevel, $logConfig['key'] ?? null);
        }
    }

    // Set up EventManager
    $eventManager = new EventManager();
    $container->setInstance(EventManager::class, $eventManager);

    // Set up AddonManager
    $addonManager = new AddonManager($container, []);
    $container->setInstance(AddonManager::class, $addonManager);

    $addonManager->setLogger($logger);
    $addonManager->setEventManager($eventManager);

    $addonScanDirs = $config->get('addons.scan');
    foreach ($addonScanDirs as $addonScanDir) {
        $addonScanDir = paths(PATH_ROOT, $addonScanDir);
        $addonManager->addSource($addonScanDir);
    }
    $addonManager->scanSourceFolders();

    $cli = new Cli();
    $container->setInstance(Cli::class, $cli);

    if ($config->get('daemon.enable', true) === true) {
        // Init the Daemon
        $daemon = new Daemon(
            $cli,
            $container,
            [
                'appversion' => APP_VERSION,
                'appdir' => PATH_ROOT,
                'appdescription' => 'Asynchronous Queue Worker',
                'appnamespace' => 'Vanilla\\QueueWorker',
                'appname' => 'QueueApp',
                'authorname' => 'Tim Gunter',
                'authoremail' => 'tim@vanillaforums.com',
            ],
            $config->get('daemon')
        );
        $logger->disableLogger('persist');
        $daemon->setLogger($logger);
        $exitCode = $daemon->attach($argv);
    } else {
        /* @var Vanilla\QueueWorker\QueueApp $app */
        $app = $container->get('Vanilla\\QueueWorker\\QueueApp');
        $workerConfig = $app->getWorkerConfig();
        $app->run($workerConfig);
    }

} catch (Throwable $ex) {
    $exitCode = 1;

    $msg = $ex->getMessage();
    $line = $ex->getLine();
    $file = $ex->getFile();
    $trace = $ex->getTraceAsString();

    $error = "$msg\n[$line] $file\n$trace";

    if ($logger) {
        $logger->log(LogLevel::ERROR, $error);
    } else {
        error_log($error);
    }
}

exit($exitCode);
