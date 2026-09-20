<?php

declare(strict_types=1);

namespace EzPhp\Webhook;

/**
 * Class WebhookSigner
 *
 * Signs and verifies webhook payloads using HMAC-SHA256, the same primitive
 * ez-php/auth's JwtManager uses for token signatures.
 *
 * The signature is computed over the raw request body exactly as it will be
 * sent/received — callers must sign and verify against identical bytes (e.g.
 * the JSON-encoded payload string, not a re-encoded array), or verification
 * will fail on encoding differences that carry no security meaning.
 *
 * @package EzPhp\Webhook
 */
final class WebhookSigner
{
    /**
     * Compute the hex-encoded HMAC-SHA256 signature of a raw payload.
     *
     * @param string $payload Raw request body bytes.
     * @param string $secret  Shared signing secret.
     *
     * @return string Lowercase hex-encoded signature.
     */
    public function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify a payload against a previously computed signature.
     *
     * Uses a constant-time comparison to avoid leaking timing information
     * about the expected signature.
     *
     * @param string $payload   Raw request body bytes.
     * @param string $signature Hex-encoded signature to verify.
     * @param string $secret    Shared signing secret.
     *
     * @return bool
     */
    public function verify(string $payload, string $signature, string $secret): bool
    {
        return hash_equals($this->sign($payload, $secret), $signature);
    }
}
