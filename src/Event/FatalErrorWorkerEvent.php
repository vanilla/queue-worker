<?php

namespace Vanilla\QueueWorker\Event;

/**
 * Class FatalErrorWorkerEvent.
 */
class FatalErrorWorkerEvent extends WorkerEvent
{
    /**
     * @var string
     */
    protected $level;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var string
     */
    protected $file;

    /**
     * @var string
     */
    protected $line;

    /**
     * @var string
     */
    protected $backtrace;

    /**
     * FatalErrorWorkerEvent constructor.
     *
     * @param null $level
     * @param null $message
     * @param null $file
     * @param null $line
     * @param null $backtrace
     */
    public function __construct($level = null, $message = null, $file = null, $line = null, $backtrace = null)
    {
        $this->level = $level ?? 'fatal';
        $this->message = "Uncaught exception '{$message}'";
        $this->file = $file ?? "N/A";
        $this->line = $line ?? "N/A";
        $this->backtrace = $backtrace ?? "N/A";
    }

    /**
     * Getter of level.
     *
     * @return string
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * Getter of message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Getter of file.
     *
     * @return string
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Getter of line.
     *
     * @return string
     */
    public function getLine(): string
    {
        return $this->line;
    }

    /**
     * Getter of backtrace.
     *
     * @return string
     */
    public function getBacktrace(): string
    {
        return $this->backtrace;
    }
}
