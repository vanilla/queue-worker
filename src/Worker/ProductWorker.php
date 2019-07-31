<?php

/**
 * @license Proprietary
 * @copyright 2009-2018 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Worker;

use Vanilla\QueueWorker\Exception\JobRetryException;
use Vanilla\QueueWorker\Exception\JobRetryExhaustedException;
use Vanilla\QueueWorker\Exception\QueueMessageException;
use Vanilla\QueueWorker\Exception\UnknownJobException;
use Vanilla\QueueWorker\Job\JobInterface;
use Vanilla\QueueWorker\Job\JobStatus;

use Vanilla\QueueWorker\Message\Message;
use Psr\Container\ContainerInterface;
use \Disque\Connection\ConnectionException;
use Psr\Log\LogLevel;

/**
 * Product Worker
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @version 1.0
 */
class ProductWorker extends AbstractQueueWorker {

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
     * Check if queue is a ready to retrieve jobs
     *
     * @return bool
     */
    public function isReady() {
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
    public function updateDistribution() {
        if (!$this->slotQueues || (time() - $this->lastSync) > $this->syncFrequency) {
            // Sync
            $distribution = $this->cache->get(AbstractQueueWorker::QUEUE_DISTRIBUTION_KEY);
            if (!$distribution) {
                $distribution = [];
            }

            $update = val($this->slot, $distribution, $this->getQueues('pull'));
            if ($this->slotQueues != $update) {
                $this->slotQueues = $update;
                $this->fire('updatedQueues', [$this]);
            }
        }
    }

    /**
     * Prepare product worker
     *
     * @param $workerConfig
     * @throws ConnectionException
     * @throws \Exception
     */
    public function prepareProductWorker($workerConfig) {

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
     * @throws ConnectionException
     * @throws \Exception
     */
    public function run($workerConfig) {

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
     * @throws \Exception
     */
    public function runIteration() {

        // Get job messages from slot queues
        $messages = $this->getMessagesFromSlotQueues();

        // No message? Return false immediately so worker can rest
        if (empty($messages) || !is_array($messages)) {
            return false;
        }

        // GetJob returns an array of messages
        foreach ($messages as $rawMessage) {
            $ack = true;

            try {
                // Retrieve and parse message
                $message = $this->parseMessage($rawMessage);

                // Clone DI to prevent pollution
                $workerContainer = clone $this->container;
                $workerContainer->setInstance(ContainerInterface::class, $workerContainer);

                // Remember this DI in the main DI
                $this->container->setInstance('@WorkerContainer', $workerContainer);

                // Execute message payload
                $job = $this->handleMessage($message);

                // Determine status
                $jobStatus = $job->getStatus();

            } catch (\Throwable $ex) {

                if ($ex instanceof JobRetryException) {
                    try {
                        $this->fire('failedMessage', [$message ?? null, $ex]);
                        $this->fire('requeueMessage', [$message ?? null, $ex]);
                    } catch (JobRetryExhaustedException $exhaustedException) {
                        // The Api could respond that the Job is expired/exhausted and the retry was denied
                        // This could happens because you reached the TTL of the Job or a TTL hard-limit
                        // The catch allow us to proceed with the ack of the job.
                    } catch (\Throwable $t) {
                        $jobStatus = JobStatus::RETRY_FAILED;
                        $ack = false;
                        $this->fire('failedMessage', [$message ?? null, $ex, $jobStatus]);
                    }

                } else {
                    $jobStatus = ($ex instanceof QueueMessageException) ? $ex->getStatus() : JobStatus::ERROR;
                    $this->fire('failedMessage', [$message ?? null, $ex, $jobStatus]);
                }
            }

            // If we dont have a message, we can hardly acknowledge it
            if ($message && $message->getID()) {
                if ($ack) {
                    // Announce ackMessage
                    $this->fire('ackMessage', [$message]);
                    $this->queue->ackJob($message->getID());
                } else {
                    // Announce nackMessage
                    $this->fire('nackMessage', [$message]);
                    $this->queue->nack($message->getID());
                }
            }

            // Announce endJob
            $this->fire('endJob', [$message ?? null, $job ?? null, $jobStatus, $this->container->get('@WorkerContainer')]);

            // Destroy child DI
            $this->container->get('@WorkerContainer')->setInstance(Container::class, null);
            $this->container->setInstance('@WorkerContainer', null);
        }

        return true;
    }

    /**
     * Parse a queue message
     *
     * @param $rawMessage
     * @return Message
     * @throws \Exception
     */
    public function parseMessage($rawMessage): Message {

        // Got a message, so decrement iterations
        $this->iterations--;

        // Get message object
        $message = $this->parser->decodeMessage($rawMessage);

        // Announce gotMessage
        $this->fire('gotMessage', [$this, $message]);

        return $message;
    }

    /**
     * Handle a queue message
     *
     * @param Message $message
     * @return JobInterface
     * @throws \Exception
     */
    public function handleMessage(Message $message): JobInterface {

        /* Announce validateMessage
         * ideally, @throws BrokenMessageException */
        $this->fire('validateMessage', [$this, $message]);

        // Convert message to runnable job
        $job = $this->getJob($message, $this->container->get('@WorkerContainer'));

        /* Announce gotJob */
        $this->fire('gotJob', [$this, $job]);

        // Setup job
        $job->setup();

        // Run job
        $job->run();

        // Teardown job
        $job->teardown();

        /* Announce finishedJob */
        $this->fire('finishedJob', [$this, $job]);

        return $job;
    }

    /**
     * Get job for message
     *
     * @param Message $message
     * @param ContainerInterface $workerDI
     * @return JobInterface
     * @throws BrokenJobException
     * @throws UnknownJobException
     */
    public function getJob(Message $message, ContainerInterface $workerDI): JobInterface {
        $payloadType = $message->getPayloadType();

        // Check that the specified job exists
        if (!$workerDI->has($payloadType)) {
            throw new UnknownJobException($message, "specified job class cannot be found");
        }

        // Create job instance
        $job = $workerDI->get($payloadType);

        // Check that the job is legal
        if (!$job instanceof JobInterface) {
            throw new BrokenJobException($message, "specified job class does not implement JobInterface");
        }

        $job->setStartTimeNow();
        $job->setID($message->getID());
        $job->setBody($message->getBody());
        $job->setHeaders($message->getHeaders());

        return $job;
    }

    /**
     * Get messages from slot queues
     *
     * @return array
     */
    protected function getMessagesFromSlotQueues() {
        // Treat the slot queues as array
        $slotQueues = $this->slotQueues;
        if (!is_array($slotQueues)) {
            $slotQueues = explode(' ', $slotQueues);
        }

        // Prepare the getJob command with variadic queue parameters
        $commandArgs = $slotQueues;
        $commandArgs[] = [
            'nohang' => true,
            'withcounters' => true,
            'timeout' => 0,
        ];

        // Get job from slot queues
        return call_user_func_array([$this->queue, 'getJob'], $commandArgs);
    }

    /**
     * @return int
     */
    public function getIterations() {
        return $this->iterations;
    }

    /**
     * @return string
     */
    public function getSlotQueues() {
        return $this->slotQueues;
    }
}
