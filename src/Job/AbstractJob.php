<?php

/**
 * @license Proprietary
 * @copyright 2009-2018 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Job;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Kaecyra\AppCommon\Log\LoggerBoilerTrait;
use Kaecyra\AppCommon\Store;

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
     *
     * @var string
     */
    protected $id;

    /**
     * Job execution status
     *
     * @var string
     */
    protected $status = JobStatus::RECEIVED;

    /**
     * Job data
     *
     * @var Store
     */
    protected $data = null;

    /**
     * Job start time
     *
     * @var float
     */
    protected $startTime;

    /**
     * Job duration
     *
     * @var float
     */
    protected $duration;

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
    public function getName(): string {
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
        return $this->data ? $this->data->dump() : [];
    }

    /**
     * Set job data
     *
     * @param array $data
     */
    public function setData(array $data) {
        if ($this->data === null) {
            $this->data = new Store();
        }
        $this->data->prepare($data);
    }

    /**
     * Get key from payload
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null) {
        return $this->data ? $this->data->get($key, $default) : $default;
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
        /**
         * If the Job doesn't set a JobStatus in the run method, we set the Job as Complete
         * Otherwise, we respect the run method wishes
         */
        if ($this->getStatus() === JobStatus::INPROGRESS) {
            $this->setStatus(JobStatus::COMPLETE);
        }
        $this->duration = microtime(true) - $this->startTime;
    }

    /**
     * Get execution time
     *
     * @return float
     */
    public function getDuration(): float {
        return $this->duration ? $this->duration : microtime(true) - $this->startTime;
    }

    /**
     * Set startTime to now
     */
    public function setStartTimeNow() {
        $this->startTime = microtime(true);
    }
}
