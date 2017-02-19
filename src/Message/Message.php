<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Message;

/**
 * Queue message.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
class Message {

    const STATUS_RECEIVED = 'received';
    const STATUS_INPROCESS = 'inprocess';
    const STATUS_COMPLETE = 'complete';
    const STATUS_FAILED = 'failed';
    const STATUS_RETRY = 'retry';
    const STATUS_MISMATCH = 'mismatch';

    /**
     * Message ID
     * @var string
     */
    protected $id;

    /**
     * Message Headers
     * @var array
     */
    protected $headers;

    /**
     * Message Body
     * @var array
     */
    protected $body;

    /**
     *
     * @var string
     */
    protected $queue;

    /**
     *
     * @var type
     */
    protected $status;

    /**
     * Create message
     *
     * @param string $id
     * @param array $headers
     * @param array $body
     */
    public function __construct($id, $headers, $body) {
        $this->id = $id;
        $this->headers = $headers;
        $this->body = $body;

        $this->queue = $this->headers['queue'] ?? null;

        $this->status = self::STATUS_RECEIVED;
    }

    /**
     * Get message ID
     *
     * @return string
     */
    public function getID() {
        return $this->id;
    }

    /**
     * Get message headers
     *
     * @return array
     */
    public function getHeaders() {
        return $this->headers;
    }

    /**
     * Get message body
     *
     * @return array
     */
    public function getBody() {
        return $this->body;
    }

    /**
     * Get message handling status
     *
     * @return string
     */
    public function getStatus() {
        return $this->status;
    }

    /**
     * Set message handling status
     *
     * @param string $status
     */
    public function setStatus($status) {
        $this->status = $status;
    }

    /**
     * Get job payload type
     *
     * @return string|null
     */
    public function getPayloadType() {
        return $this->header['job'] ?? null;
    }

}