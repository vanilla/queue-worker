<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Allocation;

/**
 * Allocation strategy interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 */
interface AllocationStrategyInterface
{
    /**
     * @param int $workers
     * @param array $queues
     *
     * @return array
     */
    public function allocate(int $workers, array $queues): array;
}
