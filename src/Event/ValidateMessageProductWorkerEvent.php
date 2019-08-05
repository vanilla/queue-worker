<?php
/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Event;

use Vanilla\QueueWorker\Message\Message;
use Vanilla\QueueWorker\Worker\ProductWorker;

/**
 * Class ValidateMessageProductWorkerEvent
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class ValidateMessageProductWorkerEvent extends WorkerEvent
{
    /**
     * @var \Vanilla\QueueWorker\Worker\ProductWorker
     */
    protected $worker;

    /**
     * @var \Vanilla\QueueWorker\Message\Message
     */
    protected $message;

    /**
     * ValidateMessageProductWorkerEvent constructor.
     *
     * @param \Vanilla\QueueWorker\Worker\ProductWorker $worker
     * @param \Vanilla\QueueWorker\Message\Message $message
     */
    public function __construct(ProductWorker $worker, Message $message)
    {
        $this->worker = $worker;
        $this->message = $message;
    }

    /**
     * @return \Vanilla\QueueWorker\Worker\ProductWorker
     */
    public function getWorker()
    {
        return $this->worker;
    }

    /**
     * @return \Vanilla\QueueWorker\Message\Message
     */
    public function getMessage()
    {
        return $this->message;
    }

}
