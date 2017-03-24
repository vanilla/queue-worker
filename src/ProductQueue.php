<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker;

use Vanilla\QueueWorker\Allocation\AllocationStrategyInterface;
use Vanilla\QueueWorker\Log\LoggerBoilerTrait;
use Vanilla\QueueWorker\Addon\AddonManager;
use Vanilla\QueueWorker\Message\Parser\ParserInterface;

use Vanilla\QueueWorker\Error\FatalErrorHandler;
use Vanilla\QueueWorker\Error\LogErrorHandler;

use Garden\Daemon\AppInterface;
use Garden\Daemon\ErrorHandler;
use Garden\Container\Container;
use Garden\Cli\Cli;
use Garden\Cli\Args;

use Kaecyra\AppCommon\AbstractConfig;
use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

/**
 * Payload Context
 *
 * ProductQueue is the asynchronous task running daemon for Vanilla's Hosted
 * environment. This class oversees each process and runs either a ProductWorker
 * or a MaintenanceWorker when run() is called.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
class ProductQueue implements AppInterface, LoggerAwareInterface, EventAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use EventAwareTrait;

    /**
     * Dependency Injection Container
     * @var \Garden\Container\Container
     */
    protected $di;

    /**
     * Commandline handler
     * @var Cli
     */
    protected $cli;

    /**
     * Commandline args
     * @var Args
     */
    protected $args;

    /**
     * App configuration
     * @var \Kaecyra\AppCommon\Config
     */
    protected $config;

    /**
     * Addon manager
     * @var \Vanilla\QueueWorker\Addon\AddonManager
     */
    protected $addons;

    /**
     * Last oversight
     * @var int
     */
    protected $lastOversight;

    /**
     * Worker slots and pids
     * @var array
     */
    protected $workers;

    /**
     * Construct app
     *
     * @param Container $di
     * @param Cli $cli
     * @param AbstractConfig $config
     * @param AddonManager $addons
     * @param ErrorHandler $errorHandler
     */
    public function __construct(
        Container $di,
        Cli $cli,
        AbstractConfig $config,
        AddonManager $addons,
        ErrorHandler $errorHandler
    ) {
        $this->di = $di;
        $this->cli = $cli;
        $this->config = $config;
        $this->addons = $addons;

        // Set worker allocation oversight strategy
        $strategyClass = $this->config->get('queue.oversight.strategy');
        $this->di->rule(AllocationStrategyInterface::class);
        $this->di->setClass($strategyClass);

        // Set job parser
        $parserClass = $this->config->get('queue.message.parser');
        $this->di->rule(ParserInterface::class);
        $this->di->setClass($parserClass);

        // Add logging error handler
        $logHandler = $this->di->get(LogErrorHandler::class);
        $errorHandler->addHandler([$logHandler, 'error'], E_ALL);

        // Add fatal error handler
        $fatalHandler = $this->di->get(FatalErrorHandler::class);
        $errorHandler->addHandler([$fatalHandler, 'error']);

        $this->cleanEnvironment();
    }

    /**
     * Clean up environment
     *
     */
    public function cleanEnvironment() {
        $this->lastOversight = 0;
        $this->workers = [];
    }

    /**
     * Check environment for app runtime compatibility
     *
     * Provide any custom CLI configuration, and check validity of configuration.
     *
     */
    public function preflight() {
        $this->log(LogLevel::INFO, " preflight checking");

        $fleetSize = $this->config->get('daemon.fleet');
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
     * @param Args $args
     */
    public function initialize(Args $args) {
        $this->log(LogLevel::INFO, " initializing");

        // Remove echo logger

        $this->log(LogLevel::INFO, " transitioning logger");
        $this->getLogger()->removeLogger('echo', false);
        $this->getLogger()->enableLogger('persist');

        $this->addons->startAddons($this->config->get('addons.active'));

        $this->fire('queueStart');
    }

    /**
     * Dismiss app
     *
     * This occurs in the main daemon process when all child workers have been
     * reaped and we're about to shut down.
     */
    public function dismiss() {
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
    public function getLaunchOverride() {
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
    public function getWorkerConfig() {

        if ($this->getLaunchOverride()) {
            $this->lastOversight = time();

            return [
                'worker'    => 'maintenance',
                'class'     => '\\Vanilla\\QueueWorker\\Worker\\MaintenanceWorker'
            ];
        }

        $slot = $this->getFreeSlot();
        if ($slot === false) {
            return false;
        }

        return [
            'worker'    => 'product',
            'class'     => '\\Vanilla\\QueueWorker\\Worker\\ProductWorker',
            'slot'      => $slot
        ];
    }

    /**
     * Get first free available slot
     *
     * @return int
     */
    public function getFreeSlot(): int {
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
     * @return bool
     */
    public function spawnedWorker($pid, $realm, $workerConfig): bool {
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
     * @return boolean
     */
    public function reapedWorker($pid, $realm): bool {
        $this->log(LogLevel::DEBUG, "Recovered worker in realm '{realm}' with pid '{pid}'", [
            'realm' => $realm,
            'pid' => $pid
        ]);

        foreach ($this->workers as $slot => &$workerPid) {
            $this->log(LogLevel::DEBUG, " checking worker: {pid}", [
                'pid' => $workerPid
            ]);

            if ($pid === $workerPid) {
                $this->log(LogLevel::DEBUG, " worker slot {slot} freed", [
                    'slot' => $slot
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
     */
    public function run($workerConfig) {

        // Clean worker environment
        $this->cleanEnvironment();

        $workerClass = $workerConfig['class'];

        // Prepare worker environment
        $this->worker = $this->di->get($workerClass);
        $this->worker->run($workerConfig);
    }

}