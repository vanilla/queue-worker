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

        $this->log(LogLevel::NOTICE, "Maintenance Worker started");

        // Connect to queues and cache
        $this->prepareWorker();

        // Gather queue backlog information
        $queues = $this->getQueues('full');
        foreach ($queues as $priority => &$queue) {
            $queue['backlog'] = $this->queue->qlen($queue['name']);
            $this->log(LogLevel::NOTICE, " queue backlog {$queue['name']}: {$queue['backlog']}");
        }

        $strategy = $this->di->get(AllocationStrategyInterface::class);
        $this->log(LogLevel::NOTICE, " using strategy {class}",[
            'class' => get_class($strategy)
        ]);

        // Run allocation
        $workers = $this->config->get('daemon.fleet');
        $distribution = $strategy->allocate($workers, $queues);
        $this->cache->set(AbstractQueueWorker::QUEUE_DISTRIBUTION_KEY, $distribution);

        // Announce allocation
        $this->log(LogLevel::NOTICE, " allocation");
        foreach ($distribution as $slot => $queueNames) {
            $this->log(LogLevel::NOTICE, sprintf("  slot %3d: %s", $slot, $queueNames));
        }

        $this->log(LogLevel::NOTICE, " maintenance complete");

    }

}