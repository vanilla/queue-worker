<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Worker;

/**
 * Class WorkerStatus
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class WorkerStatus
{
    /**
     * @var string
     */
    protected $code;

    /**
     * WorkerStatus constructor.
     *
     * @param string $code
     */
    protected function __construct(string $code)
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Is that WorkerStatus
     *
     * @param WorkerStatus $workerStatus
     *
     * @return bool
     */
    public function is(WorkerStatus $workerStatus): bool
    {
        return $this->code == $workerStatus->getCode();
    }

    /**
     * @return WorkerStatus
     */
    public static function complete()
    {
        return new WorkerStatus('complete');
    }

    /**
     * @return WorkerStatus
     */
    public static function error()
    {
        return new WorkerStatus('error');
    }

    /**
     * @return WorkerStatus
     */
    public static function failed()
    {
        return new WorkerStatus('failed');
    }

    /**
     * @return WorkerStatus
     */
    public static function mismatch()
    {
        return new WorkerStatus('mismatch');
    }

    /**
     * @return WorkerStatus
     */
    public static function progress()
    {
        return new WorkerStatus('progress');
    }

    /**
     * @return WorkerStatus
     */
    public static function received()
    {
        return new WorkerStatus('received');
    }

    /**
     * @return WorkerStatus
     */
    public static function retry()
    {
        return new WorkerStatus('retry');
    }

    /**
     * @return WorkerStatus
     */
    public static function retryExhausted()
    {
        return new WorkerStatus('retry-exhausted');
    }

    /**
     * @return WorkerStatus
     */
    public static function retryFailed()
    {
        return new WorkerStatus('retry-failed');
    }

    /**
     * @return WorkerStatus
     */
    public static function invalidMessage()
    {
        return new WorkerStatus('invalid-message');
    }

    /**
     * @return WorkerStatus
     */
    public static function invalidJob()
    {
        return new WorkerStatus('invalid-job');
    }

    /**
     * @return WorkerStatus
     */
    public static function unknown()
    {
        return new WorkerStatus('unknown');
    }
}
