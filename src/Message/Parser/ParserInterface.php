<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Message\Parser;

use Vanilla\QueueWorker\Message\Message;

/**
 * Queue message parser interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
interface ParserInterface
{
    /**
     * Decode message body
     *
     * @param array $rawMessage
     *
     * @return Message
     */
    public function decodeMessage(array $rawMessage): Message;
}
