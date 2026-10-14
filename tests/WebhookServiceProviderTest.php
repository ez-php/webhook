<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Application\Application;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Testing\ApplicationTestCase;
use EzPhp\Webhook\Middleware\VerifyWebhookSignatureMiddleware;
use EzPhp\Webhook\WebhookDispatcher;
use EzPhp\Webhook\WebhookException;
use EzPhp\Webhook\WebhookServiceProvider;
use EzPhp\Webhook\WebhookSigner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Fixtures\InMemoryQueueServiceProvider;

/**
 * Smoke test: WebhookServiceProvider registers its bindings in a minimal
 * application context without error.
 *
 * @package Tests
 */
#[CoversClass(WebhookServiceProvider::class)]
#[UsesClass(WebhookSigner::class)]
#[UsesClass(WebhookDispatcher::class)]
#[UsesClass(VerifyWebhookSignatureMiddleware::class)]
#[UsesClass(WebhookException::class)]
final class WebhookServiceProviderTest extends ApplicationTestCase
{
    /**
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
        $app->register(InMemoryQueueServiceProvider::class);
        $app->register(WebhookServiceProvider::class);
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function test_signer_is_bound(): void
    {
        $this->assertInstanceOf(WebhookSigner::class, $this->app()->make(WebhookSigner::class));
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function test_dispatcher_is_bound(): void
    {
        $this->assertInstanceOf(WebhookDispatcher::class, $this->app()->make(WebhookDispatcher::class));
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function test_verify_middleware_is_bound(): void
    {
        $this->assertInstanceOf(
            VerifyWebhookSignatureMiddleware::class,
            $this->app()->make(VerifyWebhookSignatureMiddleware::class),
        );
    }

    /**
     * Without `webhook.secret` the bound middleware must never let a request
     * through — not even one signed with the empty key, which anyone can compute.
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_verify_middleware_without_configured_secret_fails_closed(): void
    {
        $middleware = $this->app()->make(VerifyWebhookSignatureMiddleware::class);
        $payload = '{"event":"created"}';
        $request = new Request(
            'POST',
            '/webhooks/incoming',
            rawBody: $payload,
            headers: ['x-webhook-signature' => (new WebhookSigner())->sign($payload, '')],
        );

        $this->expectException(WebhookException::class);

        $middleware->handle($request, fn () => new Response('ok'));
    }
}
