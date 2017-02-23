<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Message\Parser;

use Vanilla\ProductQueue\Message\Message;

/**
 * Queue message parser interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
class JSONMessageParser extends AbstractMessageParser {

    /**
     * Decode message from queue
     *
     * @param array $rawMessage
     * @return Message
     */
    public function decodeMessage($rawMessage): Message {
        // Extract message features
        list($id, $headers, $body) = $this->extractMessageFields($rawMessage);

        // Decode message body
        $body = json_decode($body, true);

        return new Message($id, $headers, $body);
    }

    /**
     * Encode message for queue insertion
     *
     * @param Message $message
     * @return string
     */
    public function encodeMessage(Message $message): string {
        $rawMessage = "";

        // Build header section
        foreach ($message->getHeaders() as $headerKey => $header) {
            $rawMessage .= sprintf("%s: %s\n", $headerKey, $header);
        }

        // Build body section
        $rawMessage .= "\n\n";
        $rawMessage .= json_encode($message->getBody());

        return $rawMessage;
    }

}