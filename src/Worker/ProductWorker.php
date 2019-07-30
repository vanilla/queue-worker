<?php

/**
 * @license Proprietary
 * @copyright 2009-2018 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Worker;

use Vanilla\QueueWorker\Exception\JobRetryException;
use Vanilla\QueueWorker\Job\JobInterface;
use Vanilla\QueueWorker\Job\JobStatus;

use Vanilla\QueueWorker\Message\Message;

use Vanilla\QueueWorker\Exception\UnknownJobException;
use Vanilla\QueueWorker\Exception\BrokenMessageException;
use Vanilla\QueueWorker\Exception\BrokenJobException;

use Psr\Container\ContainerInterface;

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
     * Worker slot
     *
     * @var int
     */
    protected $slot;

    /**
     * How often to sync with distribution array
     *
     * @var int
     */
    protected $syncFrequency;

    /**
     * Last sync with distribution array
     *
     * @var int
     */
    protected $lastSync;

    /**
     * Number of iterations remaining
     *
     * @var int
     */
    protected $iterations;

    /**
     * Number of per-job retries
     *
     * @var int
     */
    protected $retries;

    /**
     * Slot queues
     *
     * @var string
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
                $this->log(LogLevel::INFO, " updated queues for slot {$this->slot}: {$update}");
                $this->slotQueues = $update;

                $this->fire('updatedQueues', [$this]);
            }
        }
    }

    /**
     * Prepare product worker
     *
     * @param $workerConfig
     * @throws \Disque\Connection\ConnectionException
     */
    public function prepareProductWorker($workerConfig) {
        // Prepare sync tracking

        $this->lastSync = 0;
        $this->syncFrequency = $this->config->get('queue.oversight.adjust', 5);

        // Prepare execution environment tracking

        $this->iterations = $this->config->get('process.max_requests', 0);
        $this->retries = $this->config->get('process.max_retries', 0);

        // Prepare slot tracking

        $this->slot = $workerConfig['slot'];

        // Announce worker startup
        $this->log(LogLevel::NOTICE, "Product worker started (slot {slot})", [
            'slot' => $this->slot,
        ]);

        // Connect to queues and cache
        $this->prepareWorker(self::MAX_RETRIES);

        $this->fire('workerReady', [$this, $this->container]);
    }

    /**
     * Run worker instance
     *
     * This method runs the product queue processor worker.
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

            try {
                // Clone DI to prevent pollution
                $workerContainer = clone $this->container;
                $workerContainer->setInstance(ContainerInterface::class, $workerContainer);

                // Remember this DI in the main DI
                $this->container->setInstance('@WorkerContainer', $workerContainer);

                // Retrieve and parse message
                $message = $this->parseMessage($rawMessage);

                // Execute message payload
                $job = $this->handleMessage($message);

                // Determine status
                $jobStatus = $job->getStatus();

            } catch (JobRetryException $ex) {

                // Job asked for a retry with a delay
                $this->log(LogLevel::WARNING, "JobRetryException job [{id}][{job}]: {reason} :: delay: {delay} :: ttl: {ttl}", [
                    'id' => $message->getID(),
                    'delay' => $ex->getDelay(),
                    'ttl' => $ex->getTTL(),
                    'reason' => $ex->getMessage(),
                ]);
                $jobStatus = JobStatus::RETRY;
                $reason = $ex->getMessage();

            } catch (UnknownJobException $ex) {

                // Unable to load job matching payload type
                $this->log(LogLevel::WARNING, "Unknown job [{id}][{job}]: {reason}", [
                    'id' => $message->getID(),
                    'job' => $ex->getJob(),
                    'reason' => $ex->getMessage(),
                ]);
                $jobStatus = JobStatus::MISMATCH;
                $reason = $ex->getMessage();

            } catch (BrokenJobException $ex) {

                // Payload type matches object that is not a valid job
                $this->log(LogLevel::WARNING, "Broken job [{id}][{job}]: {reason}", [
                    'id' => $message->getID(),
                    'job' => $ex->getJob(),
                    'reason' => $ex->getMessage(),
                ]);
                $jobStatus = JobStatus::INVALID;
                $reason = $ex->getMessage();

            } catch (BrokenMessageException $ex) {

                // Message could not be processed within the allotted retry count
                $this->log(LogLevel::WARNING, "Broken message [{id}]: {reason}", [
                    'id' => $message->getID(),
                    'reason' => $ex->getMessage(),
                ]);
                $jobStatus = JobStatus::ABANDONED;
                $reason = $ex->getMessage();

            } catch (\Throwable $ex) {

                // PHP error
                $this->logException($ex);
                $jobStatus = JobStatus::ERROR;
                $reason = $ex->getMessage();

            } finally {
                $message = $message ?? null;
                $job = $job ?? null;
                $jobStatus = $jobStatus ?? JobStatus::ERROR;
                $reason = $reason ?? null;
            }

            $this->fire('endJob', [$message, $job, $jobStatus, $this->container->get('@WorkerContainer')]);

            // Destroy child DI
            $this->container->get('@WorkerContainer')->setInstance(Container::class, null);
            $this->container->setInstance('@WorkerContainer', null);

            // Handle end state for jobs
            if ($jobStatus === JobStatus::RETRY && $ex instanceof JobRetryException) {
                $this->failedMessage($message, $jobStatus, $reason, $ex);

                if ($this->fireReturn('retryMessage', [$message, $ex])) {
                    // Job was re-queue with a new daemonId. We ACK the current one
                    $this->queue->ackJob($message->getID());
                } else {
                    // Call to re-queue fail. NACK failed message after attempt (will retry)
                    // We could do better in this case.
                    $this->queue->nack($message->getID());
                };
            } elseif (in_array($jobStatus, [
                JobStatus::INVALID,
                JobStatus::ABANDONED,
                JobStatus::ERROR,
            ])) {
                // ACK failed message (will not retry)
                $this->queue->ackJob($message->getID());

                // Dead letter handler
                $this->failedMessage($message, $jobStatus, $reason, $ex);
            } else {
                if ($jobStatus != JobStatus::COMPLETE) {
                    // NACK failed message after attempt (will retry)
                    $this->queue->nack($message->getID());
                } else {
                    // ACK processed message
                    $this->queue->ackJob($message->getID());
                }
            }
        }

        return true;
    }

    /**
     * Parse a queue message
     *
     * @param array $rawMessage
     * @return Message
     */
    public function parseMessage($rawMessage): Message {

        // Got a message, so decrement iterations
        $this->iterations--;

        $this->log(LogLevel::DEBUG, "[{slot}] xGot message from queue: {queue}", [
            'slot' => $this->getSlot(),
        ]);
        $this->log(LogLevel::DEBUG, print_r($rawMessage, true));

        // Get message object
        $message = $this->parser->decodeMessage($rawMessage);

        $this->fire('gotMessage', [$message]);

        return $message;
    }

    /**
     * Handle a queue message
     *
     * @param Message $message
     * @return JobInterface
     * @throws BrokenJobException
     * @throws BrokenMessageException
     * @throws JobRetryException
     * @throws UnknownJobException
     */
    public function handleMessage(Message $message): JobInterface {

        // Check message integrity
        $this->validateMessage($message);

        $this->fire('prepareJobEnvironment', [$message, $this->container->get('@WorkerContainer')]);

        // Convert message to runnable job
        $job = $this->getJob($message, $this->container->get('@WorkerContainer'));

        $this->log(LogLevel::NOTICE, "[{slot}][{queue}] Resolved job: {job}", [
            'slot' => $this->getSlot(),
            'queue' => $message->getQueue(),
            'job' => $job->getName(),
        ]);

        $this->fire('gotJob', [$job]);

        // Setup job
        $job->setup();

        // Run job
        $job->run();

        // Teardown job
        $job->teardown();

        $this->fire('finishedJob', [$job]);

        $this->log(LogLevel::NOTICE, "[{slot}] Completed job: {job} ({id})", [
            'slot' => $this->getSlot(),
            'job' => $job->getName(),
            'id' => $message->getID(),
        ]);

        return $job;
    }

    /**
     * Validate message
     *
     * @param Message $message
     * @throws BrokenMessageException
     */
    public function validateMessage(Message $message) {
        $headers = $message->getHeaders();
        $nacks = $headers['broker_nacks'] ?? 0;
        $deliveries = $headers['broker_additional-deliveries'] ?? 0;

        $this->log(LogLevel::DEBUG, " message nacked {nacks} times, delivered {deliveries} additional times", [
            'deliveries' => $deliveries,
            'nacks' => $nacks,
        ]);

        if (($deliveries + $nacks) > $this->retries) {
            throw new BrokenMessageException($message, "too many retries");
        }
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
     * Handle a final message failure
     *
     * This method handles alerting for messages that have fully failed and
     * cannot be retried.
     *
     * @param Message $message
     * @param string $level
     * @param string $reason optional
     * @param \Throwable $e
     */
    public function failedMessage(Message $message, string $level, string $reason = null, \Throwable $e = null) {
        if (!$e instanceof JobRetryException) {
            $this->log(LogLevel::WARNING, "Message could not be handled [{job}]: {reason}", [
                'job' => $message->getPayloadType(),
                'level' => $level,
                'reason' => $reason,
            ]);
        }

        $this->fire('failedMessage', [$message, $level, $reason, $e]);
    }

    /**
     * Log severe problems
     *
     * @param \Throwable $ex
     */
    public function logException(\Throwable $ex) {
        $errorFormat = "PHP {levelString}: {message} in {file} on line {line}";

        // Log locally
        $level = $this->phpErrorLevel($ex->getCode());
        $this->log($level, $errorFormat, [
            'level' => $level,
            'levelString' => ucfirst($level),
            'message' => $ex->getMessage(),
            'file' => $ex->getFile(),
            'line' => $ex->getLine(),
        ]);

        // Fire hookable log event
        $this->fire('fatalError', [[
            'level' => $level,
            'message' => $ex->getMessage(),
            'file' => $ex->getFile(),
            'line' => $ex->getLine(),
        ]]);
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

}
