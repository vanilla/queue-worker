<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Job;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Kaecyra\AppCommon\Log\LoggerBoilerTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Vanilla\QueueWorker\Exception\JobRetryException;
use Vanilla\QueueWorker\Message\Message;
use Vanilla\QueueWorker\Worker\WorkerStatus;

/**
 * AbstractJob interface.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 */
abstract class AbstractJob implements JobInterface, LoggerAwareInterface, EventAwareInterface
{
    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use EventAwareTrait;

    /**
     * @var Message
     */
    protected $message;

    /**
     * Job execution status
     *
     * @var WorkerStatus
     */
    protected $status;

    /**
     * @param \Vanilla\QueueWorker\Message\Message $message
     */
    public function setMessage(Message $message)
    {
        $this->message = $message;
    }

    /**
     * @return \Vanilla\QueueWorker\Message\Message
     */
    public function getMessage(): Message
    {
        return ($this->message === null) ? new Message([], []) : $this->message;
    }

    /**
     * Set message handling status
     *
     * @param WorkerStatus $status
     */
    public function setStatus(WorkerStatus $status)
    {
        $this->status = $status;
    }

    /**
     * Get message handling status
     *
     * @return WorkerStatus
     */
    public function getStatus(): WorkerStatus
    {
        return ($this->status === null) ? WorkerStatus::unknown() : $this->status;
    }

    /**
     * Get broker id
     *
     * @return string
     */
    public function getBrokerId(): string
    {
        return $this->getMessage()->getBrokerId();
    }

    /**
     * Get job name
     *
     * @return string
     */
    public function getName(): string
    {
        return static::class;
    }

    /**
     * Get message body
     *
     * @return array
     */
    public function getBody(): array
    {
        return $this->getMessage()->getBody();
    }

    /**
     * Get key from message body
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getBodyKey(string $key, $default = null)
    {
        return $this->getMessage()->getBodyKey($key, $default);
    }

    /**
     * Get key from message header
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getHeaderKey(string $key, $default = null)
    {
        return $this->getMessage()->getHeaderKey($key, $default);
    }

    /**
     * Get message header
     *
     * @return array
     */
    public function getHeader(): array
    {
        return $this->getMessage()->getHeader();
    }

    /**
     * NOOP Setup
     *
     */
    public function setup()
    {
        $this->setStatus(WorkerStatus::progress());
    }

    /**
     * NOOP Teardown
     *
     */
    public function teardown()
    {
        /**
         * If the Job doesn't set a WorkerStatus in the run method, we set the WorkerStatus as Complete
         * Otherwise, we respect the run method wishes
         */
        if ($this->getStatus()->is(WorkerStatus::progress())) {
            $this->setStatus(WorkerStatus::complete());
        }
    }

    /**
     * @throws JobRetryException
     */
    abstract public function run();
}
