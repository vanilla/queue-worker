<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Message\Parser;

/**
 * Queue message parser interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
abstract class AbstractMessageParser implements ParserInterface {

    /**
     * Extract message fields from raw queue message
     *
     * @param array $rawMessage
     * @return array
     */
    public function extractMessageFields(array $rawMessage): array {
        $id = $rawMessage['id'];
        $body = json_decode($rawMessage['body'], true);

        // Extract extras from message
        $extras = array_diff_key($rawMessage, [
            'queue' => true,
            'id'    => true,
            'body'  => true
        ]);

        $headers = [
            'queue' => $rawMessage['queue']
        ];

        if (count($body) == 2) {

            // Get headers
            $headers = array_merge($headers, $body[0]);

            // Get body
            $body = $body[1];

        } else {
            // No headers, just body
            $body = $body;
        }

        return [
            'id' => $id,
            'headers' => $headers,
            'body' => $body,
            'extras' => $extras
        ];
    }

}
