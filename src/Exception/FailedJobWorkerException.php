<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Exception;

use Vanilla\QueueWorker\Job\JobInterface;
use Vanilla\QueueWorker\Worker\WorkerStatus;

/**
 * Class FailedWorkerException
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class FailedJobWorkerException extends WorkerException
{
    /**
     * FailedJobWorkerException constructor.
     *
     * @param \Vanilla\QueueWorker\Job\JobInterface $job
     * @param string $reason
     */
    public function __construct(JobInterface $job, string $reason = "")
    {
        parent::__construct(null, $job, $reason);
        $this->workerStatus = WorkerStatus::failed();
    }
}
