<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Exception;

/**
 * Message Exception: BrokenJob
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
class BrokenJobException extends \Exception {

    /**
     * Get job payload name
     *
     * @return string
     */
    public function getJob(): string {
        return $this->getMessage();
    }

}