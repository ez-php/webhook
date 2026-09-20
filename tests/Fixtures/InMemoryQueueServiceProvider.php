<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use EzPhp\Contracts\QueueInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Queue\Driver\InMemoryDriver;

/**
 * Test-only provider binding QueueInterface to an in-memory driver, so
 * WebhookServiceProviderTest can resolve WebhookDispatcher without a real
 * queue backend.
 *
 * @package Tests\Fixtures
 */
final class InMemoryQueueServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(QueueInterface::class, fn (): QueueInterface => new InMemoryDriver());
    }

    /**
     * @return void
     */
    public function boot(): void
    {
    }
}
