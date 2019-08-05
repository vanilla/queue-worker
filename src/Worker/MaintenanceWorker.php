<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Worker;

use Psr\Log\LogLevel;
use Vanilla\QueueWorker\Allocation\AllocationStrategyInterface;
use Vanilla\QueueWorker\Event\MaintenanceCompleteEvent;

/**
 * Maintenance Worker
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 */
class MaintenanceWorker extends AbstractQueueWorker
{
    /**
     * Run worker instance
     *
     * This method runs the queue maintenance worker.
     *
     * @param mixed $workerConfig
     *
     * @throws \Disque\Connection\ConnectionException
     * @throws \Throwable
     */
    public function run($workerConfig)
    {
        $this->log(LogLevel::NOTICE, "Maintenance Worker started");

        // Connect to queues and cache
        $this->prepareWorker(1);

        // Gather queue backlog information
        $queues = $this->getQueues('full');
        foreach ($queues as $priority => &$queue) {
            try {
                $queue['backlog'] = $this->getDisqueClient()->qlen($queue['name']);
            } catch (Exception $ex) {
                $this->log(LogLevel::ERROR, print_r($ex, true));
                continue;
            }
            $this->log(LogLevel::NOTICE, " queue backlog ({$queue['name']}): {$queue['backlog']}");
        }

        $strategy = $this->container->get(AllocationStrategyInterface::class);
        $this->log(LogLevel::INFO, " using strategy {class}", [
            'class' => get_class($strategy),
        ]);

        // Run allocation
        $workers = $this->config->get('daemon.fleet');
        $distribution = $strategy->allocate($workers, $queues);
        $this->getCache()->set(AbstractQueueWorker::QUEUE_DISTRIBUTION_KEY, $distribution);

        // Announce allocation
        $this->log(LogLevel::INFO, " allocation");
        foreach ($distribution as $slot => $queueNames) {
            $this->log(LogLevel::INFO, sprintf("  slot %3d: %s", $slot, $queueNames));
        }

        $this->fireEvent(new MaintenanceCompleteEvent($this));
    }
}
