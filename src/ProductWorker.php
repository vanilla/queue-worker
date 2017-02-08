<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue;

use Psr\Log\LogLevel;

class ProductWorker extends QueueWorker {

    /**
     * Worker slot
     * @var int
     */
    protected $slot;

    /**
     * List of queues to read from, in order
     * @var string
     */
    protected $queues;

    /**
     * How often to sync with distribution array
     * @var int
     */
    protected $syncFrequency;

    /**
     * Last sync with distribution array
     * @var int
     */
    protected $lastSync;

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
     * Check if queue is a ready to retrieve jobs
     *
     * @return bool
     */
    public function isReady() {
        return $this->iterations > 0;
    }

    /**
     * Run worker instance
     *
     * This method runs the product queue processor worker.
     */
    public function run($workerConfig) {

        $this->log(LogLevel::INFO, "Product Worker started");

        // Prepare slot and sync

        $this->slot = $workerConfig['slot'];
        $this->lastSync = 0;
        $this->syncFrequency = $this->config->get('queue.oversight.adjust', 5);

        // Prepare limits

        $this->iterations = $this->config->get('process.max_requests', 0);
        $this->retries = $this->config->get('process.max_retries', 0);

        // Get jobs. Do 'em.
        while ($this->isReady()) {

            if ((time() - $this->lastSync) > $this->syncFrequency) {
                // Sync
                $distribution = $this->cache->get(QueueWorker::QUEUE_DISTRIBUTION_KEY);
                if (!$distribution) {
                    $distribution = [];
                }
                $this->queues = val($this->slot, $distribution, $this->defaultQueue);
            }

            //$message = $this->queue->getJob($this->queues);
            sleep(1);

            $this->iterations--;
        }
    }

}