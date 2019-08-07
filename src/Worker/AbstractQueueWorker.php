<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Worker;

use Disque\Client;
use Garden\Container\Container;
use Kaecyra\AppCommon\ConfigCollection;
use Kaecyra\AppCommon\Event\EventManager;
use Memcached;
use Psr\Log\LoggerInterface;
use Vanilla\QueueWorker\Allocation\AllocationStrategyInterface;
use Vanilla\QueueWorker\Event\EventAwareTrait;
use Vanilla\QueueWorker\Log\LoggerBoilerTrait;
use Vanilla\QueueWorker\Message\Parser\ParserInterface;

/**
 * Abstract Worker base class
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 */
abstract class AbstractQueueWorker
{
    use LoggerBoilerTrait;
    use EventAwareTrait;

    const QUEUE_DISTRIBUTION_KEY = 'queue.worker.distribution';
    /**
     * List of queues
     * @var array
     */
    protected $queues;

    /**
     * Dependency Injection Container
     * @var \Garden\Container\Container;
     */
    private $container;

    /**
     * App configuration
     * @var \Kaecyra\AppCommon\Config
     */
    private $config;

    /**
     * @var \Psr\Log\LoggerInterface;
     */
    private $logger;

    /**
     * @var \Memcached
     */
    private $cache;

    /**
     * @var \Disque\Client
     */
    private $brokerClient;

    /**
     * @var \Vanilla\QueueWorker\Message\Parser\ParserInterface
     */
    private $parser;

    /**
     * @var \Vanilla\QueueWorker\Worker\AllocationStrategyInterface
     */
    private $allocationStrategy;

    /**
     * Event manager
     * @var EventManager
     *
     * Note: has to be protected because of the Trait
     */
    protected $eventManager;

    /**
     * AbstractQueueWorker constructor.
     *
     * @param \Garden\Container\Container $container
     * @param \Kaecyra\AppCommon\ConfigCollection $config
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Kaecyra\AppCommon\Event\EventManager $eventManager
     * @param \Memcached $cache
     * @param \Disque\Client $brokerClient
     * @param \Vanilla\QueueWorker\Message\Parser\ParserInterface $parser
     * @param \Vanilla\QueueWorker\Allocation\AllocationStrategyInterface $allocationStrategy
     */
    public function __construct(
        Container $container,
        ConfigCollection $config,
        LoggerInterface $logger,
        EventManager $eventManager,
        Memcached $cache,
        Client $brokerClient,
        ParserInterface $parser,
        AllocationStrategyInterface $allocationStrategy
    ) {
        $this->container = $container;
        $this->config = $config;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->cache = $cache;
        $this->brokerClient = $brokerClient;
        $this->parser = $parser;
        $this->allocationStrategy = $allocationStrategy;

        // Prepare Queue config
        $this->getQueues(null, true);
    }

    /**
     * Get raw queues
     *
     * @return array
     */
    protected function getRawQueues(): array
    {
        $queues = $this->config->get('queue.queues');
        return $this->fireFilter('getRawQueues', $queues);
    }

    /**
     * Get list of queues
     *
     * @param string $scope
     * @param bool $refresh
     *
     * @return array|string
     */
    protected function getQueues($scope = null, $refresh = false)
    {
        if ($refresh) {
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
                'simple' => array_column(array_values($sorted), 'name'),
                'full' => $sorted,
                'pull' => implode(' ', array_column(array_values($sorted), 'name')),
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
     *
     * @return array|null
     */
    protected function getQueueDefinition($queueName): array
    {
        return val($queueName, $this->getQueues('full'), null);
    }

    /**
     * @return array
     */
    public function getCurrentQueues()
    {
        return $this->queues['pull'] ?? [];
    }

    /**
     * Getter of container.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Getter of config.
     *
     * @return ConfigCollection
     */
    public function getConfig(): ConfigCollection
    {
        return $this->config;
    }

    /**
     * Getter of logger.
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Getter of eventManager.
     *
     * @return EventManager
     */
    public function getEventManager(): EventManager
    {
        return $this->eventManager;
    }

    /**
     * Getter of cache.
     *
     * @return Memcached
     */
    public function getCache(): Memcached
    {
        return $this->cache;
    }

    /**
     * Getter of brokerClient.
     *
     * @return Client
     */
    public function getBrokerClient(): Client
    {
        return $this->brokerClient;
    }

    /**
     * Getter of parser.
     *
     * @return ParserInterface
     */
    public function getParser(): ParserInterface
    {
        return $this->parser;
    }

    /**
     * Getter of allocationStrategy.
     *
     * @return AllocationStrategyInterface
     */
    public function getAllocationStrategy(): AllocationStrategyInterface
    {
        return $this->allocationStrategy;
    }

    /**
     * Run worker
     *
     * @param mixed $workerConfig
     */
    abstract public function run($workerConfig);
}
