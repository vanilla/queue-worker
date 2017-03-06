<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Worker;

use Vanilla\ProductQueue\Message\Message;
use Vanilla\ProductQueue\Job\AbstractJob;
use Vanilla\ProductQueue\Job\JobStatus;
use Vanilla\ProductQueue\Job\JobInterface;

use Vanilla\ProductQueue\Exception\UnknownJobException;
use Vanilla\ProductQueue\Exception\BrokenMessageException;
use Vanilla\ProductQueue\Exception\BrokenJobException;

use Garden\Container\Container;

use Psr\Log\LogLevel;

/**
 * Product Worker
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @version 1.0
 */
class ProductWorker extends AbstractQueueWorker {

    /**
     * Worker slot
     * @var int
     */
    protected $slot;

    /**
     * How often to sync with distribution array
     * @var int
     */
    protected $syncFrequency;

    /**
     * Last sync with distribution array
     * @var int
     */
    protected $lastSync;

    /**
     * Number of iterations remaining
     * @var int
     */
    protected $iterations;

    /**
     * Number of per-job retries
     * @var int
     */
    protected $retries;

    /**
     * Slot queues
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
     * @param array $workerConfig
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
            'slot' => $this->slot
        ]);

        // Connect to queues and cache
        $this->prepareWorker();

        $this->fire('workerReady', [$this]);
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
     * @return bool
     */
    public function runIteration() {

        // Get job from slot queues
        $messages = $this->queue->getJob($this->slotQueues, [
            'nohang' => true,
            'withcounters' => true
        ]);

        // No message? Return false immediately so worker can rest
        if (empty($messages) || !is_array($messages)) {
            return false;
        }

        // GetJob returns an array of messages
        foreach ($messages as $rawMessage) {

            try {

                // Retrieve and parse message

                $message = $this->parseMessage($rawMessage);

                // Execute message payload

                $job = $this->handleMessage($message);

                // Determine status

                $jobStatus = $job->getStatus();

            } catch (UnknownJobException $ex) {

                // Unable to load job matching payload type

                $this->log(LogLevel::WARNING, "Unknown job: {job}", [
                    'job' => $ex->getJob()
                ]);
                $jobStatus = JobStatus::MISMATCH;

            } catch (BrokenJobException $ex) {

                // Payload type matches object that is not a valid job

                $this->log(LogLevel::WARNING, "Broken job: {job}", [
                    'job' => $ex->getJob()
                ]);
                $jobStatus = JobStatus::INVALID;

            } catch (BrokenMessageException $ex) {

                // Message could not be processed within the allotted retry count

                $this->log(LogLevel::WARNING, "Broken message: {reason}", [
                    'reason' => $ex->getMessage()
                ]);
                $jobStatus = JobStatus::ABANDONED;

            } catch (\Exception $ex) {

                // General exception

                $this->log(LogLevel::WARNING, "Exception: {exception}", [
                    'exception' => $ex->getMessage()
                ]);
                $jobStatus = JobStatus::RETRY;

            } finally {
                $message = $message ?? null;
                $job = $job ?? null;
                $jobStatus = $jobStatus ?? null;
            }

            $this->fire('endJob', [$message, $job, $jobStatus]);

            if (in_array($jobStatus, [
                JobStatus::INVALID,
                JobStatus::ABANDONED
            ])) {

                // Dead letter handler
                $this->failedMessage($message);

                // ACK failed message (will not retry)
                $this->queue->ackJob($message->getID());

            } else if ($jobStatus != JobStatus::COMPLETE) {

                // NACK failed message after attempt (will retry)
                $this->queue->nack($message->getID());

            } else {

                // ACK processed message
                $this->queue->ackJob($message->getID());

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

        $this->log(LogLevel::DEBUG, "Got message from queue");
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
     * @throws UnknownJobException
     * @return JobInterface
     */
    public function handleMessage(Message $message): JobInterface {

        // Check message integrity
        $this->validateMessage($message);

        // Clone DI to prevent pollution
        $workerDI = clone $this->di;
        $workerDI->setInstance(ContainerInterface::class, $workerDI);

        // Convert message to runnable job
        $job = $this->getJob($message, $workerDI);

        $this->log(LogLevel::NOTICE, "Resolved job: {job}", [
            'job' => $job->getName()
        ]);

        $this->fire('gotJob', [$job]);

        // Setup job
        $job->setup();

        // Run job
        $job->run();

        // Teardown job
        $job->teardown();

        $this->fire('finishedJob', [$job]);

        return $job;
    }

    /**
     * Validate message
     *
     * @param Message $message
     * @throws BrokenMessageException
     */
    public function validateMessage(Message $message) {
        //$nacks = $message->getExtra('nacks');
        $deliveries = $message->getExtra('additional-deliveries');
        if ($deliveries > $this->retries) {
            throw new BrokenMessageException();
        }
    }

    /**
     * Get job for message
     *
     * @param Message $message
     * @return AbstractJob
     */
    public function getJob(Message $message, Container $workerDI): AbstractJob {
        $payloadType = $message->getPayloadType();

        // Check that the specified job exists
        if (!$workerDI->has($payloadType)) {
            throw new UnknownJobException($payloadType);
        }

        // Check that the job is legal
        if (!is_a($payloadType, 'Vanilla\ProductQueue\Job\JobInterface', true)) {
            throw new BrokenJobException($payloadType);
        }

        // Create job instance
        $job = $workerDI->get($payloadType);
        $job->setData($message->getBody());
        return $job;
    }

    /**
     * Handle a final message failure
     *
     * This method handles alerting for messages that have fully failed and
     * cannot be retried.
     *
     * @param Message $message
     */
    public function failedMessage(Message $message) {
        $this->fire('failedMessage', [$message]);
    }

}