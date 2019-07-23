<?php
/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Event;

use Vanilla\QueueWorker\Exception\WorkerException;
use Vanilla\QueueWorker\Worker\ProductWorker;

/**
 * Class FailedIterationProductWorkerEvent
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class FailedIterationProductWorkerEvent extends WorkerEvent
{
    /**
     * @var \Vanilla\QueueWorker\Worker\ProductWorker
     */
    protected $worker;

    /**
     * @var \Vanilla\QueueWorker\Exception\WorkerException
     */
    protected $workerException;

    /**
     * FailedIterationProductWorkerEvent constructor.
     *
     * @param \Vanilla\QueueWorker\Worker\ProductWorker $worker
     * @param \Vanilla\QueueWorker\Exception\WorkerException $workerException
     */
    public function __construct(ProductWorker $worker, WorkerException $workerException)
    {
        $this->worker = $worker;
        $this->workerException = $workerException;
    }

    /**
     * @return \Vanilla\QueueWorker\Worker\ProductWorker
     */
    public function getWorker()
    {
        return $this->worker;
    }

    /**
     * @return \Vanilla\QueueWorker\Exception\WorkerException
     */
    public function getWorkerException()
    {
        return $this->workerException;
    }
}
