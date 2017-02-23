<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Addon;

/**
 * ProductQueue Addon interface.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @since 1.0
 */
interface AddonInterface {

    /**
     * Set addon marker
     *
     * @param Addon $addon
     */
    public function setAddon(Addon $addon);

    /**
     * Bind any required events
     */
    public function start();

    /**
     * Bind to an event
     *
     * @param string $event
     * @param callable $handler
     */
    public function bind(string $event, callable $handler);

}