<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Addon;

use Vanilla\ProductQueue\Log\LoggerBoilerTrait;

use Kaecyra\AppCommon\Event\EventManager;
use Kaecyra\AppCommon\Event\EventBindsInterface;
use Kaecyra\AppCommon\Event\EventBindsTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * ProductQueue Addon interface.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @since 1.0
 */
abstract class AbstractAddon implements AddonInterface, EventBindsInterface, LoggerAwareInterface {

    use LoggerBoilerTrait;
    use LoggerAwareTrait;
    use EventBindsTrait;

    /**
     * Addon marker
     * @var Addon
     */
    protected $addon;

    /**
     * Event manager
     * @var EventManager
     */
    protected $events;

    /**
     * Addon config
     * @var array
     */
    protected $config;

    public function __construct($config = []) {
        $this->config = (array)$config;
    }

    /**
     * Set addon marker
     *
     * @param Addon $addon
     */
    public function setAddon(Addon $addon) {
        $this->addon = $addon;
    }

}