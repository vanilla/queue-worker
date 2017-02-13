<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue;

use Psr\Log\LogLevel;

class ProductWorker extends AbstractQueueWorker {

    /**
     * Worker slot
     * @var int
     */
    protected $slot;

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
     * Slot queues
     * @var string
     */
    protected $slotQueues;


    public function __construct(Container $di, Config $config) {
        parent::__construct($di, $config);

        // Prepare sync

        $this->lastSync = 0;
        $this->syncFrequency = $this->config->get('queue.oversight.adjust', 5);

        // Prepare execution environment

        $this->iterations = $this->config->get('process.max_requests', 0);
        $this->retries = $this->config->get('process.max_retries', 0);
    }

    /**
     * Check if queue is a ready to retrieve jobs
     *
     * @return bool
     */
    public function isReady() {
        return $this->iterations > 0;
    }

    /**
     * Update queue priority
     *
     * Each worker is assigned a "slot" by the main queue process. This slot is
     * associated with a set of queues. Every queue.oversight.adjust seconds the
     * worker re-queries its list of queues from memcache. This allows the queue
     * to automatically adjust itself to changing queue backlogs.
     *
     * This method queries the distribution array and updates the queue list based
     * on slot number.
     */
    public function updateDistribution() {
        if (!$this->slotQueues || (time() - $this->lastSync) > $this->syncFrequency) {
            // Sync
            $distribution = $this->cache->get(QueueWorker::QUEUE_DISTRIBUTION_KEY);
            if (!$distribution) {
                $distribution = [];
            }

            $update = val($this->slot, $distribution, $this->getQueues('pull'));
            if ($this->slotQueues != $update) {
                $this->log(LogLevel::INFO, " updated queues for slot {$this->slot}: {$update}");
                $this->slotQueues = $update;
            }
        }
    }

    /**
     * Run worker instance
     *
     * This method runs the product queue processor worker.
     */
    public function run($workerConfig) {

        $this->log(LogLevel::INFO, "Product Worker started");

        // Prepare slot and initial distribution

        $this->slot = $workerConfig['slot'];

        // Get jobs. Do 'em.
        while ($this->isReady()) {

            $this->updateDistribution();

            $message = $this->queue->getJob($this->slotQueues);
            sleep(1);

            $this->iterations--;
        }
    }

}