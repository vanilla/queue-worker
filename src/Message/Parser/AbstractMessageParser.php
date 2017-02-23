<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Message\Parser;

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
        $body = $rawMessage['body'];

        $headers = [
            'queue' => $rawMessage['queue']
        ];

        // Split body into body and headers
        $parts = explode("\n\n", $body);

        if (count($parts) > 1) {

            // Decode headers
            $rawHeaders = $parts[0];
            $rawHeaders = explode("\n", $rawHeaders);
            foreach ($rawHeaders as $rawHeader) {
                $header = explode(":", $rawHeader, 2);
                $headerName = strtolower($header[0]);
                $headers[$headerName] = trim($header[1]);
            }

            // Get body
            $body = $parts[1];

        } else {
            // No headers, just body
            $body = $body;
        }

        return [$id, $headers, $body];
    }

}
