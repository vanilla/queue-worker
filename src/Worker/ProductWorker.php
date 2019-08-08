<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Worker;

use Psr\Container\ContainerInterface;
use Throwable;
use Vanilla\QueueWorker\Event\AckedMessageProductWorkerEvent;
use Vanilla\QueueWorker\Event\ExecutedJobProductWorkerEvent;
use Vanilla\QueueWorker\Event\FailedIterationProductWorkerEvent;
use Vanilla\QueueWorker\Event\GotJobProductWorkerEvent;
use Vanilla\QueueWorker\Event\GotMessageProductWorkerEvent;
use Vanilla\QueueWorker\Event\NackedMessageProductWorkerEvent;
use Vanilla\QueueWorker\Event\ProductWorkerExhaustedWorkerEvent;
use Vanilla\QueueWorker\Event\ProductWorkerReadyWorkerEvent;
use Vanilla\QueueWorker\Event\RequeueJobProductWorkerEvent;
use Vanilla\QueueWorker\Event\UpdatedQueueProductWorkerEvent;
use Vanilla\QueueWorker\Event\ValidateMessageProductWorkerEvent;
use Vanilla\QueueWorker\Exception\InvalidMessageWorkerException;
use Vanilla\QueueWorker\Exception\JobRetryException;
use Vanilla\QueueWorker\Exception\JobRetryExhaustedException;
use Vanilla\QueueWorker\Exception\JobRetryFailedException;
use Vanilla\QueueWorker\Exception\MessageMismatchWorkerException;
use Vanilla\QueueWorker\Exception\RetryJobExhaustedWorkerException;
use Vanilla\QueueWorker\Exception\RetryJobFailedWorkerException;
use Vanilla\QueueWorker\Exception\WorkerException;
use Vanilla\QueueWorker\Exception\WorkerRetryException;
use Vanilla\QueueWorker\Job\JobInterface;
use Vanilla\QueueWorker\Message\Message;

/**
 * Product Worker
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 */
class ProductWorker extends AbstractQueueWorker
{
    /**
     * @var int Worker slot
     */
    protected $slot;

    /**
     * @var int How often to sync with distribution array
     */
    protected $syncFrequency;

    /**
     * @var int Last sync with distribution array
     */
    protected $lastSync;

    /**
     * @var int Number of iterations remaining
     */
    protected $iterations;

    /**
     * @var string Slot queues
     */
    protected $slotQueues;

    /**
     * @var integer
     */
    protected $iterationStartTime = 0;

    /**
     * @var integer
     */
    protected $iterationFinishTime;

    /**
     * Run worker instance
     *
     * This method runs the product queue processor worker.
     *
     * @param mixed $workerConfig
     *
     * @throws \Throwable
     */
    public function run($workerConfig)
    {
        // Prepare sync tracking
        $this->lastSync = 0;
        $this->syncFrequency = $this->getConfig()->get('queue.oversight.adjust', 600);

        // Prepare execution environment tracking
        $this->iterations = $this->getConfig()->get('process.max_requests', 10000);

        // Prepare slot tracking
        $this->slot = $workerConfig['slot'];

        // prepare idle sleep
        $idleSleep = $this->getConfig()->get('process.sleep', 500) * 1000;

        // Announce workerReady
        $this->fireEvent(new ProductWorkerReadyWorkerEvent($this));

        // Get jobs and do them.
        while ($this->isReady()) {

            // Potentially update worker distribution according to oversight
            $this->updateDistribution();

            $ran = $this->runIteration();

            // Sleep when no jobs are picked up
            if (!$ran) {
                usleep($idleSleep);
            }
        }

        $this->fireEvent(new ProductWorkerExhaustedWorkerEvent($this));
    }

