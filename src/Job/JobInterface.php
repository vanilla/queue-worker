<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Job;

use Vanilla\QueueWorker\Exception\JobRetryException;
use Vanilla\QueueWorker\Message\Message;
use Vanilla\QueueWorker\Worker\WorkerStatus;

/**
 * Queue job interface.
 *
 * Interface for a runnable job payload.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 */
interface JobInterface
{
    /**
     * Set message handling status
     *
     * @param WorkerStatus $status
     */
    public function setStatus(WorkerStatus $status);

    /**
     * Get message handling status
     *
     * @return WorkerStatus
     */
    public function getStatus(): WorkerStatus;

    /**
     * Set message
     *
     * @param \Vanilla\QueueWorker\Message\Message $message
     *
     */
    public function setMessage(Message $message);

    /**
     * @return \Vanilla\QueueWorker\Message\Message
     */
    public function getMessage(): Message;

    /**
     * Get job data
     *
     * @return array
     */
    public function getBody(): array;

    /**
     * Get job data by key
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getBodyKey(string $key, $default = null);

    /**
     * Get job headers
     *
     * @return array
     */
    public function getHeader(): array;

    /**
     * Get job header
     *
     * @param $key
     * @param $default
     *
     * @return mixed
     */
    public function getHeaderKey(string $key, $default = null);

    /**
     * Get broker id
     *
     * @return string
     */
    public function getBrokerId(): string;

    /**
     * Get the job name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Run job payload
     *
     * @throws JobRetryException
     */
    public function run();

    /**
     * Setup job payload for execution
     *
     */
    public function setup();

    /**
     * Clean up after job execution
     *
     */
    public function teardown();
}
