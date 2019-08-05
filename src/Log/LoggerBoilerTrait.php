<?php
/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * A trait that provides getLogger() and NullLogger passthru functionality.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 */
trait LoggerBoilerTrait
{
    /**
     * Get a logger
     * @return \Psr\Log\LoggerInterface
     */
    private function getLogger()
    {
        if (!($this->logger instanceof LoggerInterface)) {
            $this->logger = new NullLogger;
        }
        return $this->logger;
    }

    /**
     * Get the numeric priority for a log level.
     *
     * The priorities are set to the LOG_* constants from the {@link syslog()} function.
     * A lower number is more severe.
     *
     * @param string|int $level The string log level or an actual priority.
     *
     * @return int Returns the numeric log level or `8` if the level is invalid.
     */
    protected function levelPriority(string $level): int
    {
        static $priorities = [
            LogLevel::DEBUG => LOG_DEBUG,
            LogLevel::INFO => LOG_INFO,
            LogLevel::NOTICE => LOG_NOTICE,
            LogLevel::WARNING => LOG_WARNING,
            LogLevel::ERROR => LOG_ERR,
            LogLevel::CRITICAL => LOG_CRIT,
            LogLevel::ALERT => LOG_ALERT,
            LogLevel::EMERGENCY => LOG_EMERG,
        ];

        if (isset($priorities[$level])) {
            return $priorities[$level];
        } else {
            return LOG_DEBUG + 1;
        }
    }

    /**
     * Get LogLevel corresponding to PHP error
     *
     * @param int $level
     *
     * @return string
     */
    protected function phpErrorLevel($level)
    {
        switch ($level) {
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return LogLevel::NOTICE;

            case E_WARNING:
            case E_USER_WARNING:
            case E_COMPILE_WARNING:
            case E_CORE_WARNING:
                return LogLevel::WARNING;

            case E_ERROR:
            case E_USER_ERROR:
            case E_PARSE:
            case E_COMPILE_ERROR:
            case E_CORE_ERROR:
            case E_RECOVERABLE_ERROR:
                return LogLevel::ERROR;
        }

        return LogLevel::ERROR;
    }

    /**
     * Output to log (screen or file or both)
     *
     * @param string $level logger event level
     * @param string $message
     * @param array $context optional.
     */
    protected function log(string $level, string $message, array $context = [])
    {
        if (!is_array($context)) {
            $context = [];
        }
        $context = array_merge([
            'pid' => posix_getpid(),
            'time' => date('Y-m-d H:i:s'),
            'message' => $message,
        ], $context);

        $this->getLogger()->log($level, "[{time}] [{pid}] {$message}", $context);
    }

}
