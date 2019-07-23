<?php

namespace Vanilla\QueueWorker\Event;

use Throwable;

/**
 * Trait EventAwareTrait.
 */
trait EventAwareTrait
{
    use \Kaecyra\AppCommon\Event\EventAwareTrait;

    /**
     * @param WorkerEvent $event
     *
     * @return WorkerEvent
     *
     * @throws Throwable
     */
    public function fireEvent(WorkerEvent $event)
    {
        $eventResults = $this->fireReturn($event->getName(), [$event]);
        $event->setStackResult($eventResults);
        return $event;
    }
}
