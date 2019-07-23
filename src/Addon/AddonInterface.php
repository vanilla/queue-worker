<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Addon;

/**
 * Queue Worker Addon interface.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 */
interface AddonInterface
{

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
