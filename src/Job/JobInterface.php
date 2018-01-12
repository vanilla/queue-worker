<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Job;

/**
 * Queue job interface.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @version 1.0
 */
interface JobInterface {

    /**
     * Get job data
     *
     * @return array
     */
    public function getData(): array;

    /**
     * Get job data by key
     * 
     * @param string $key
     * @return mixed
     */
    public function get(string $key);

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
     * Setup job payload for execution
     *
     */
    public function setup();

    /**
     * Clean up after job execution
     *
     */
    public function teardown();

    /**
     * Return execution time
     * @return float
     */
    public function getDuration();

    /**
     * Run job payload
     */
    public function run();

}