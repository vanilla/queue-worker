<?php

/**
 * @license Proprietary
 * @copyright 2009-2019 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Message\Parser;

use Vanilla\QueueWorker\Exception\InvalidMessageWorkerException;
use Vanilla\QueueWorker\Message\Message;

/**
 * NativeMessageParser
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class NativeMessageParser implements ParserInterface
{
    /**
     * Decode message from queue
     *
     * @param array $rawMessage
     *
     * @return \Vanilla\QueueWorker\Message\Message
     * @throws \Vanilla\QueueWorker\Exception\InvalidMessageWorkerException
     */
    public function decodeMessage(array $rawMessage): Message
    {
        $mandatoryFields = ['id','queue','nacks','additional-deliveries'];

        foreach ($mandatoryFields as $mandatoryField) {
            if (!isset($rawMessage[$mandatoryField])) {
                throw new InvalidMessageWorkerException(null, "Message cannot be processed. Missing mandatory field `{$mandatoryField}`");
            }
        }

        $id = $rawMessage['id'];
        $body = json_decode($rawMessage['body'], true);

        $headers = [
            'broker_queue' => $rawMessage['queue'],
            'broker_nacks' => $rawMessage['nacks'],
            'broker_additional-deliveries' => $rawMessage['additional-deliveries'],
        ];

        if (count($body) == 2) {
            // Get headers
            $headers = array_merge($headers, $body[0]);
            // Get body
            $body = $body[1];
        }

        return new Message($id, $headers, $body);
    }
}
