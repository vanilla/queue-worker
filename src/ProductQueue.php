<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue;

/**
 * ProductQueue is the asynchronous task running daemon for Vanilla's Hosted
 * environment.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */

use Garden\Daemon\AppInterface;

use Garden\Container\Container;

use Garden\Cli\Cli;
use Garden\Cli\Args;

use \Kaecyra\AppCommon\Config;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use Vanilla\ProductQueue\Queue\QueueInterface;
use Vanilla\ProductQueue\Log\LoggerBoilerTrait;

class ProductQueue implements AppInterface, LoggerAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;

    protected $di;

    protected $cli;
    protected $args;

    /**
     * App configuration
     * @var Config
     */
    protected $config;

    /**
     * Actual queue driver
     * @var QueueInterface
     */
    protected $queue;

    /**
     * Construct app
     *
     * @param Container $di
     */
    public function __construct(Container $di) {
        $this->di = $di;
    }

    /**
     * Prepare commandline arguments
     *
     * @param Cli $cli
     * @param Config $config
     */
    public function preflight(Cli $cli, Config $config) {
        $this->log(LogLevel::INFO, "App preflight checking");

        $this->cli = $cli;
        $this->config = $config;

        // Validate configuration to allow early exit

        $queueDriver = $this->config->get('queue.driver');
        $this->log(LogLevel::INFO, " queue driver: {$queueDriver}");
        if (!$queueDriver) {
            throw new \Exception("No queue driver defined");
        }

        $queueDriverClass = ucfirst($queueDriver).'Queue';
        $queueDriverFullClass = sprintf('Vanilla\\ProductQueue\\Queue\\%s\\%s', ucfirst($queueDriver), $queueDriverClass);
        $this->log(LogLevel::INFO, " queue driver class: {$queueDriverClass}");
        if (!class_exists($queueDriverFullClass)) {
            $this->log(LogLevel::CRITICAL, "Could not find class {$queueDriverClass}");
            throw new \Exception("Requested queue driver '{$queueDriver}' is not supported");
        }
    }

    /**
     * Initialize app and disable console logging
     *
     * @param Args $args
     */
    public function initialize(Args $args) {
        $this->log(LogLevel::INFO, "App initializing");

        $this->args = $args;

        // Remove echo logger
        $this->log(LogLevel::INFO, " transitioning logger");
        $this->getLogger()->removeLogger('echo', false);
        $this->getLogger()->enableLogger('persist');

        // Prepare queue driver
        $this->log(LogLevel::INFO, " loading queue driver");
        $queueDriver = $this->config->get('queue.driver');
        $queueDriverClass = ucfirst($queueDriver).'Queue';
        $queueDriverFullClass = sprintf('Vanilla\\ProductQueue\\Queue\\%s\\%s', ucfirst($queueDriver), $queueDriverClass);

        // Allow queue driver to load config values
        $this->di->rule($queueDriverFullClass);
        $this->di->setConstructorArgs([new \Garden\Container\Reference([Config::class, "queue.{$queueDriver}"])]);

        $this->queue = $this->di->get($queueDriverFullClass);

        $this->log(LogLevel::INFO, " initialized");
    }

    /**
     * Run app instance
     *
     * This method acts as the main program scope for a worker. Forking has
     * already been handled at this point, so this scope is confined to a single
     * process.
     *
     * Returning from this function ends the process.
     */
    public function run() {
        $this->log(LogLevel::INFO, "App running");

        $sleep = mt_rand(5, 10);
        $this->log(LogLevel::INFO, " sleeping for {$sleep} sec");
        sleep($sleep);

    }

}