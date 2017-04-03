<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Job;

use Vanilla\QueueWorker\Log\LoggerBoilerTrait;

use Garden\Container\Container;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Queue job interface.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @version 1.0
 */
abstract class AbstractJob implements JobInterface, LoggerAwareInterface, EventAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use EventAwareTrait;

    /**
     * Job message ID
     * @var string
     */
    protected $id;

    /**
     * Job execution status
     * @var string
     */
    protected $status;

    /**
     * Job data
     * @var array
     */
    protected $data;

    /**
     * Prepare job
     *
     * @param Container $di
     */
    public function __construct() {
        $this->setStatus(JobStatus::RECEIVED);
    }

    /**
     * Set job id
     *
     * @param string $id
     */
    public function setID(string $id) {
        $this->id = $id;
    }

    /**
     * Get job ID
     *
     * @return string
     */
    public function getID(): string {
        return $this->id;
    }

    /**
     * Get job name
     *
     * @return string
     */
    public function getName() {
        return static::class;
    }

    /**
     * Get message handling status
     *
     * @return string
     */
    public function getStatus(): string {
        return $this->status;
    }

    /**
     * Set message handling status
     *
     * @param string $status
     */
    public function setStatus(string $status) {
        $this->status = $status;
    }

    /**
     * Get job data
     *
     * @return array
     */
    public function getData(): array {
        return $this->data;
    }

    /**
     * Set job data
     *
     * @param array $data
     */
    public function setData(array $data) {
        $this->data = $data;
    }

    /**
     * NOOP Setup
     *
     */
    public function setup() {
        $this->setStatus(JobStatus::INPROGRESS);
    }

    /**
     * NOOP Teardown
     *
     */
    public function teardown() {
        $this->setStatus(JobStatus::COMPLETE);
    }

}