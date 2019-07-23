<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Error;

use Garden\Daemon\ErrorHandlerInterface;
use Kaecyra\AppCommon\Event\EventAwareInterface;
use Vanilla\QueueWorker\Event\EventAwareTrait;
use Vanilla\QueueWorker\Event\FatalErrorWorkerEvent;
use Vanilla\QueueWorker\Log\LoggerBoilerTrait;

/**
 * Queue fatal error handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 */
class FatalErrorHandler implements ErrorHandlerInterface, EventAwareInterface
{

    use LoggerBoilerTrait;
    use EventAwareTrait;

    /**
     * @param int $errorLevel
     * @param string $message
     * @param string $file
     * @param int $line
     * @param array $context
     *
     * @throws \Throwable
     */
    public function error($errorLevel, $message, $file, $line, $context)
    {
        $level = $this->phpErrorLevel($errorLevel);
        $backtrace = debug_backtrace(0);

        $this->fireEvent(new FatalErrorWorkerEvent($level, $message, $file, $line, $backtrace));
    }

}
