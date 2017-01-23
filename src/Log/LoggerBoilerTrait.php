<?php

/**
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2016 Tim Gunter
 * @license MIT
 */

namespace Vanilla\ProductQueue\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Log\LogLevel;

/**
 * A trait that provides getLogger() and NullLogger passthru functionality.
 *
 */
trait LoggerBoilerTrait {

    /**
     * Get a logger
     * @return \Psr\Log\LoggerInterface
     */
    private function getLogger() {
        if (!($this->logger instanceof LoggerInterface)) {
            return new NullLogger;
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
     * @return int Returns the numeric log level or `8` if the level is invalid.
     */
    private function levelPriority(string $level): int {
        static $priorities = [
            LogLevel::DEBUG     => LOG_DEBUG,
            LogLevel::INFO      => LOG_INFO,
            LogLevel::NOTICE    => LOG_NOTICE,
            LogLevel::WARNING   => LOG_WARNING,
            LogLevel::ERROR     => LOG_ERR,
            LogLevel::CRITICAL  => LOG_CRIT,
            LogLevel::ALERT     => LOG_ALERT,
            LogLevel::EMERGENCY => LOG_EMERG
        ];

        if (isset($priorities[$level])) {
            return $priorities[$level];
        } else {
            return LOG_DEBUG + 1;
        }
    }

    /**
     * Output to log (screen or file or both)
     *
     * @param string $level logger event level
     * @param string $message
     * @param array $context optional.
     * @param type $options optional.
     */
    private function log(string $level, string $message, array $context = []) {
        if (!is_array($context)) {
            $context = [];
        }
        $context = array_merge([
            'pid' => posix_getpid(),
            'time' => date('Y-m-d H:i:s'),
            'message' => $message
        ], $context);

        $this->getLogger()->log($level, $message, $context);
    }

}