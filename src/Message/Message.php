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
     * Message Extras
     * @var array
     */
    protected $extras;

    /**
     * Message Queue
     * @var string
     */
    protected $queue;

    /**
     * Create message
     *
     * @param string $id
     * @param array $headers
     * @param array $body
     * @param array $extras optional
     */
    public function __construct(string $id, array $headers, array $body, array $extras = []) {
        $this->id = $id;
        $this->headers = $headers;
        $this->body = $body;
        $this->extras = $extras;

        $this->queue = $this->headers['queue'] ?? null;
    }

    /**
     * Get message ID
     *
     * @return string
     */
    public function getID(): string {
        return $this->id;
    }

    /**
     * Get message headers
     *
     * @return array
     */
    public function getHeaders(): array {
        return $this->headers;
    }

    /**
     * Get message body
     *
     * @return array
     */
    public function getBody(): array {
        return $this->body;
    }

    /**
     * Get job payload type
     *
     * @return string|null
     */
    public function getPayloadType(): string {
        return $this->headers['job'] ?? null;
    }

    /**
     * Get extras
     *
     * @return array
     */
    public function getExtras(): array {
        return $this->extras;
    }

    /**
     * Get extra
     *
     * @param string $key
     * @return mixed
     */
    public function getExtra(string $key) {
        return $this->extras[$key] ?? null;
    }

}