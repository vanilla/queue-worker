<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Allocation;

/**
 * Hybrid Allocation Strategy
 *
 * Allocates minimum workers to each queue according to config. Allocates the
 * remaining workers as catch-all, but taking jobs from higher priority queues
 * first.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
class HybridStrategy implements AllocationStrategyInterface {

    /**
     * Allocate workers using hybrid strategy
     *
     * @param int $workers
     * @param array $queues
     * @return array
     */
    public function allocate(int $workers, array $queues): array {
        $availableWorkers = $workers;

        $distribution = [];
        $slot = -1;
        $hybrid = [];

        foreach ($queues as &$queue) {
            $queueName = $queue['name'];
            $hybrid[] = $queueName;

            // Allocate minimum workers
            for ($i = 1; $i <= $queue['min']; $i++) {
                $slot++;
                $distribution[$slot] = $queueName;
            }

            $availableWorkers -= $queue['min'];
        }

        // Leftover workers are multipurpose and take jobs from any queue but in order of priority
        $hybrid = implode(' ', $hybrid);
        for ($i = 1; $i <= $availableWorkers; $i++) {
            $slot++;
            $distribution[$slot] = $hybrid;
        }

        return $distribution;
    }
}