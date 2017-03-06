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
     * Addon startup
     * 
     */
    public function start();

}