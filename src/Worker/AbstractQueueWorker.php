<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Worker;

use Vanilla\QueueWorker\Log\LoggerBoilerTrait;
use Vanilla\QueueWorker\Message\Parser\ParserInterface;

use Garden\Container\Container;

use Kaecyra\AppCommon\AbstractConfig;
use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

/**
 * Abstract Worker base class
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
abstract class AbstractQueueWorker implements LoggerAwareInterface, EventAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use EventAwareTrait;

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
     * List of queues
     * @var array
     */
    protected $queues;

    /**
     * Message Parser
     * @var ParserInterface
     */
    protected $parser;

    /**
     * Construct app
     *
     * @param Container $di
     */
    public function __construct(Container $di, AbstractConfig $config) {
        $this->di = $di;
        $this->config = $config;
        $this->queues = null;
    }

    /**
     * Get raw queues
     *
     * @return array
     */
    protected function getRawQueues(): array {
        $queues = $this->config->get('queue.queues');
        return $this->fireFilter('getRawQueues', $queues);
    }

    /**
     * Get list of queues
     *
     * @param string $scope
     * @param bool $refresh
     * @return array|string
     */
    protected function getQueues($scope = null, $refresh = false) {
        if (is_null($this->queues) || $refresh) {
            // Prepare queues (sort by priority)
            $queues = $this->getRawQueues();
            $sorted = [];
            $priorityOffsets = [];
            foreach ($queues as $queue) {
                $priority = $queue['priority'] * 100;
                $offset = val($priority, $priorityOffsets, 0);
                $priority += $offset;
                $priorityOffsets[$priority] = $offset;
                $sorted[$priority] = $queue;
            }

            $this->queues = [
                'simple'    => array_column(array_values($sorted), 'name'),
                'full'      => $sorted,
                'pull'      => implode(' ', array_column(array_values($sorted), 'name')),
            ];
        }

        // Get all queues?
        if (!$scope) {
            return $this->queues;
        }

        // Get scoped
        return val($scope, $this->queues);
    }

    /**
     * Get a queue definition
     *
     * @param string $queueName
     * @return array|null
     */
    protected function getQueueDefinition($queueName): array {
        return val($queueName, $this->getQueues('full'), null);
    }

    /**
     * Prepare worker
     *
     * This method prepares a forked worker to begin handling messages from
     * the queue.
     */
    public function prepareWorker() {

        // Prepare cache driver

        $this->log(LogLevel::INFO, " connecting to cache");
        $cacheNodes = $this->config->get('cache.nodes');
        $this->log(LogLevel::INFO, "  cache servers: {nodes}", [
            'nodes' => count($cacheNodes)
        ]);

        $this->cache = new \Memcached;
        foreach ($cacheNodes as $node) {
            $this->log(LogLevel::INFO, "  cache: {server}:{port}", [
                'server'    => $node[0],
                'port'      => $node[1]
            ]);
            $this->cache->addServer($node[0], $node[1]);
        }

        $this->di->setInstance(\Memcached::class, $this->cache);

        // Prepare queue driver

        $this->log(LogLevel::INFO, " connecting to queue");
        $queueNodes = $this->config->get('queue.nodes');
        $this->log(LogLevel::INFO, "  queue servers: {nodes}", [
            'nodes' => count($queueNodes)
        ]);

        foreach ($queueNodes as &$node) {
            $this->log(LogLevel::INFO, "  queue: {server}:{port}", [
                'server'    => $node[0],
                'port'      => $node[1]
            ]);
            $node = $this->di->getArgs(\Disque\Connection\Credentials::class, $node);
        }
        $this->queue = new \Disque\Client($queueNodes);
        $this->queue->connect();

        $this->di->setInstance(\Disque\Client::class, $this->queue);

        // Prepare queue message parser

        $this->parser = $this->di->get(ParserInterface::class);
        $this->log(LogLevel::INFO, " using parser {class}",[
            'class' => get_class($this->parser)
        ]);
    }

    /**
     * Get worker slot
     *
     * @return int slot number
     */
    public function getSlot(): int {
        return $this->slot;
    }

    /**
     * Run worker
     *
     * @param mixed $workerConfig
     */
    abstract public function run($workerConfig);

}