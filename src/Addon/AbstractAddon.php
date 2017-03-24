<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Addon;

use Vanilla\ProductQueue\Log\LoggerBoilerTrait;

use Kaecyra\AppCommon\Event\EventManager;
use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * ProductQueue Addon interface.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @since 1.0
 */
abstract class AbstractAddon implements AddonInterface, EventAwareInterface, LoggerAwareInterface {

    use LoggerBoilerTrait;
    use LoggerAwareTrait;
    use EventAwareTrait;

    /**
     * Addon marker
     * @var Addon
     */
    protected $addon;

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

    /**
     * Do nothing
     */
    public function start() {

    }

}