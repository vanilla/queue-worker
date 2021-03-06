<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Exception;

/**
 * Message Exception: MissingData
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @version 1.0
 */
class MissingDataException extends QueueMessageException {

    /**
     * Get job payload name
     *
     * @return string
     */
    public function getJob(): string {
        return $this->getQueueMessage()->getPayloadType();
    }

}