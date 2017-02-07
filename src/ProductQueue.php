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
use Kaecyra\AppCommon\Store;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use Vanilla\ProductQueue\Log\LoggerBoilerTrait;


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
     * App transient storage
     * @var \Kaecyra\AppCommon\Store
     */
    protected $store;

    /**
     * Construct app
     *
     * @param Container $di
     */
    public function __construct(Container $di) {
        $this->di = $di;

        $this->di->rule(QueueWorker::class);
        $this->di->addCall('prepareWorker');
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
     */
    public function initialize() {
        $this->log(LogLevel::INFO, "Application initializing");

        // Remove echo logger

        $this->log(LogLevel::INFO, " transitioning logger");
        $this->getLogger()->removeLogger('echo', false);
        $this->getLogger()->enableLogger('persist');

        $this->log(LogLevel::INFO, "Application initialized");
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
        $lastOversight = $this->store->get('worker.oversight', 0);

        if ((time() - $lastOversight) > $oversightFrequency) {
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
            $this->store->set('worker.oversight', time());
            return [
                'worker'    => 'maintenance',
                'class'     => MaintenanceWorker::class
            ];
        }

        return [
            'worker'    => 'product',
            'class'     => ProductWorker::class
        ];
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
        // Erase store in worker
        $this->store->flush();

        $workerClass = $workerConfig['class'];

        // Prepare worker environment
        $this->worker = $this->di->get($workerClass);
        $this->worker->run($workerConfig);
    }

}