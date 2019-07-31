<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Exception;

use Vanilla\QueueWorker\Job\JobStatus;

/**
 * Class JobRetryException.
 */
class JobRetryExhaustedException extends QueueMessageException {
    protected const JOB_STATUS = JobStatus::RETRY_FAILED;

    /**
     * JobRetryExhaustedException constructor.
     *
     * @param null $reason
     */
    public function __construct($reason = null) {
        parent::__construct(null, $reason ?? "");
    }
}
