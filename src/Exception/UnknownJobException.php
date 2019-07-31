<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Exception;

use Vanilla\QueueWorker\Job\JobStatus;

/**
 * Message Exception: UnknownJob
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @version 1.0
 */
class UnknownJobException extends QueueMessageException {
    protected const JOB_STATUS = JobStatus::MISMATCH;

    /**
     * Get job payload name
     *
     * @return string
     */
    public function getJob(): string {
        return $this->getQueueMessage()->getPayloadType() ?? "N/A";
    }
}
