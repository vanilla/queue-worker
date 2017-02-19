<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Job;

use Garden\Container\Container;

/**
 * Queue job interface.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
interface JobInterface {

    /**
     *
     * @param Container $di
     */
    public function __construct(Container $di, Message $message);

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
     * Run job payload
     */
    public function run();

}