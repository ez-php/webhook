<?php

declare(strict_types=1);

namespace Tests\Job;

use EzPhp\HttpClient\Http;
use EzPhp\Webhook\Job\DeliverWebhookJob;
use EzPhp\Webhook\WebhookException;
use EzPhp\Webhook\WebhookSigner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * @package Tests\Job
 */
#[CoversClass(DeliverWebhookJob::class)]
#[UsesClass(WebhookSigner::class)]
#[UsesClass(WebhookException::class)]
final class DeliverWebhookJobTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        Http::resetClient();
        parent::tearDown();
    }

    public function test_handle_posts_signed_payload(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $job = new DeliverWebhookJob('https://example.com/hook', ['event' => 'created'], 'secret');
        $job->handle();

        $expectedSignature = (new WebhookSigner())->sign((string) json_encode(['event' => 'created']), 'secret');

        Http::assertSent(function (string $method, string $url, array $headers, string $body) use ($expectedSignature): bool {
            return $method === 'POST'
                && $url === 'https://example.com/hook'
                && ($headers['X-Webhook-Signature'] ?? null) === $expectedSignature
                && $body === (string) json_encode(['event' => 'created']);
        });
        $this->addToAssertionCount(1);
    }

    public function test_handle_uses_custom_signature_header(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $job = new DeliverWebhookJob('https://example.com/hook', ['event' => 'created'], 'secret', 'X-Custom-Signature');
        $job->handle();

        Http::assertSent(function (string $method, string $url, array $headers): bool {
            return array_key_exists('X-Custom-Signature', $headers);
        });
        $this->addToAssertionCount(1);
    }

    public function test_handle_throws_on_non_2xx_response(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $job = new DeliverWebhookJob('https://example.com/hook', ['event' => 'created'], 'secret');

        $this->expectException(WebhookException::class);

        $job->handle();
    }

    public function test_default_max_tries_and_backoff_configured_for_retry(): void
    {
        $job = new DeliverWebhookJob('https://example.com/hook', [], 'secret');

        $this->assertSame(5, $job->getMaxTries());
    }

    public function test_default_queue_is_default(): void
    {
        $job = new DeliverWebhookJob('https://example.com/hook', [], 'secret');

        $this->assertSame('default', $job->getQueue());
    }

    public function test_custom_queue_is_honoured(): void
    {
        $job = new DeliverWebhookJob('https://example.com/hook', [], 'secret', 'X-Webhook-Signature', 'webhooks');

        $this->assertSame('webhooks', $job->getQueue());
    }

    public function test_timestamped_delivery_sends_timestamp_and_signs_it(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $job = new DeliverWebhookJob('https://example.com/hook', ['event' => 'created'], 'secret', timestamped: true);
        $job->handle();

        $body = (string) json_encode(['event' => 'created']);

        Http::assertSent(function (string $method, string $url, array $headers, string $sent) use ($body): bool {
            $timestamp = $headers['X-Webhook-Timestamp'] ?? null;

            return is_string($timestamp)
                && abs(time() - (int) $timestamp) <= 5
                && ($headers['X-Webhook-Signature'] ?? null) === (new WebhookSigner())->signWithTimestamp($body, 'secret', (int) $timestamp)
                && $sent === $body;
        });
        $this->addToAssertionCount(1);
    }

    public function test_default_delivery_sends_no_timestamp(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        (new DeliverWebhookJob('https://example.com/hook', [], 'secret'))->handle();

        Http::assertSent(fn (string $method, string $url, array $headers): bool => !array_key_exists('X-Webhook-Timestamp', $headers));
        $this->addToAssertionCount(1);
    }
}
