<?php
/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Event;

/**
 * Class WorkerEvent.
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class WorkerEvent
{
    /**
     * @var array
     */
    protected $stackResult = [];

    /**
     * @return string
     */
    public function getName(): string
    {
        return static::class;
    }

    /**
     * @param array $stackResult
     *
     * @return $this
     */
    public function setStackResult(array $stackResult)
    {
        $this->stackResult[] = $stackResult;
        return $this;
    }
}
