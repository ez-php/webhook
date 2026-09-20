# ez-php/webhook

Outgoing webhooks for the ez-php framework — HMAC-signed delivery as a retrying `ez-php/queue` job, plus middleware to verify incoming webhook signatures.

---

## Installation

```bash
composer require ez-php/webhook
```

Requires `ez-php/queue` (delivery is always queue-backed) and `ez-php/http-client` (outgoing HTTP requests).

---

## Quick Start

Register the provider in `provider/modules.php`, after `QueueServiceProvider`:

```php
use EzPhp\Webhook\WebhookServiceProvider;

$app->register(WebhookServiceProvider::class);
```

Add config values to `config/webhook.php`:

```php
return [
    'secret'            => getenv('WEBHOOK_SECRET') ?: '',
    'signature_header'  => getenv('WEBHOOK_SIGNATURE_HEADER') ?: 'X-Webhook-Signature',
    'queue'             => getenv('WEBHOOK_QUEUE') ?: 'default',

    // Replay protection (opt-in, see "Replay protection" below).
    'timestamped'       => filter_var(getenv('WEBHOOK_TIMESTAMPED'), FILTER_VALIDATE_BOOLEAN), // sender
    'tolerance'         => (int) (getenv('WEBHOOK_TOLERANCE') ?: 0),                            // receiver, seconds; 0 = off
];
```

### Sending an outgoing webhook

Inject `WebhookDispatcher` and dispatch — delivery happens on the queue, retried automatically on failure:

```php
use EzPhp\Webhook\WebhookDispatcher;

final class OrderController
{
    public function __construct(private readonly WebhookDispatcher $webhooks)
    {
    }

    public function store(): void
    {
        // ... create the order ...

        $this->webhooks->dispatch(
            url: $subscriber->webhookUrl,
            payload: ['event' => 'order.created', 'order_id' => $order->id],
            secret: $subscriber->webhookSecret,
        );
    }
}
```

Run `ez queue:work` (from `ez-php/queue`) to process deliveries.

### Verifying an incoming webhook

Wire `VerifyWebhookSignatureMiddleware` in front of the route that receives webhooks from a third party:

```php
use EzPhp\Webhook\Middleware\VerifyWebhookSignatureMiddleware;
use EzPhp\Webhook\WebhookSigner;

$router->post('/webhooks/incoming', [IncomingWebhookController::class, 'handle'])
    ->middleware(new VerifyWebhookSignatureMiddleware(new WebhookSigner(), secret: 'shared-secret'));
```

Or resolve it from the container (reads `webhook.secret`/`webhook.signature_header`) when `WebhookServiceProvider` is registered.

A request with a missing or invalid signature never reaches the controller — the middleware returns `401 Unauthorized` directly.

---

## Signing

`WebhookSigner` is a stateless HMAC-SHA256 helper — the same primitive `ez-php/auth`'s `JwtManager` uses for token signatures:

```php
$signer = new WebhookSigner();
$signature = $signer->sign($rawBody, $secret);        // lowercase hex string
$signer->verify($rawBody, $signature, $secret);        // bool, constant-time
```

Sign and verify against the exact same bytes (the raw JSON string, not a re-encoded array) — re-encoding can reorder keys or change whitespace and break verification even though the data is unchanged.

### Replay protection

A plain signature covers only the body, so a captured delivery can be re-sent later and still verify. Both sides can opt in to signing a timestamp too:

- **Sender:** `webhook.timestamped = true` (or `new WebhookDispatcher($queue, $queueName, timestamped: true)`) adds an `X-Webhook-Timestamp` header and signs `"{timestamp}.{body}"`. The timestamp is taken on every delivery attempt, so a retry after a long backoff is not stale.
- **Receiver:** `webhook.tolerance = 300` (or `new VerifyWebhookSignatureMiddleware($signer, $secret, toleranceSeconds: 300)`) requires the timestamp header, verifies `"{timestamp}.{body}"` and rejects deliveries more than `tolerance` seconds away from the server clock with `401`.

Both sides must agree — a timestamped sender talking to a plain receiver (or the reverse) fails verification. Timestamps only bound the replay window; deduplicate by event id if you need exactly-once handling.

---

## Retry Behaviour

`DeliverWebhookJob` extends `ez-php/queue`'s `Job` with `$maxTries = 5` and a `$backoff` of `[10, 30, 60, 300]` seconds. Any transport failure or non-2xx response throws, so the `Worker` re-queues the job with the next backoff delay until attempts are exhausted, then records it as permanently failed (via the queue driver's `failed()`/`FailedJobRepositoryInterface`, same as any other job).

---

## What This Module Does Not Do

- No subscription management UI or storage — applications own their own list of webhook subscribers/URLs/secrets and pass them to `WebhookDispatcher::dispatch()`.
- No webhook event catalog — `payload` is an arbitrary array the application defines.
- No delivery log/dashboard — inspect failures via `ez queue:failed` (`ez-php/queue`).
