<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Exception;

/**
 * Message Exception: UnknownJob
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @version 1.0
 */
class UnknownJobException extends QueueMessageException {

    /**
     * Get job payload name
     *
     * @return string
     */
    public function getJob(): string {
        return $this->getQueueMessage()->getPayloadType();
    }

}