<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Addon;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Vanilla\QueueWorker\Log\LoggerBoilerTrait;

/**
 * Queue Worker Abstract addon base class
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 */
abstract class AbstractAddon implements AddonInterface, EventAwareInterface, LoggerAwareInterface
{

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

    /**
     * AbstractAddon constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        $this->config = (array)$config;
    }

    /**
     * Set addon marker
     *
     * @param Addon $addon
     */
    public function setAddon(Addon $addon)
    {
        $this->addon = $addon;
    }

    /**
     * Do nothing
     */
    public function start()
    {

    }

}
