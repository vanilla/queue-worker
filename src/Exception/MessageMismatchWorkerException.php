<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Exception;

use Vanilla\QueueWorker\Message\Message;
use Vanilla\QueueWorker\Worker\WorkerStatus;

/**
 * Class MessageMismatchWorkerException
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class MessageMismatchWorkerException extends WorkerException
{
    /**
     * MessageMismatchWorkerException constructor.
     *
     * @param \Vanilla\QueueWorker\Message\Message|null $workerMessage
     * @param string $reason
     */
    public function __construct(Message $workerMessage, string $reason = "")
    {
        parent::__construct($workerMessage, null, $reason);
        $this->workerStatus = WorkerStatus::mismatch();
    }
}
