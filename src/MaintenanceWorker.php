<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue;

use Vanilla\ProductQueue\Allocation\AllocationStrategyInterface;

use Psr\Log\LogLevel;

class MaintenanceWorker extends AbstractQueueWorker {

    /**
     * Queue config
     * @var array
     */
    protected $queues;

    /**
     * Run worker instance
     *
     * This method runs the queue maintenance worker.
     */
    public function run($workerConfig) {

        $this->log(LogLevel::INFO, "Maintenance Worker started");

        // Gather queue backlog information
        $queues = $this->getQueues('full');
        foreach ($queues as $priority => &$queue) {
            $queue['backlog'] = $this->queue->qlen($queue['name']);
            $this->log(LogLevel::INFO, " queue backlog {$queue['name']}: {$queue['backlog']}");
        }

        $strategy = $this->di->get(AllocationStrategyInterface::class);
        $this->log(LogLevel::INFO, " using strategy ".get_class($strategy));

        // Run allocation
        $workers = $this->config->get('daemon.fleet');
        $distribution = $strategy->allocate($workers, $queues);
        $this->cache->set(self::QUEUE_DISTRIBUTION_KEY, $distribution);

        // Announce allocation
        $this->log(LogLevel::INFO, " allocation");
        foreach ($distribution as $slot => $queueNames) {
            $this->log(LogLevel::INFO, sprintf("  slot %3d: %s", $slot, $queueNames));
        }

    }

}