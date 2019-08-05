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
 * Queue job interface.
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
     * @throws JobRetryException
     */
    abstract public function run();

    /**
     * Get job ID
     *
     * @return string
     */
    public function getID(): string
    {
        return $this->getMessage()->getID();
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
     * Get message handling status
     *
     * @return WorkerStatus
     */
    public function getStatus(): WorkerStatus
    {
        return $this->status;
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
     * Get job data
     *
     * @return array
     */
    public function getBody(): array
    {
        return $this->getMessage()->getBody();
    }

    /**
     * Get key from body
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->getMessage()->getBodyKey($key, $default);
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
         * If the Job doesn't set a JobStatus in the run method, we set the Job as Complete
         * Otherwise, we respect the run method wishes
         */
        if ($this->getStatus()->is(WorkerStatus::progress())) {
            $this->setStatus(WorkerStatus::complete());
        }
    }

    /**
     * Get key from header
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getHeader(string $key, $default = null)
    {
        return $this->getMessage()->getHeaderKey($key, $default);
    }

    /**
     * Get job header
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->getMessage()->getHeaders();
    }

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
        return $this->message;
    }
}
