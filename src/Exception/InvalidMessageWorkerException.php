<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Exception;

use Vanilla\QueueWorker\Message\Message;
use Vanilla\QueueWorker\Worker\WorkerStatus;

/**
 * Class InvalidMessageWorkerException
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class InvalidMessageWorkerException extends WorkerException
{
    /**
     * InvalidMessageWorkerException constructor.
     *
     * @param \Vanilla\QueueWorker\Message\Message|null $workerMessage
     * @param string $reason
     */
    public function __construct(Message $workerMessage, string $reason = "")
    {
        parent::__construct($workerMessage, null, $reason);
        $this->workerStatus = WorkerStatus::invalidMessage();
    }
}
