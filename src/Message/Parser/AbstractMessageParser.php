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
 * @package queue-worker
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

        $headers = [
            'broker_id' => $rawMessage['id'],
            'broker_queue' => $rawMessage['queue'],
            'broker_nacks' => $rawMessage['nacks'],
            'broker_additional-deliveries' => $rawMessage['additional-deliveries'],
        ];

        if (count($body) == 2) {
            // Get headers
            $headers = array_merge($headers, $body[0]);
            // Get body
            $body = $body[1];
        }

        return [
            'id' => $id,
            'headers' => $headers,
            'body' => $body,
        ];
    }

}
