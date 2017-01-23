<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Queue\Disque;

use Vanilla\ProductQueue\Queue\QueueInterface;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use Vanilla\ProductQueue\Log\LoggerBoilerTrait;

/**
 * Disque Queue Driver.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
class DisqueQueue implements QueueInterface, LoggerAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;

    protected $config;

    public function __construct(array $config) {
        $this->config = $config;

        $this->log(LogLevel::INFO, " started disque driver");
    }

}