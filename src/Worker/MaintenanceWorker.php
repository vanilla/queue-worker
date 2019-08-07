<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Worker;

use Psr\Log\LogLevel;
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
     * @throws \Throwable
     */
    public function run($workerConfig)
    {
        $this->log(LogLevel::NOTICE, "Maintenance Worker started");

        // Gather queue backlog information
        $queues = $this->getQueues('full');
        foreach ($queues as $priority => &$queue) {
            try {
                $queue['backlog'] = $this->getBrokerClient()->qlen($queue['name']);
            } catch (Exception $ex) {
                $this->log(LogLevel::ERROR, print_r($ex, true));
                continue;
            }
            $this->log(LogLevel::NOTICE, " queue backlog ({$queue['name']}): {$queue['backlog']}");
        }

        // Run allocation
        $workers = $this->getConfig()->get('daemon.fleet');
        $distribution = $this->getAllocationStrategy()->allocate($workers, $queues);
        $this->getCache()->set(AbstractQueueWorker::QUEUE_DISTRIBUTION_KEY, $distribution);

        // Announce allocation
        $this->log(LogLevel::INFO, " allocation");
        foreach ($distribution as $slot => $queueNames) {
            $this->log(LogLevel::INFO, sprintf("  slot %3d: %s", $slot, $queueNames));
        }

        $this->fireEvent(new MaintenanceCompleteEvent($this));
        $this->log(LogLevel::NOTICE, "Maintenance Worker finished");
    }
}
