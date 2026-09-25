<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Application\Application;
use EzPhp\Testing\ApplicationTestCase;
use EzPhp\Webhook\Middleware\VerifyWebhookSignatureMiddleware;
use EzPhp\Webhook\WebhookDispatcher;
use EzPhp\Webhook\WebhookServiceProvider;
use EzPhp\Webhook\WebhookSigner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Fixtures\InMemoryQueueServiceProvider;

/**
 * Wrong-typed config values fall back to the provider's defaults instead of
 * raising a TypeError (the provider used to trust them via @var casts).
 *
 * @package Tests
 */
#[CoversClass(WebhookServiceProvider::class)]
#[UsesClass(WebhookSigner::class)]
#[UsesClass(WebhookDispatcher::class)]
#[UsesClass(VerifyWebhookSignatureMiddleware::class)]
final class WebhookProviderConfigTypesTest extends ApplicationTestCase
{
    protected function getBasePath(): string
    {
        $path = parent::getBasePath();
        file_put_contents(
            $path . '/config/webhook.php',
            "<?php return ['queue' => ['x'], 'secret' => 123, 'signature_header' => false];",
        );

        return $path;
    }

    protected function configureApplication(Application $app): void
    {
        $app->register(InMemoryQueueServiceProvider::class);
        $app->register(WebhookServiceProvider::class);
    }

    public function test_wrong_typed_values_still_resolve_both_services(): void
    {
        self::assertInstanceOf(WebhookDispatcher::class, $this->app()->make(WebhookDispatcher::class));
        self::assertInstanceOf(
            VerifyWebhookSignatureMiddleware::class,
            $this->app()->make(VerifyWebhookSignatureMiddleware::class),
        );
    }
}
