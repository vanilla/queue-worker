<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue;

use Garden\Container\Container;

use Kaecyra\AppCommon\Config;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use Vanilla\ProductQueue\Log\LoggerBoilerTrait;

/**
 * Abstract Worker base class
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
abstract class QueueWorker implements LoggerAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;

    const QUEUE_DISTRIBUTION_KEY = 'queue.worker.distribution';

    /**
     * Dependency Injection Container
     * @var \Garden\Container\Container
     */
    protected $di;

    /**
     * App configuration
     * @var \Kaecyra\AppCommon\Config
     */
    protected $config;

    /**
     * Memcached cluster
     * @var \Memcached
     */
    protected $cache;

    /**
     * Actual queue driver
     * @var \Disque\Client
     */
    protected $queue;

    /**
     * Worker slot
     * @var int
     */
    protected $slot;

    /**
     * Construct app
     *
     * @param Container $di
     */
    public function __construct(Container $di, Config $config) {
        $this->di = $di;
        $this->config = $config;
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
    }

    abstract public function run($workerConfig = null);

}