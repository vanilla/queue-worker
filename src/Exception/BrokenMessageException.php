<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Exception;

use Vanilla\QueueWorker\Job\JobStatus;

/**
 * Message Exception: BrokenMessage
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @version 1.0
 */
class BrokenMessageException extends QueueMessageException {
    protected const JOB_STATUS = JobStatus::MISMATCH;

}
