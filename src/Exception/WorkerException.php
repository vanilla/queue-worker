<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Exception;

use Exception;
use Vanilla\QueueWorker\Job\JobInterface;
use Vanilla\QueueWorker\Message\Message;
use Vanilla\QueueWorker\Worker\WorkerStatus;

/**
 * Class WorkerException
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class WorkerException extends Exception
{

    /**
     * @var \Vanilla\QueueWorker\Message\Message|null
     */
    protected $workerMessage;

    /**
     * @var \Vanilla\QueueWorker\Worker\WorkerStatus
     */
    protected $workerStatus;

    /**
     * @var \Vanilla\QueueWorker\Job\JobInterface|null
     */
    protected $workerJob;

    /**
     * @var array
     */
    protected $payload;

    /**
     * WorkerException constructor.
     *
     * @param \Vanilla\QueueWorker\Message\Message|null $workerMessage
     * @param \Vanilla\QueueWorker\Job\JobInterface|null $workerJob
     * @param string $reason
     * @param array|null $payload
     */
    public function __construct(Message $workerMessage = null, JobInterface $workerJob = null, string $reason = "", array $payload = [])
    {
        parent::__construct();
        $this->workerStatus = WorkerStatus::unknown();
        $this->workerMessage = $workerMessage;
        $this->workerJob = $workerJob;
        $this->payload = $payload;
        $this->message = $reason;
    }

    /**
     * Get queue message
     *
     * @return Message
     */
    public function getWorkerMessage(): ?Message
    {
        return $this->workerMessage;
    }

    /**
     * @return \Vanilla\QueueWorker\Job\JobInterface|null
     */
    public function getWorkerJob(): ?JobInterface
    {
        return $this->workerJob;
    }

    /**
     * @return \Vanilla\QueueWorker\Worker\WorkerStatus
     */
    public function getWorkerStatus(): WorkerStatus
    {
        return $this->workerStatus;
    }

    /**
     * Getter of payload.
     *
     * @return array
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }
}