    /**
     * Poll the queue for a message
     *
     * @return bool whether we handled any messages
     * @return bool
     *
     * @throws \Throwable
     */
    public function runIteration()
    {
        // Get job messages from slot queues - returns an array of messages
        $messages = $this->getMessagesFromSlotQueues();
        // No message? Return false immediately so worker can rest
        if (empty($messages) || !is_array($messages)) {
            return false;
        }

        // getMessagesFromSlotQueues is giving us at-most-one-message. However, we keep the loop for convenience
        foreach ($messages as $rawMessage) {
            // Got a message, so mark we are running an iteration
            $this->markIterationStart();

            // Prevent pollution
            $this->createWorkerContainer();

            try {

                // Get message object
                $message = $this->getParser()->decodeMessage($rawMessage);
                $this->fireEvent(new GotMessageProductWorkerEvent($this, $message));

                // Validate the message
                $this->fireEvent(new ValidateMessageProductWorkerEvent($this, $message));

                // Convert message to runnable job
                $job = $this->getJob($message);
                $this->fireEvent(new GotJobProductWorkerEvent($this, $job));

                // Run Job stack
                $job->setup();
                $job->run();
                $job->teardown();

                // Announce executedJob
                $this->fireEvent(new ExecutedJobProductWorkerEvent($this, $job));

                // Ack the message
                $this->getBrokerClient()->ackJob($message->getBrokerId());
                $this->fireEvent(new AckedMessageProductWorkerEvent($this, $message));

            } catch (Throwable $ex) {
                // $isTrueFail determined witch kind of Event we fire
                $isTrueFail = true;
                // $ackJob determined if we ack or nack the message in the Broker
                $ackJob = true;

                if ($ex instanceof JobRetryException) {
                    try {
                        // Announce requeueJob
                        $this->fireEvent(new RequeueJobProductWorkerEvent($this, $job, $ex));
                        $isTrueFail = false;
                    } catch (Throwable $requeueException) {
                        if ($requeueException instanceof RetryJobExhaustedWorkerException) {
                            /*
                             * The Api could respond that the Job is expired/exhausted and the retry was denied
                             * This could happens because you reached the TTL of the Job or a TTL hard-limit
                             */
                            $isTrueFail = false;
                        } elseif ($requeueException instanceof RetryJobFailedWorkerException) {
                            /*
                             * RetryJobFail means the Job asked for a Retry, however the Retry couldn't be scheduled (API)
                             * We nack the Job to requeue into the Broker
                             */
                            $ackJob = false;
                        }
                        $ex = $requeueException;
                    }
                }

                if ($isTrueFail) {
                    if (!$ex instanceof WorkerException) {
                        // convert any kind of Exception into a WorkerException
                        $ex = new WorkerException($message, $job, $ex->getMessage(), ['originatingException' => $ex]);
                    }
                    // Announce failedIteration
                    $this->fireEvent(new FailedIterationProductWorkerEvent($this, $ex));
                } else {
                    // Announce executedJob
                    $this->fireEvent(new ExecutedJobProductWorkerEvent($this, $job));
                }

                if ($ackJob) {
                    // Ack the message and announce it
                    $this->getBrokerClient()->ackJob($message->getBrokerId());
                    $this->fireEvent(new AckedMessageProductWorkerEvent($this, $message));
                } else {
                    // Nack the message and announce it
                    $this->getBrokerClient()->nack($message->getBrokerId());
                    $this->fireEvent(new NackedMessageProductWorkerEvent($this, $message));
                }
            }

            // Prevent pollution
            $this->destroyWorkerContainer();

            // We are finished, so mark it
            $this->markIterationFinish();
        }

        return true;
    }

    /**
     * Update queue priority
     *
     * Each worker is assigned a "slot" by the main queue process. This slot is
     * associated with a set of queues. Every queue.oversight.adjust seconds the
     * worker re-queries its list of queues from memcache. This allows the queue
     * to automatically adjust itself to changing queue backlogs.
     *
     * This method queries the distribution array and updates the queue list based
     * on slot number.
     *
     * @throws \Throwable
     */
    public function updateDistribution()
    {
        if (!$this->slotQueues || (time() - $this->lastSync) > $this->syncFrequency) {
            // Sync
            $distribution = $this->getCache()->get(AbstractQueueWorker::QUEUE_DISTRIBUTION_KEY);
            if (!$distribution) {
                $distribution = [];
            }

            $update = val($this->slot, $distribution, $this->getQueues('pull'));
            if ($this->slotQueues != $update) {
                $this->slotQueues = $update;
                $this->fireEvent(new UpdatedQueueProductWorkerEvent($this));
            }
            $this->lastSync = time();
        }
    }

