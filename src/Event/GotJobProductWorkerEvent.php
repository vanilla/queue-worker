<?php
/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Event;

use Vanilla\QueueWorker\Job\JobInterface;
use Vanilla\QueueWorker\Worker\ProductWorker;

/**
 * Class GotJobProductWorkerEvent
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class GotJobProductWorkerEvent extends WorkerEvent
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
     * GotJobProductWorkerEvent constructor.
     *
     * @param \Vanilla\QueueWorker\Worker\ProductWorker $worker
     * @param \Vanilla\QueueWorker\Job\JobInterface $job
     */
    public function __construct(ProductWorker $worker, JobInterface $job)
    {
        $this->worker = $worker;
        $this->job = $job;
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

}
