<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Error;

use Garden\Daemon\ErrorHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Vanilla\QueueWorker\Log\LoggerBoilerTrait;

/**
 * Queue log error handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
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
