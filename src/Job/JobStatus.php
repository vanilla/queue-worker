<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Job;

/**
 * Job Status
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
class JobStatus {

    const RECEIVED = 'received';
    const INPROGRESS = 'inprogress';
    const RETRY = 'retry';
    const COMPLETE = 'complete';

    const INVALID = 'invalid';
    const FAILED = 'failed';
    const MISMATCH = 'mismatch';
    const ABANDONED = 'abandoned';
    const ERROR = 'error';

}