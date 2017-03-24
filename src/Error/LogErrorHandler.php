<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Vanilla\QueueWorker\Error;

use Vanilla\QueueWorker\Log\LoggerBoilerTrait;

use Garden\Daemon\ErrorHandlerInterface;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

/**
 * Queue log error handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package garden-daemon
 */
class LogErrorHandler implements ErrorHandlerInterface, LoggerAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;

    /**
     * Log error
     *
     * @param int $errorLevel
     * @param string $message
     * @param string $file
     * @param int $line
     * @param array $context
     */
    public function error($errorLevel, $message, $file, $line, $context) {
        $errorFormat = "PHP {levelString}: {message} in {file} on line {line}";

        $level = $this->phpErrorLevel($errorLevel);
        $this->log($level, $errorFormat, [
            'level' => $level,
            'levelString' => ucfirst($level),
            'message' => $message,
            'file' => $file,
            'line' => $line
        ]);
    }

}