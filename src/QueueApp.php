<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker;

use Exception;
use Garden\Cli\Args;
use Garden\Cli\Cli;
use Garden\Container\Container;
use Garden\Daemon\AppInterface;
use Garden\Daemon\ErrorHandler;
use Kaecyra\AppCommon\ConfigCollection;
use Kaecyra\AppCommon\Event\EventManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Vanilla\QueueWorker\Error\FatalErrorHandler;
use Vanilla\QueueWorker\Error\LogErrorHandler;
use Vanilla\QueueWorker\Event\EventAwareTrait;
use Vanilla\QueueWorker\Event\QueueStartWorkerEvent;
use Vanilla\QueueWorker\Log\LoggerBoilerTrait;
use Vanilla\QueueWorker\Worker\ProcessBootstrap;

/**
 * Payload Context
 *
 * Queue Worker is an asynchronous task running daemon for executing jobs from
 * message queues. This class oversees each process and runs either a ProductWorker
 * or a MaintenanceWorker when run() is called.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @version 2.0.0
 */
class QueueApp implements AppInterface
{
    use LoggerBoilerTrait;
    use EventAwareTrait;

    /**
     * Dependency Injection Container
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Commandline handler
     * @var Cli
     */
    protected $cli;

    /**
     * @var \Kaecyra\AppCommon\ConfigCollection
     */
    protected $config;

    /**
     * @var \Kaecyra\AppCommon\Event\EventManager
     */
    protected $eventManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Last oversight
     * @var int
     */
    protected $lastOversight = 0;

    /**
     * Worker slots and pids
     * @var array
     */
    protected $workers = [];

    /**
     * QueueApp constructor.
     *
     * @param \Garden\Container\Container $container
     * @param \Garden\Cli\Cli $cli
     * @param \Kaecyra\AppCommon\ConfigCollection $config
     * @param \Garden\Daemon\ErrorHandler $errorHandler
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Kaecyra\AppCommon\Event\EventManager $eventManager
     *
     * @throws \Garden\Container\ContainerException
     * @throws \Garden\Container\NotFoundException
     */
    public function __construct(
        Container $container,
        Cli $cli,
        ConfigCollection $config,
        ErrorHandler $errorHandler,
        LoggerInterface $logger,
        EventManager $eventManager
    ) {
        $this->container = $container;
        $this->cli = $cli;
        $this->config = $config;
        $this->logger = $logger;
        $this->eventManager = $eventManager;

        // Add logging error handler
        $logHandler = $this->container->get(LogErrorHandler::class);
        $errorHandler->addHandler([$logHandler, 'error'], E_ALL);

        // Add fatal error handler
        $fatalHandler = $this->container->get(FatalErrorHandler::class);
        $errorHandler->addHandler([$fatalHandler, 'error']);
    }

    /**
     * Check environment for app runtime compatibility
     *
     * Provide any custom CLI configuration, and check validity of configuration.
     *
     */
    public function preflight()
    {
        // MaintenanceWorker is outside the fleet
        $fleetSize = $this->config->get('daemon.fleet') + 1;

        $this->log(LogLevel::INFO, "Pre-flight checking [fleet:{$fleetSize}]");

        for ($i = 0; $i < $fleetSize; $i++) {
            $this->workers[$i] = null;
        }
    }

    /**
     * Initialize app and disable console logging
     *
     * This occurs in the main daemon process, prior to worker forking. No
     * connections should be established here, since this method's actions are
     * pre-worker forking, and will be shared to child processes.
     *
     * @param \Garden\Cli\Args $args
     *
     * @throws \Throwable
     */
    public function initialize(Args $args)
    {
        $this->log(LogLevel::INFO, "Initializing");

        // Remove echo logger
        $this->log(LogLevel::INFO, "Transitioning logger");
        $this->getLogger()->removeLogger('echo', false);
        $this->getLogger()->enableLogger('persist');

        $this->log(LogLevel::NOTICE, "Starting QueueApp");

        $this->fireEvent(new QueueStartWorkerEvent());
    }

