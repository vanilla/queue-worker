<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Allocation;

/**
 * Allocation strategy interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
interface AllocationStrategyInterface {

    public function allocate(int $workers, array $queues): array;

}