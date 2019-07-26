<?php

/**
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Vanilla\QueueWorker\Job;

/**
 * Queue job interface.
 *
 * Interface for a runnable job payload.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 */
interface JobInterface {

    /**
     * Get job ID
     *
     * @return string
     */
    public function getID(): string;

    /**
     * Set job ID
     *
     * @param string $id
     */
    public function setID(string $id);

    /**
     * Get the job name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get job data by key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Get job data
     *
     * @return array
     */
    public function getData(): array;

    /**
     * Set job data
     *
     * @param array $data
     */
    public function setData(array $data);

    /**
     * Get message handling status
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Set message handling status
     *
     * @param string $status
     */
    public function setStatus(string $status);

    /**
     * Set startTime to now
     */
    public function setStartTimeNow();

    /**
     * Return execution time
     * @return float
     */
    public function getDuration();

    /**
     * Run job payload
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
