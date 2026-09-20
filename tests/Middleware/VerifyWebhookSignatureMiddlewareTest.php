<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Webhook\Middleware\VerifyWebhookSignatureMiddleware;
use EzPhp\Webhook\WebhookSigner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * @package Tests\Middleware
 */
#[CoversClass(VerifyWebhookSignatureMiddleware::class)]
#[UsesClass(WebhookSigner::class)]
final class VerifyWebhookSignatureMiddlewareTest extends BaseTestCase
{
    public function test_rejects_missing_signature_header(): void
    {
        $middleware = new VerifyWebhookSignatureMiddleware(new WebhookSigner(), 'secret');
        $request = new Request('POST', '/webhooks/incoming', rawBody: '{"event":"created"}');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(401, $response->status());
    }

    public function test_rejects_invalid_signature(): void
    {
        $middleware = new VerifyWebhookSignatureMiddleware(new WebhookSigner(), 'secret');
        $request = new Request(
            'POST',
            '/webhooks/incoming',
            rawBody: '{"event":"created"}',
            headers: ['x-webhook-signature' => 'not-a-valid-signature'],
        );

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(401, $response->status());
    }

    public function test_calls_next_when_signature_is_valid(): void
    {
        $signer = new WebhookSigner();
        $payload = '{"event":"created"}';
        $signature = $signer->sign($payload, 'secret');

        $middleware = new VerifyWebhookSignatureMiddleware($signer, 'secret');
        $request = new Request(
            'POST',
            '/webhooks/incoming',
            rawBody: $payload,
            headers: ['x-webhook-signature' => $signature],
        );

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(200, $response->status());
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('ok', $response->body());
    }

    public function test_uses_custom_signature_header(): void
    {
        $signer = new WebhookSigner();
        $payload = '{"event":"created"}';
        $signature = $signer->sign($payload, 'secret');

        $middleware = new VerifyWebhookSignatureMiddleware($signer, 'secret', 'X-Custom-Signature');
        $request = new Request(
            'POST',
            '/webhooks/incoming',
            rawBody: $payload,
            headers: ['x-custom-signature' => $signature],
        );

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(200, $response->status());
    }
}
