<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\ProductQueue\Worker;

use Vanilla\ProductQueue\Message\Message;

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
     * Dependency Injection Container (original)
     * @var \Garden\Container\Container
     */
    protected $workerDI;

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
                $this->log(LogLevel::NOTICE, " updated queues for slot {$this->slot}: {$update}");
                $this->slotQueues = $update;
            }
        }
    }

    /**
     * Run worker instance
     *
     * This method runs the product queue processor worker.
     */
    public function run($workerConfig) {

        // Prepare sync

        $this->lastSync = 0;
        $this->syncFrequency = $this->config->get('queue.oversight.adjust', 5);

        // Prepare execution environment

        $this->iterations = $this->config->get('process.max_requests', 0);
        $this->retries = $this->config->get('process.max_retries', 0);

        // Prepare slot

        $this->slot = $workerConfig['slot'];

        $this->log(LogLevel::NOTICE, "Product worker started (slot {slot})", [
            'slot' => $this->slot
        ]);

        // Connect to queues and cache
        $this->prepareWorker();

        // Get jobs and do them.
        $idleSleep = $this->config->get('process.sleep') * 1000;
        while ($this->isReady()) {

            // Potentially update
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
            'nohang' => true
        ]);

        // No message? Return false immediately so worker can rest
        if (empty($messages) || !is_array($messages)) {
            return false;
        }

        // GetJob returns an array of messages
        foreach ($messages as $rawMessage) {
            $message = $this->handleMessage($rawMessage);
            $messageStatus = $message->getStatus();

            // Message handled, ACK
            if ($messageStatus == Message::STATUS_COMPLETE) {
                $this->queue->ackJob($message->getID());
                continue;
            }

            // Message failed but should be retried, NACK
            if ($messageStatus == Message::STATUS_RETRY) {
                $this->queue->nack($message->getID());
                continue;
            }
        }

        return true;
    }

    /**
     * Handle a queue message
     *
     * @param array $rawMessage
     * @return Message
     */
    public function handleMessage($rawMessage) {

        // Got a message, so decrement iterations
        $this->iterations--;

        $this->log(LogLevel::CRITICAL, " got message from queue");
        $this->log(LogLevel::CRITICAL, print_r($rawMessage, true));

        // Get message object
        $message = $this->parser->decodeMessage($rawMessage);

        // Clone DI to prevent pollution
        $this->workerDI = clone $this->di;
        $this->workerDI->setInstance(Container::class, $this->workerDI);

        // Convert message to runnable job
        $job = $this->getJob($message);

        // No job could be found to handle this message
        if (!$job) {
            $message->setStatus(Message::STATUS_MISMATCH);
            return $message;
        }

        sleep(10);
    }

    /**
     * Get job for message
     *
     * @param Message $message
     * @return JobInterface
     */
    public function getJob(Message $message) {
        $payloadType = $message->getPayloadType();

        // Lookup job payload


        // Create job instance
    }

}