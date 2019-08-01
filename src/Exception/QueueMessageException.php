<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Exception;

use Vanilla\QueueWorker\Job\JobStatus;
use Vanilla\QueueWorker\Message\Message;

/**
 * Message Exception: UnknownJob
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @version 1.0
 */
class QueueMessageException extends \Exception {
    protected const JOB_STATUS = JobStatus::UNKNOWN;

    protected $queueMessage;

    /**
     * Construct
     *
     * @param Message $message
     * @param string $error
     */
    public function __construct(Message $message = null, string $error = "") {
        parent::__construct($error);
        $this->setQueueMessage($message);
    }

    /**
     * Get queue message
     *
     * @return Message
     */
    public function getQueueMessage(): ?Message {
        return $this->queueMessage;
    }

    /**
     * Set queue message
     *
     * @param Message $message
     */
    public function setQueueMessage(Message $message = null) {
        $this->queueMessage = $message;
    }


    public function getStatus() {
        return static::JOB_STATUS;
    }
}
