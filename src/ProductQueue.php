<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue;

use Garden\Daemon\AppInterface;

use Garden\Container\Container;

use Garden\Cli\Cli;
use Garden\Cli\Args;

use Kaecyra\AppCommon\Config;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use Vanilla\ProductQueue\Log\LoggerBoilerTrait;


/**
 * ProductQueue is the asynchronous task running daemon for Vanilla's Hosted
 * environment.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
class ProductQueue implements AppInterface, LoggerAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;

    /**
     *
     * @var \Garden\Container\Container
     */
    protected $di;

    protected $cli;
    protected $args;

    /**
     * App configuration
     * @var \Kaecyra\AppCommon\Config
     */
    protected $config;

    /**
     *
     * @var \Memcached
     */
    protected $cache;

    /**
     * Actual queue driver
     * @var \Disque\Client
     */
    protected $queue;

    /**
     * Number of iterations remaining
     * @var int
     */
    protected $iterations;

    /**
     * Number of per-job retries
     * @var int
     */
    protected $retries;

    /**
     * Construct app
     *
     * @param Container $di
     */
    public function __construct(Container $di) {
        $this->di = $di;
    }

    /**
     * Check environment for app runtime compatibility
     *
     * Provide any custom CLI configuration, and check validity of configuration.
     *
     * @param Cli $cli
     * @param Config $config
     */
    public function preflight(Cli $cli, Config $config) {
        $this->log(LogLevel::INFO, "Application preflight checking");

        $this->cli = $cli;
        $this->config = $config;

    }

    /**
     * Initialize app and disable console logging
     *
     * This occurs in the main daemon process, prior to worker forking. No
     * connections should be established here, since this method's actions are
     * pre-worker forking, and will be shared to child processes.
     *
     * @param Args $args
     */
    public function initialize(Args $args) {
        $this->log(LogLevel::INFO, "Application initializing");

        $this->args = $args;

        // Remove echo logger

        $this->log(LogLevel::INFO, " transitioning logger");
        $this->getLogger()->removeLogger('echo', false);
        $this->getLogger()->enableLogger('persist');

        $this->log(LogLevel::INFO, "Application initialized");
    }

    /**
     * Prepare worker
     *
     * This method prepares a forked worker to begin handling messages from
     * the queue.
     */
    protected function prepareWorker() {

        // Prepare cache driver

        $this->log(LogLevel::INFO, "Connecting to cache");
        $cacheNodes = $this->config->get('cache.nodes');
        $this->cache = new \Memcached;
        foreach ($cacheNodes as $node) {
            $this->cache->addServer($node[0], $node[1]);
        }
        $this->log(LogLevel::INFO, " cache servers: {nodes}", [
            'nodes' => count($cacheNodes)
        ]);

        $this->di->setInstance($this->cache::class, $this->cache);

        // Prepare queue driver

        $this->log(LogLevel::INFO, "Connecting to queue");
        $queueNodes = $this->config->get('queue.nodes');
        foreach ($queueNodes as &$node) {
            $node = $this->di->getConstructorArgs(\Disque\Connection\Credentials, []);
        }
        $this->queue = new \Disque\Client($queueNodes);
        $this->log(LogLevel::INFO, " queue servers: {nodes}", [
            'nodes' => count($queueNodes)
        ]);

        $this->di->setInstance($this->queue::class, $this->queue);

        // Prepare limits

        $this->iterations = $this->config->get('process.max_requests', 0);
        $this->retries = $this->config->get('process.max_retries', 0);
    }

    /**
     * Run worker instance
     *
     * This method is the main program scope for a worker. Forking has
     * already been handled at this point, so this scope is confined to a single
     * process.
     *
     * Returning from this function ends the process.
     */
    public function run() {
        $this->log(LogLevel::INFO, "Worker started");

        // Prepare worker environment
        $this->prepareWorker();

        while($this->isReady()) {

            $message = $this->queue->getJob($queues);

            $this->iterations--;
        }

    }

    /**
     * Check if queue is a ready to retrieve jobs
     *
     * @return bool
     */
    public function isReady() {
        return $this->iterations > 0;
    }

}