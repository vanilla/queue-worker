<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Exception;

use Vanilla\QueueWorker\Job\JobInterface;
use Vanilla\QueueWorker\Worker\WorkerStatus;

/**
 * Class RetryJobExhaustedWorkerException
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class RetryJobExhaustedWorkerException extends WorkerException
{
    /**
     * RetryJobExhaustedWorkerException constructor.
     *
     * @param \Vanilla\QueueWorker\Job\JobInterface|null $job
     * @param string $reason
     */
    public function __construct(JobInterface $job, string $reason = "")
    {
        parent::__construct(null, $job, $reason);
        $this->workerStatus = WorkerStatus::retryExhausted();
    }
}