    /**
     * Get job for message
     *
     * @param \Vanilla\QueueWorker\Message\Message $message
     *
     * @return \Vanilla\QueueWorker\Job\JobInterface
     * @throws \Garden\Container\ContainerException
     * @throws \Garden\Container\NotFoundException
     * @throws \Vanilla\QueueWorker\Exception\InvalidMessageWorkerException
     * @throws \Vanilla\QueueWorker\Exception\MessageMismatchWorkerException
     */
    public function getJob(Message $message): JobInterface
    {
        /**
         * @var ContainerInterface
         */
        $workerDI = $this->getContainer()->get('@WorkerContainer');

        $payloadType = $message->getType();

        // Check that the specified job exists
        if (!$workerDI->has($payloadType)) {
            throw new MessageMismatchWorkerException($message, "Specified job class cannot be found: ".$payloadType);
        }

        // Create job instance
        $job = $workerDI->get($payloadType);

        // Check that the job is legal
        if (!$job instanceof JobInterface) {
            throw new InvalidMessageWorkerException($message, "Specified job class does not implement JobInterface: ".$payloadType);
        }

        $job->setStatus(WorkerStatus::progress());
        $job->setMessage($message);

        return $job;
    }

    /**
     * Get messages from slot queues
     *
     * @return mixed
     */
    protected function getMessagesFromSlotQueues()
    {
        // Treat the slot queues as array
        $slotQueues = $this->slotQueues;
        if (!is_array($slotQueues)) {
            $slotQueues = explode(' ', $slotQueues);
        }

        // Prepare the getJob command with variadic queue parameters
        $commandArgs = $slotQueues;
        $commandArgs[] = [
            'nohang' => true,
            'count' => 1,
            'withcounters' => true,
        ];

        /*
         * Get job from slot queues
         * connect method is not a true connect
         * it will evaluate the need of changing the broker node in case we are already connected
         */
        $this->getBrokerClient()->connect();
        return call_user_func_array([$this->getBrokerClient(), 'getJob'], $commandArgs);
    }

    /**
     * Check if queue is a ready to retrieve jobs
     *
     * @return bool
     */
    public function isReady()
    {
        return $this->iterations > 0;
    }

    /**
     * @return int
     */
    public function getIterations()
    {
        return $this->iterations;
    }

    /**
     * @return string
     */
    public function getSlotQueues()
    {
        return $this->slotQueues;
    }

    /**
     * Create the @WorkerContainer
     */
    protected function createWorkerContainer()
    {
        // Clone DI to prevent pollution
        $workerContainer = clone $this->getContainer();
        $workerContainer->setInstance(ContainerInterface::class, $workerContainer);

        // Remember this DI in the main DI
        $this->getContainer()->setInstance('@WorkerContainer', $workerContainer);
    }

    /**
     * Destroy the @WorkerContainer
     *
     * @throws \Garden\Container\ContainerException
     * @throws \Garden\Container\NotFoundException
     */
    protected function destroyWorkerContainer()
    {
        $this->getContainer()->get('@WorkerContainer')->setInstance(Container::class, null);
        $this->getContainer()->setInstance('@WorkerContainer', null);
    }

    /**
     * Mark the iteration start
     */
    public function markIterationStart()
    {
        $this->iterations--;
        $this->iterationStartTime = microtime(true);
        $this->iterationFinishTime = null;
    }

    /**
     * Mark the iteration finish
     */
    public function markIterationFinish()
    {
        $this->iterationFinishTime = microtime(true);
    }

    /**
     * Get iteration execution time
     *
     * @return float
     */
    public function getIterationDuration(): float
    {
        return $this->iterationFinishTime === null ?
            microtime(true) - $this->iterationStartTime :
            $this->iterationFinishTime - $this->iterationStartTime;
    }

    /**
     * Get worker slot
     *
     * @return int slot number
     */
    public function getSlot(): int
    {
        return $this->slot;
    }
}
