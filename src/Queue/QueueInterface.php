<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Queue;

/**
 * Queue provider interface. Allows driver-based queues.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
interface QueueInterface {

    public function __construct(array $config);

}