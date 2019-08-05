<?php
/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Event;

use Vanilla\QueueWorker\Worker\ProductWorker;

/**
 * Class WorkerReadyWorkerEvent
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class WorkerReadyWorkerEvent extends WorkerEvent
{
    /**
     * @var \Vanilla\QueueWorker\Worker\ProductWorker
     */
    protected $worker;

    /**
     * WorkerReadyWorkerEvent constructor.
     *
     * @param \Vanilla\QueueWorker\Worker\ProductWorker $worker
     */
    public function __construct(ProductWorker $worker)
    {
        $this->worker = $worker;
    }

    /**
     * @return \Vanilla\QueueWorker\Worker\ProductWorker
     */
    public function getWorker()
    {
        return $this->worker;
    }
}
