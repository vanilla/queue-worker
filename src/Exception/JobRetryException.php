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
class JobRetryException extends QueueMessageException {
    protected const JOB_STATUS = JobStatus::ABANDONED;

    /* @var int */
    private $delay;

    /**
     * JobRetryException constructor.
     *
     * @param int|null $delay
     * @param string|null $reason
     */
    public function __construct(int $delay = null, string $reason = null) {
        parent::__construct(null, $reason ?? "");
        $this->delay = $delay;
    }

    /**
     * @return int|null
     */
    public function getDelay(): ?int {
        return $this->delay;
    }
}
