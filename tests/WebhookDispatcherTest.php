<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Queue\Driver\InMemoryDriver;
use EzPhp\Webhook\Job\DeliverWebhookJob;
use EzPhp\Webhook\WebhookDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * @package Tests
 */
#[CoversClass(WebhookDispatcher::class)]
#[UsesClass(DeliverWebhookJob::class)]
final class WebhookDispatcherTest extends BaseTestCase
{
    public function test_dispatch_pushes_a_deliver_webhook_job_onto_the_default_queue(): void
    {
        $queue = new InMemoryDriver();
        $dispatcher = new WebhookDispatcher($queue);

        $dispatcher->dispatch('https://example.com/hook', ['event' => 'created'], 'secret');

        $this->assertSame(1, $queue->size('default'));

        $job = $queue->pop('default');
        $this->assertInstanceOf(DeliverWebhookJob::class, $job);
    }

    public function test_dispatch_pushes_onto_the_configured_queue(): void
    {
        $queue = new InMemoryDriver();
        $dispatcher = new WebhookDispatcher($queue, 'webhooks');

        $dispatcher->dispatch('https://example.com/hook', ['event' => 'created'], 'secret');

        $this->assertSame(0, $queue->size('default'));
        $this->assertSame(1, $queue->size('webhooks'));
    }
}
