<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Exception;

use Vanilla\ProductQueue\Message\Message;

/**
 * Message Exception: UnknownJob
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
class QueueMessageException extends \Exception {

    /**
     * Construct
     * 
     * @param Message $message
     * @param string $error
     */
    public function __construct(Message $message, string $error) {
        parent::__construct($error);
        $this->setQueueMessage($message);
    }

    /**
     * Get queue message
     *
     * @return Message
     */
    public function getQueueMessage(): Message {
        return $this->queueMessage;
    }

    /**
     * Set queue message
     *
     * @param Message $message
     */
    public function setQueueMessage(Message $message) {
        $this->queueMessage = $message;
    }

}