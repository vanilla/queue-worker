<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Worker;

use Disque\Client;
use Disque\Connection\ConnectionException;
use Disque\Connection\Credentials;
use Kaecyra\AppCommon\AbstractConfig;
use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Memcached;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Throwable;
use Vanilla\QueueWorker\Event\WorkerEvent;
use Vanilla\QueueWorker\Log\LoggerBoilerTrait;
use Vanilla\QueueWorker\Message\Parser\ParserInterface;

/**
 * Abstract Worker base class
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 */
abstract class AbstractQueueWorker implements LoggerAwareInterface, EventAwareInterface
{

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use EventAwareTrait;

    const QUEUE_DISTRIBUTION_KEY = 'queue.worker.distribution';
    const QUEUE_CONNECT_TIMEOUT = 5;
    const QUEUE_RESPONSE_TIMEOUT = 5;

    const BACKOFF_FACTOR_MS = 100;

    /**
     * Dependency Injection Container
     * @var \Psr\Container\ContainerInterface;
     */
    protected $container;

    /**
     * App configuration
     * @var \Kaecyra\AppCommon\Config
     */
    protected $config;

    /**
     * Memcached cluster
     * @var Memcached
     */
    private $cache;

    /**
     * Actual queue driver
     * @var \Disque\Client
     */
    private $queue;

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
    private $parser;

    /**
     * How many retries have occurred
     * @var int
     */
    protected $retries = 0;

    /**
     * AbstractQueueWorker constructor.
     *
     * @param \Psr\Container\ContainerInterface $di
     * @param \Kaecyra\AppCommon\AbstractConfig $config
     */
    public function __construct(ContainerInterface $di, AbstractConfig $config)
    {
        $this->container = $di;
        $this->config = $config;
        $this->queues = null;
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
     * Prepare worker
     *
     * This method prepares a forked worker to begin handling messages from
     * the queue.
     *
     * @param int $tries number of retries to permit
     *
     * @throws ConnectionException
     */
    public function prepareWorker($tries)
    {

        // Prepare cache driver

        $this->log(LogLevel::INFO, " connecting to cache");
        $cacheNodes = $this->config->get('cache.nodes');
        $this->log(LogLevel::INFO, "  cache servers: {nodes}", [
            'nodes' => count($cacheNodes),
        ]);

        $this->cache = new Memcached();
        foreach ($cacheNodes as $node) {
            $this->log(LogLevel::INFO, "  cache: {server}:{port}", [
                'server' => $node[0],
                'port' => $node[1],
            ]);
            $this->cache->addServer($node[0], $node[1]);
        }

        $this->container->setInstance(Memcached::class, $this->cache);

        // Prepare queue driver

        $this->log(LogLevel::INFO, " connecting to queue");
        $queueNodes = $this->config->get('queue.nodes');
        $this->log(LogLevel::INFO, "  queue servers: {nodes}", [
            'nodes' => count($queueNodes),
        ]);

        // Don't re-use credentials

        $this->container->rule(Credentials::class)->setShared(false);

        $nodeDefault = [
            'host' => null,
            'port' => null,
            'password' => null,
            'connectionTimeout' => self::QUEUE_CONNECT_TIMEOUT,
            'responseTimeout' => self::QUEUE_RESPONSE_TIMEOUT,
        ];

        foreach ($queueNodes as &$node) {
            $this->log(LogLevel::INFO, "  queue: {server}:{port}", [
                'server' => $node[0],
                'port' => $node[1],
            ]);
            $node = array_replace($nodeDefault, [
                'host' => $node[0],
                'port' => $node[1],
                'password' => $node[2] ?? null,
                'connectionTimeout' => self::QUEUE_CONNECT_TIMEOUT,
                'responseTimeout' => self::QUEUE_RESPONSE_TIMEOUT,
            ]);
            $node = $this->container->getArgs(Credentials::class, $node);
        }
        $this->queue = new Client($queueNodes);

        // Attempt to connect

        while (1) {
            try {
                $this->queue->connect();
            } catch (ConnectionException $e) {
                if ($this->retries > $tries) {
                    $this->log(LogLevel::INFO, " failed to connect: {emsg}, giving up after {tries} attempts", [
                        'emsg' => $e->getMessage(),
                        'tries' => $this->retries,
                    ]);
                    throw $e;
                }

                // Exponential backoff
                $delay = pow(2, $this->retries) * self::BACKOFF_FACTOR_MS;
                $delaySeconds = round($delay / 1000, 2);
                $this->log(LogLevel::INFO, " failed to connect: {emsg}, backoff for {sec} seconds", [
                    'emsg' => $e->getMessage(),
                    'sec' => $delaySeconds,
                ]);
                usleep($delay * 1000);

                $this->retries++;
                continue;
            }

            // Connected
            break;
        }

        // Connected

        $this->retries = 0;
        $this->container->setInstance(Client::class, $this->queue);

        // Prepare queue message parser

        $this->parser = $this->container->get(ParserInterface::class);
        $this->log(LogLevel::INFO, " using parser `{class}`", [
            'class' => get_class($this->parser),
        ]);

        // Don't share jobs
        $this->container->rule(AbstractJob::class)->setShared(false);
    }

    /**
     * Get worker slot
     *
     * @return int slot number
     */
    public function getSlot(): int
    {
        return $this->slot;
    }

    /**
     * Getter of parser.
     *
     * @return \Vanilla\QueueWorker\Message\Parser\ParserInterface
     */
    public function getParser(): ParserInterface
    {
        return $this->parser;
    }

    /**
     * Getter of queue.
     *
     * @return Client
     */
    public function getDisqueClient(): Client
    {
        return $this->queue;
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
     * @param WorkerEvent $event
     *
     * @return WorkerEvent
     *
     * @throws Throwable
     */
    public function fireEvent(WorkerEvent $event)
    {
        $eventResults = $this->fireReturn($event->getName(), [$event]);
        $event->setStackResult($eventResults);
        return $event;
    }

    /**
     * @return array
     */
    public function getCurrentQueues()
    {
        return $this->queues['pull'] ?? [];
    }

    /**
     * Run worker
     *
     * @param mixed $workerConfig
     */
    abstract public function run($workerConfig);

}
