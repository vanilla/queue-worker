<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Job;

use Vanilla\ProductQueue\Log\LoggerBoilerTrait;

use Garden\Container\Container;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Queue job interface.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
abstract class AbstractJob implements JobInterface, LoggerAwareInterface, EventAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use EventAwareTrait;

    /**
     * Dependency Injection Container
     * @var Container
     */
    protected $di;

    /**
     * Job execution status
     * @var string
     */
    protected $status;

    /**
     * Prepare job
     *
     * @param Container $di
     */
    public function __construct(Container $di) {
        $this->di = $di;

        $this->setStatus(JobStatus::RECEIVED);
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