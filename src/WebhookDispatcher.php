<?php

declare(strict_types=1);

namespace EzPhp\Webhook;

use EzPhp\Contracts\QueueInterface;
use EzPhp\Webhook\Job\DeliverWebhookJob;

/**
 * Class WebhookDispatcher
 *
 * Entry point for sending an outgoing webhook. Builds a DeliverWebhookJob and
 * pushes it onto the injected QueueInterface, so delivery (and retry on
 * failure) happens asynchronously via the queue Worker.
 *
 * @package EzPhp\Webhook
 */
final readonly class WebhookDispatcher
{
    /**
     * WebhookDispatcher Constructor
     *
     * @param QueueInterface $queue
     * @param string         $queueName Queue to push delivery jobs onto.
     * @param bool           $timestamped Send a timestamp header and sign `"{timestamp}.{body}"` (replay protection).
     */
    public function __construct(
        private QueueInterface $queue,
        private string $queueName = 'default',
        private bool $timestamped = false,
    ) {
    }

    /**
     * Queue a signed webhook for delivery.
     *
     * @param string               $url    Destination URL.
     * @param array<string, mixed> $payload Payload to JSON-encode and sign.
     * @param string               $secret Shared signing secret for this subscriber.
     * @param string               $signatureHeader Header name the signature is sent under.
     *
     * @return void
     */
    public function dispatch(
        string $url,
        array $payload,
        string $secret,
        string $signatureHeader = 'X-Webhook-Signature',
    ): void {
        $this->queue->push(new DeliverWebhookJob($url, $payload, $secret, $signatureHeader, $this->queueName, $this->timestamped));
    }
}
