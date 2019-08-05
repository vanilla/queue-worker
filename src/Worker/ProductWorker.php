<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Worker;

use Disque\Connection\ConnectionException;
use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;
use Throwable;
use Vanilla\QueueWorker\Event\AckedMessageProductWorkerEvent;
use Vanilla\QueueWorker\Event\ExecutedJobProductWorkerEvent;
use Vanilla\QueueWorker\Event\FailedIterationProductWorkerEvent;
use Vanilla\QueueWorker\Event\GotJobProductWorkerEvent;
use Vanilla\QueueWorker\Event\GotMessageProductWorkerEvent;
use Vanilla\QueueWorker\Event\NackedMessageProductWorkerEvent;
use Vanilla\QueueWorker\Event\RequeueJobProductWorkerEvent;
use Vanilla\QueueWorker\Event\ValidateMessageProductWorkerEvent;
use Vanilla\QueueWorker\Exception\InvalidMessageWorkerException;
use Vanilla\QueueWorker\Exception\JobRetryException;
use Vanilla\QueueWorker\Exception\JobRetryExhaustedException;
use Vanilla\QueueWorker\Exception\JobRetryFailedException;
use Vanilla\QueueWorker\Exception\MessageMismatchWorkerException;
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
     * Max retries to connect to the Daemon
     */
    const MAX_RETRIES = 15;

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
    protected $iterationStartTime;

    /**
     * @var integer
     */
    protected $iterationFinishTime;

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
     * @throws \Exception
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
                $this->fire('updatedQueues', [$this]);
            }
            $this->lastSync = time();
        }
    }

    /**
     * Prepare product worker
     *
     * @param $workerConfig
     *
     * @throws ConnectionException
     * @throws \Exception
     */
    public function prepareProductWorker($workerConfig)
    {

        // Prepare sync tracking
        $this->lastSync = 0;
        $this->syncFrequency = $this->config->get('queue.oversight.adjust', 5);

        // Prepare execution environment tracking
        $this->iterations = $this->config->get('process.max_requests', 0);

        // Prepare slot tracking
        $this->slot = $workerConfig['slot'];

        // Connect to queues and cache
        $this->prepareWorker(self::MAX_RETRIES);

        // Announce workerReady
        $this->fire('workerReady', [$this]);
    }

    /**
     * Run worker instance
     *
     * This method runs the product queue processor worker.
     *
     * @param mixed $workerConfig
     *
     * @throws ConnectionException
     * @throws \Throwable
     */
    public function run($workerConfig)
    {

        // Prepare worker
        $this->prepareProductWorker($workerConfig);

        // Get jobs and do them.
        $idleSleep = $this->config->get('process.sleep') * 1000;
        while ($this->isReady()) {

            // Potentially update worker distribution according to oversight
            $this->updateDistribution();

            $ran = $this->runIteration();

            // Sleep when no jobs are picked up
            if (!$ran) {
                usleep($idleSleep);
            }
        }

        $this->log(LogLevel::NOTICE, " exhausted iterations, exiting");
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

        foreach ($messages as $rawMessage) {
            // Got a message, so decrement iterations
            $this->iterations--;

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
                $this->getDisqueClient()->ackJob($message->getBrokerId());
                $this->fireEvent(new AckedMessageProductWorkerEvent($this, $message));

            } catch (Throwable $ex) {
                $isTrueFail = true;

                if ($ex instanceof JobRetryException) {
                    try {
                        // Announce requeueJob
                        $this->fireEvent(new RequeueJobProductWorkerEvent($this, $job, $ex));
                        // Announce executedJob
                        $this->fireEvent(new ExecutedJobProductWorkerEvent($this, $job));
                        // Ack the message
                        $this->getDisqueClient()->ackJob($message->getBrokerId());
                        $this->fireEvent(new AckedMessageProductWorkerEvent($this, $message));

                        $isTrueFail = false;
                    } catch (Throwable $requeueException) {
                        // The Api could respond that the Job is expired/exhausted and the retry was denied
                        // This could happens because you reached the TTL of the Job or a TTL hard-limit
                        $ex = $requeueException;
                    }
                }

                if ($isTrueFail) {
                    if (!$ex instanceof WorkerException) {
                        $ex = new WorkerException($message, $job, $ex->getMessage(), ['originatingException' => $ex]);
                    }

                    // Nack the message
                    $this->getDisqueClient()->nack($message->getBrokerId());
                    $this->fireEvent(new NackedMessageProductWorkerEvent($this, $message));

                    $this->fireEvent(new FailedIterationProductWorkerEvent($this, $ex));
                }
            }

            // Prevent pollution
            $this->destroyWorkerContainer();
        }

        return true;
    }

    /**
     * Get job for message
     *
     * @param Message $message
     *
     * @return JobInterface
     * @throws \Vanilla\QueueWorker\Exception\InvalidMessageWorkerException
     * @throws \Vanilla\QueueWorker\Exception\MessageMismatchWorkerException
     */
    public function getJob(Message $message): JobInterface
    {
        /**
         * @var ContainerInterface
         */
        $workerDI = $this->container->get('@WorkerContainer');

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
     * @return array
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
            'count' => 1,
            'withcounters' => true,
            'timeout' => 1,
        ];

        // Get job from slot queues
        return call_user_func_array([$this->getDisqueClient(), 'getJob'], $commandArgs);
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
        $workerContainer = clone $this->container;
        $workerContainer->setInstance(ContainerInterface::class, $workerContainer);

        // Remember this DI in the main DI
        $this->container->setInstance('@WorkerContainer', $workerContainer);
    }

    /**
     * Destroy the @WorkerContainer
     */
    protected function destroyWorkerContainer()
    {
        $this->container->get('@WorkerContainer')->setInstance(Container::class, null);
        $this->container->setInstance('@WorkerContainer', null);
    }

    /**
     * Mark the iteration start
     */
    public function markIterationStart()
    {
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
}
