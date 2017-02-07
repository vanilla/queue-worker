<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue;

use Psr\Log\LogLevel;

class MaintenanceWorker extends QueueWorker {

    /**
     * Run worker instance
     *
     * This method runs the queue maintenance worker.
     */
    public function run() {

        $this->log(LogLevel::INFO, "Maintenance Worker started");

    }

}