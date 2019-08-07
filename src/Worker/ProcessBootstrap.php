<?php

namespace Vanilla\QueueWorker\Worker;

use Disque\Client;
use Disque\Connection\ConnectionException;
use Disque\Connection\Credentials;
use Disque\Connection\Node\NullPrioritizer;
use Garden\Container\Container;
use Kaecyra\AppCommon\ConfigCollection;
use Kaecyra\AppCommon\Event\EventManager;
use Memcached;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Vanilla\QueueWorker\Addon\AddonManager;
use Vanilla\QueueWorker\Allocation\AllocationStrategyInterface;
use Vanilla\QueueWorker\Log\LoggerBoilerTrait;
use Vanilla\QueueWorker\Message\Parser\ParserInterface;

/**
 * Class ProcessBootstrap.
 */
class ProcessBootstrap
{
    use LoggerBoilerTrait;

    /**
     * @var \Vanilla\QueueWorker\Worker\Container
     */
    protected $container;

    /**
     * @var \Kaecyra\AppCommon\ConfigCollection
     */
    protected $config;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Kaecyra\AppCommon\Event\EventManager
     */
    protected $eventManager;

    /**
     * @var \Vanilla\QueueWorker\Addon\AddonManager
     */
    protected $addonManager;

    /**
     * * Connection parameters and Backoff factor for retrying connecting to the Broker
     */
    const BROKER_MAX_RETRIES = 5;
    const BROKER_BACKOFF_FACTOR_MS = 100;
    const BROKER_CONNECT_TIMEOUT = 5;
    const BROKER_RESPONSE_TIMEOUT = 5;

    /**
     * ProcessBootstrap constructor.
     *
     * @param \Garden\Container\Container $container
     * @param \Kaecyra\AppCommon\ConfigCollection $config
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Kaecyra\AppCommon\Event\EventManager $eventManager
     */
    public function __construct(Container $container, ConfigCollection $config, LoggerInterface $logger, EventManager $eventManager, AddonManager $addonManager)
    {
        $this->container = $container;
        $this->config = $config;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->addonManager = $addonManager;
    }

    /**
     * Set the container for the new forked process
     *
     * @throws \Disque\Connection\ConnectionException
     */
    public function init()
    {
        $this->prepareParser();
        $this->prepareAllocation();
        $this->prepareCache();
        $this->prepareBroker();
        $this->startAddons();
    }

    /**
     * Prepare Cache and put it into the Container
     */
    protected function prepareCache()
    {
        // Prepare Cache
        $cacheNodes = $this->config->get('cache.nodes');

        $cache = new Memcached();
        $cacheLog = [];
        foreach ($cacheNodes as $node) {
            $cacheLog[] = $node[0].":".$node[1];
            $cache->addServer($node[0], $node[1]);
        }
        $this->log(LogLevel::INFO, "Cache [".count($cacheNodes)."]: ".implode(",", $cacheLog));

        $this->container->setInstance(Memcached::class, $cache);
    }

    /**
     * Prepare Broker and put it into the Container
     */
    protected function prepareBroker()
    {
        // Prepare Broker
        $queueNodes = $this->config->get('queue.nodes');

        $nodeDefault = [
            'host' => null,
            'port' => null,
            'password' => null,
            'connectionTimeout' => self::BROKER_CONNECT_TIMEOUT,
            'responseTimeout' => self::BROKER_RESPONSE_TIMEOUT,
        ];

        $credentials = [];
        $brokerLog = [];
        foreach ($queueNodes as $node) {
            $node = array_replace($nodeDefault, [
                'host' => $node[0],
                'port' => $node[1],
                'password' => $node[2] ?? null,
                'connectionTimeout' => self::BROKER_CONNECT_TIMEOUT,
                'responseTimeout' => self::BROKER_RESPONSE_TIMEOUT,
            ]);
            $brokerLog[] = $node['host'].":".$node['port'];

            $credential = $this->container->getArgs(Credentials::class, $node);
            $credentials[] = $credential;
        }

        $brokerClient = new Client($credentials);
        $brokerClient->getConnectionManager()->setPriorityStrategy(new NullPrioritizer());
        // Attempt to connect
        $attempt = 0;
        while (1) {
            try {
                /* @var \Disque\Connection\Node\Node $disqueNode */
                $disqueNode = $brokerClient->connect();
            } catch (ConnectionException $e) {
                if ($attempt > static::BROKER_MAX_RETRIES) {
                    $this->log(LogLevel::INFO, " failed to connect: {emsg}, giving up after {tries} attempts", [
                        'emsg' => $e->getMessage(),
                        'tries' => $attempt,
                    ]);
                    throw $e;
                }

                // Exponential backoff
                $delay = pow(2, $attempt) * self::BROKER_BACKOFF_FACTOR_MS;
                $delaySeconds = round($delay / 1000, 2);
                $this->log(LogLevel::INFO, " failed to connect: {emsg}, backoff for {sec} seconds", [
                    'emsg' => $e->getMessage(),
                    'sec' => $delaySeconds,
                ]);
                usleep($delay * 1000);

                $attempt++;
                continue;
            }

            // Connected
            break;
        }

        // Connected
        $this->log(LogLevel::INFO, "Brokers [".count($queueNodes)."]: connected to ".$disqueNode->getCredentials()->getAddress().", available: ".implode(",", $brokerLog));
        $this->container->setInstance(Client::class, $brokerClient);
    }

    protected function prepareAllocation()
    {
        $strategyClass = $this->config->get('queue.oversight.strategy');
        $this->container->rule(AllocationStrategyInterface::class);
        $this->container->setClass($strategyClass);

        $this->log(LogLevel::INFO, "Allocation Strategy: `{$strategyClass}``");
    }

    protected function prepareParser()
    {
        $parserClass = $this->config->get('queue.message.parser');
        $this->container->rule(ParserInterface::class);
        $this->container->setClass($parserClass);

        $this->log(LogLevel::INFO, "Parser `{$parserClass}`");
    }

    protected function startAddons()
    {
        $this->addonManager->startAddons($this->config->get('addons.active'));
    }
}
