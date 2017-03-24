<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Message\Parser;

use Vanilla\QueueWorker\Message\Message;

/**
 * Native queue message parser
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @version 1.0
 */
class NativeMessageParser extends AbstractMessageParser {

    /**
     * Decode message from queue
     *
     * @param array $rawMessage
     * @return Message
     */
    public function decodeMessage(array $rawMessage): Message {
        // Extract message features
        $fields = $this->extractMessageFields($rawMessage);
        return new Message($fields['id'], $fields['headers'], $fields['body'], $fields['extras']);
    }

    /**
     * Encode message for queue insertion
     *
     * @param Message $message
     * @return array
     */
    public function encodeMessage(Message $message) {
        return [$message->getHeaders(), $message->getBody()];
    }

}