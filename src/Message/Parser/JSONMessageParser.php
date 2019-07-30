<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Message\Parser;

use Vanilla\QueueWorker\Message\Message;

/**
 * JSON queue message parser
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @version 1.0
 */
class JSONMessageParser extends AbstractMessageParser {

    /**
     * Decode message from queue
     *
     * @param array $rawMessage
     * @return Message
     */
    public function decodeMessage(array $rawMessage): Message {
        // Extract message features
        $fields = $this->extractMessageFields($rawMessage);

        // Decode message body
        $fields['body'] = json_decode($fields['body'], true);

        return new Message($fields['id'], $fields['headers'], $fields['body']);
    }

    /**
     * Encode message for queue insertion
     *
     * @param Message $message
     * @return array
     */
    public function encodeMessage(Message $message) {
        $encodedBody = json_encode($message->getBody());
        return [$message->getHeaders(), $encodedBody];
    }

}
