<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Message;

/**
 * Queue message.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @version 1.0
 */
class Message {

    /**
     * Message ID
     *
     * @var string
     */
    protected $id;

    /**
     * Message Headers
     *
     * @var array
     */
    protected $headers;

    /**
     * Message Body
     *
     * @var array
     */
    protected $body;

    /**
     * Create message
     *
     * @param string $id
     * @param array $headers
     * @param array $body
     */
    public function __construct(string $id, array $headers, array $body) {
        $this->id = $id;
        $this->headers = $headers;
        $this->body = $body;
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
     * Get queue name
     *
     * @return string
     */
    public function getQueue(): string {
        return $this->getHeader('broker_queue', '');
    }

    /**
     * Get job payload type
     *
     * @return string
     */
    public function getPayloadType(): string {
        return $this->getHeader('type', 'N/A');
    }

    /**
     * Get header
     *
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function getHeader(string $key, $default = null) {
        return $this->headers[$key] ?? $default;
    }
}