    /**
     * Dismiss app
     *
     * This occurs in the main daemon process when all child workers have been
     * reaped and we're about to shut down.
     */
    public function dismiss()
    {
        // NOOP
    }

    /**
     * Get launch override permission
     *
     * Check whether we want to override fleet size limit to launch an additional
     * worker.
     *
     * @return bool
     */
    public function getLaunchOverride()
    {
        $oversightFrequency = $this->config->get('queue.oversight.frequency');

        if ((time() - $this->lastOversight) > $oversightFrequency) {
            return true;
        }
        return false;
    }

    /**
     * Get worker config
     *
     * This method runs just prior to a forking event and allows the payload
     * coordinator to provide the nascent worker with some config.
     *
     * @return array|bool
     */
    public function getWorkerConfig()
    {
        $slot = $this->getFreeSlot();

        if ($slot === false) {
            return false;
        }

        // Last slot is MaintenanceWorker slot
        if ($slot === (count($this->workers) - 1)) {
            if ($this->getLaunchOverride()) {
                $this->lastOversight = time();

                return [
                    'worker' => 'maintenance',
                    'class' => '\\Vanilla\\QueueWorker\\Worker\\MaintenanceWorker',
                ];
            } else {
                return false;
            }
        }

        return [
            'worker' => 'product',
            'class' => '\\Vanilla\\QueueWorker\\Worker\\ProductWorker',
            'slot' => $slot,
        ];
    }

    /**
     * Get first free available slot
     *
     * @return int
     */
    public function getFreeSlot(): int
    {
        foreach ($this->workers as $slot => $pid) {
            if (is_null($pid)) {
                return $slot;
            }
        }
        return false;
    }

    /**
     * Register spawned worker
     *
     * @param int $pid
     * @param string $realm
     * @param array $workerConfig
     *
     * @return bool
     */
    public function spawnedWorker($pid, /** @noinspection PhpUnusedParameterInspection */ $realm, $workerConfig): bool
    {
        $slot = val('slot', $workerConfig, false);
        if ($slot === false) {
            return false;
        }

        $this->workers[$slot] = $pid;
        return true;
    }

    /**
     * Unregister reaped worker
     *
     * @param int $pid
     * @param string $realm
     *
     * @return boolean
     */
    public function reapedWorker($pid, $realm): bool
    {
        $this->log(LogLevel::DEBUG, "Recovered worker in realm '{realm}' with pid '{pid}'", [
            'realm' => $realm,
            'pid' => $pid,
        ]);

        foreach ($this->workers as $slot => &$workerPid) {
            if ($pid === $workerPid) {
                $this->log(LogLevel::DEBUG, " worker slot {slot} freed", [
                    'slot' => $slot,
                ]);
                $workerPid = null;
                return true;
            }
        }
        return false;
    }

    /**
     * Run payload
     *
     * This method is the main program scope for the payload. Forking has already
     * been handled at this point, so this scope is confined to a single process.
     *
     * Returning from this function ends the process.
     *
     * @param array $workerConfig
     *
     * @throws \Disque\Connection\ConnectionException
     * @throws \Garden\Container\ContainerException
     * @throws \Garden\Container\NotFoundException
     * @throws Exception
     */

    public function run($workerConfig)
    {
        $workerSlot = $workerConfig['slot'];
        $workerClass = $workerConfig['class'];
        $workerDelay = random_int(200, 1000);

        usleep($workerDelay * 1000);
        $this->log(LogLevel::NOTICE, "Creating Worker [slot:{$workerSlot}][delayed: {$workerDelay}ms][type:{$workerClass}]");

        /* @var \Vanilla\QueueWorker\Worker\ProcessBootstrap $processBootstrap */
        $processBootstrap = $this->container->get(ProcessBootstrap::class);
        $processBootstrap->init();

        /** @var \Vanilla\QueueWorker\Worker\AbstractQueueWorker $worker */
        $worker = $this->container->get($workerClass);
        $worker->run($workerConfig);
    }
}
