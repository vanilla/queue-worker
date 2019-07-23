<?php
/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Event;

use Vanilla\QueueWorker\Worker\MaintenanceWorker;

/**
 * Class MaintenanceCompleteEvent
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class MaintenanceCompleteEvent extends WorkerEvent
{
    /**
     * @var \Vanilla\QueueWorker\Worker\MaintenanceWorker
     */
    protected $worker;

    /**
     * MaintenanceCompleteEvent constructor.
     *
     * @param \Vanilla\QueueWorker\Worker\MaintenanceWorker $worker
     */
    public function __construct(MaintenanceWorker $worker)
    {
        $this->worker = $worker;
    }

    /**
     * @return \Vanilla\QueueWorker\Worker\MaintenanceWorker
     */
    public function getWorker()
    {
        return $this->worker;
    }
}
