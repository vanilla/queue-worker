<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Message;

/**
 * Queue message.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class Message
{
    /**
     * Message Headers
     *
     * @var array
     */
    protected $header;

    /**
     * Message Body
     *
     * @var array
     */
    protected $body;

    /**
     * Create message
     *
     * @param array $header
     * @param array $body
     */
    public function __construct(array $header, array $body)
    {
        $this->header = $header;
        $this->body = $body;
    }

    /**
     * Get message body
     *
     * @return array
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * @param string $key
     * @param null $default
     *
     * @return mixed
     */
    public function getBodyKey(string $key, $default = null)
    {
        return valr(trim($key), $this->body, $default);
    }

    /**
     * Get header
     *
     * @return array
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * Get header key
     *
     * @param string $key
     * @param null $default
     *
     * @return mixed|null
     */
    public function getHeaderKey(string $key, $default = null)
    {
        return valr(trim($key), $this->header, $default);
    }

    /**
     * Get message ID
     *
     * @return string
     */
    public function getBrokerId(): string
    {
        return $this->getHeaderKey('broker_id', "N/A");
    }

    /**
     * Get queue name
     *
     * @return string
     */
    public function getQueue(): string
    {
        return $this->getHeaderKey('broker_queue', 'N/A');
    }

    /**
     * Get job payload type
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->getHeaderKey('type', 'N/A');
    }

}
