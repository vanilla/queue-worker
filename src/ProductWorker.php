<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue;

use Psr\Log\LogLevel;

class ProductWorker extends QueueWorker {

    /**
     * Check if queue is a ready to retrieve jobs
     *
     * @return bool
     */
    public function isReady() {
        return $this->iterations > 0;
    }

    /**
     * Run worker instance
     *
     * This method runs the product queue processor worker.
     */
    public function run() {

        $this->log(LogLevel::INFO, "Product Worker started");

        // Prepare limits

        $this->iterations = $this->config->get('process.max_requests', 0);
        $this->retries = $this->config->get('process.max_retries', 0);

        // Get jobs. Do 'em.
        while ($this->isReady()) {

            $message = $this->queue->getJob($queues);

            $this->iterations--;
        }
    }

}