<?php

/**
 * @license MIT
 * @copyright 2016 Tim Gunter
 */

namespace Vanilla\QueueWorker\Error;

use Vanilla\QueueWorker\Log\LoggerBoilerTrait;

use Garden\Daemon\ErrorHandlerInterface;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;

/**
 * Queue fatal error handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package garden-daemon
 */
class FatalErrorHandler implements ErrorHandlerInterface, EventAwareInterface {

    use LoggerBoilerTrait;
    use EventAwareTrait;

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
        $level = $this->phpErrorLevel($errorLevel);

        $backtrace = debug_backtrace(0);

        $this->fire('fatalError', [[
            'level'     => $level ?? 'fatal',
            'message'   => "Uncaught exception '{$message}'",
            'file'      => $file,
            'line'      => $line,
            'backtrace' => $backtrace
        ]]);
    }

}