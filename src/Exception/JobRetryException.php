<?php

/**
 * Vanilla queue extension: ClassMapperAddon
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 * @package queue-worker
 * @since 1.0
 */

namespace Vanilla\QueueWorker\Exception;

use Exception;

/**
 * Class JobRetryException.
 */
class JobRetryException extends Exception {
    /* @var int */
    private $delay;

    /* @var int|null */
    private $ttl;

    public function __construct(string $reason, int $delay, int $ttl = null) {
        parent::__construct($reason.". Job will be scheduled to retry in $delay seconds");
        $this->delay = $delay;
        $this->tll = $ttl;
    }

    /**
     * @return int
     */
    public function getDelay(): int {
        return $this->delay;
    }

    /**
     * @return int|null
     */
    public function getTTL(): ?int {
        return $this->ttl;
    }
}
