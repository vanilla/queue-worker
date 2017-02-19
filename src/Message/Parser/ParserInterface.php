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
interface ParserInterface {

    /**
     * Decode message body
     *
     * @return Message
     */
    public function decodeMessage($id, $body): Message;

    /**
     * Encode message body
     *
     * @param Message $message
     * @return string
     */
    public function encodeMessage(Message $message): string;

}