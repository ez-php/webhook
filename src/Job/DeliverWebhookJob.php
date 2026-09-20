<?php

declare(strict_types=1);

namespace EzPhp\Webhook\Job;

use EzPhp\HttpClient\Http;
use EzPhp\Queue\Job;
use EzPhp\Webhook\WebhookException;
use EzPhp\Webhook\WebhookSigner;

/**
 * Class DeliverWebhookJob
 *
 * Queue job that HMAC-signs a JSON payload and POSTs it to a subscriber URL.
 * Retries via the standard ez-php/queue Job mechanism (getMaxTries()); any
 * non-2xx response or transport failure throws, so the Worker re-queues the
 * job until $maxTries is exhausted.
 *
 * @package EzPhp\Webhook\Job
 */
final class DeliverWebhookJob extends Job
{
    protected int $maxTries = 5;

    protected array $backoff = [10, 30, 60, 300];

    /**
     * DeliverWebhookJob Constructor
     *
     * @param string               $url          Destination URL to deliver the webhook to.
     * @param array<string, mixed> $payload      Payload to JSON-encode and sign.
     * @param string               $secret       Shared signing secret for this subscriber.
     * @param string               $signatureHeader Header name the signature is sent under.
     * @param string               $queue        Queue to dispatch this job to.
     */
    public function __construct(
        private readonly string $url,
        private readonly array $payload,
        private readonly string $secret,
        private readonly string $signatureHeader = 'X-Webhook-Signature',
        string $queue = 'default',
    ) {
        $this->queue = $queue;
    }

    /**
     * Sign and deliver the payload.
     *
     * @return void
     *
     * @throws WebhookException When the response status is not 2xx.
     */
    public function handle(): void
    {
        $body = (string) json_encode($this->payload, JSON_THROW_ON_ERROR);
        $signature = (new WebhookSigner())->sign($body, $this->secret);

        $response = Http::post($this->url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader($this->signatureHeader, $signature)
            ->withBody($body)
            ->send();

        if ($response->status() < 200 || $response->status() >= 300) {
            throw new WebhookException(sprintf(
                'Webhook delivery to "%s" failed with status %d.',
                $this->url,
                $response->status(),
            ));
        }
    }
}
