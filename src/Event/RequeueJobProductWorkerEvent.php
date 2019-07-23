<?php
/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Event;

use Vanilla\QueueWorker\Exception\JobRetryException;
use Vanilla\QueueWorker\Job\JobInterface;
use Vanilla\QueueWorker\Worker\ProductWorker;

/**
 * Class RequeueJobProductWorkerEvent
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class RequeueJobProductWorkerEvent extends WorkerEvent
{
    /**
     * @var \Vanilla\QueueWorker\Worker\ProductWorker
     */
    protected $worker;

    /**
     * @var \Vanilla\QueueWorker\Job\JobInterface
     */
    protected $job;

    /**
     * @var \Vanilla\QueueWorker\Exception\JobRetryException
     */
    protected $jobRetryException;

    /**
     * RequeueJobProductWorkerEvent constructor.
     *
     * @param \Vanilla\QueueWorker\Worker\ProductWorker $worker
     * @param \Vanilla\QueueWorker\Job\JobInterface $job
     * @param \Vanilla\QueueWorker\Exception\JobRetryException $jobRetryException
     */
    public function __construct(ProductWorker $worker, JobInterface $job, JobRetryException $jobRetryException)
    {
        $this->worker = $worker;
        $this->job = $job;
        $this->jobRetryException = $jobRetryException;
    }

    /**
     * @return \Vanilla\QueueWorker\Worker\ProductWorker
     */
    public function getWorker()
    {
        return $this->worker;
    }

    /**
     * @return \Vanilla\QueueWorker\Job\JobInterface
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * @return int|null
     */
    public function getDelay(): ?int
    {
        return $this->jobRetryException->getDelay();
    }

    /**
     * @return string
     */
    public function getReason(): string
    {
        return $this->jobRetryException->getMessage();
    }

}
