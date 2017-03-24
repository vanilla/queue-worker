<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Message\Parser;

use Vanilla\QueueWorker\Message\Message;

/**
 * Queue message parser interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
interface ParserInterface {

    /**
     * Decode message body
     *
     * @param array $rawMessage
     * @return Message
     */
    public function decodeMessage(array $rawMessage): Message;

    /**
     * Encode message body
     *
     * @param Message $message
     */
    public function encodeMessage(Message $message);

}