<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Exception;

use Exception;

/**
 * Class JobRetryException.
 */
class JobRetryException extends Exception
{
    /* @var int */
    private $delay;

    /**
     * JobRetryException constructor.
     *
     * @param int|null $delay
     * @param string $reason
     */
    public function __construct(int $delay = null, string $reason = "")
    {
        parent::__construct($reason);
        $this->delay = $delay;
    }

    /**
     * @return int|null
     */
    public function getDelay(): ?int
    {
        return $this->delay;
    }
}
