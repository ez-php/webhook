<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Webhook\WebhookSigner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * @package Tests
 */
#[CoversClass(WebhookSigner::class)]
final class WebhookSignerTest extends BaseTestCase
{
    private WebhookSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new WebhookSigner();
    }

    public function test_sign_produces_hex_encoded_hmac(): void
    {
        $signature = $this->signer->sign('{"event":"created"}', 'secret');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $signature);
        $this->assertSame(hash_hmac('sha256', '{"event":"created"}', 'secret'), $signature);
    }

    public function test_verify_accepts_matching_signature(): void
    {
        $payload = '{"event":"created"}';
        $signature = $this->signer->sign($payload, 'secret');

        $this->assertTrue($this->signer->verify($payload, $signature, 'secret'));
    }

    public function test_verify_rejects_wrong_secret(): void
    {
        $payload = '{"event":"created"}';
        $signature = $this->signer->sign($payload, 'secret');

        $this->assertFalse($this->signer->verify($payload, $signature, 'wrong-secret'));
    }

    public function test_verify_rejects_tampered_payload(): void
    {
        $payload = '{"event":"created"}';
        $signature = $this->signer->sign($payload, 'secret');

        $this->assertFalse($this->signer->verify('{"event":"deleted"}', $signature, 'secret'));
    }

    public function test_verify_rejects_garbage_signature(): void
    {
        $this->assertFalse($this->signer->verify('payload', 'not-a-signature', 'secret'));
    }
}
