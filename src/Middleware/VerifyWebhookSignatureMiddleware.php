<?php

declare(strict_types=1);

namespace EzPhp\Webhook\Middleware;

use EzPhp\Contracts\MiddlewareInterface;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use EzPhp\Webhook\WebhookSigner;

/**
 * Class VerifyWebhookSignatureMiddleware
 *
 * Verifies an incoming webhook's HMAC signature before letting the request
 * reach the route handler. Rejects with HTTP 401 when the signature header
 * is missing or does not match the raw request body.
 *
 * Replay protection is opt-in: pass `$toleranceSeconds` and the sender must also supply a
 * timestamp header; the signature then covers `"{timestamp}.{body}"` and deliveries whose
 * timestamp is outside the tolerance window are rejected.
 *
 * @package EzPhp\Webhook\Middleware
 */
final readonly class VerifyWebhookSignatureMiddleware implements MiddlewareInterface
{
    /**
     * VerifyWebhookSignatureMiddleware Constructor
     *
     * @param WebhookSigner $signer
     * @param string        $secret          Shared signing secret to verify against.
     * @param string        $signatureHeader Header name the signature is expected under.
     * @param int|null      $toleranceSeconds Enables timestamped signatures when set (max clock difference).
     * @param string        $timestampHeader Header name the Unix timestamp is expected under.
     */
    public function __construct(
        private WebhookSigner $signer,
        private string $secret,
        private string $signatureHeader = 'X-Webhook-Signature',
        private ?int $toleranceSeconds = null,
        private string $timestampHeader = 'X-Webhook-Timestamp',
    ) {
    }

    /**
     * @param RequestInterface $request
     * @param callable         $next
     *
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $signature = $request->header($this->signatureHeader);

        if (!is_string($signature) || $signature === '') {
            return new Response('Missing webhook signature.', 401);
        }

        if ($this->toleranceSeconds === null) {
            if (!$this->signer->verify($request->rawBody(), $signature, $this->secret)) {
                return new Response('Invalid webhook signature.', 401);
            }
        } else {
            $timestamp = $request->header($this->timestampHeader);

            if (!is_string($timestamp) || preg_match('/^\d{1,15}$/', $timestamp) !== 1) {
                return new Response('Missing webhook timestamp.', 401);
            }

            if (!$this->signer->verifyWithTimestamp($request->rawBody(), $signature, $this->secret, (int) $timestamp, $this->toleranceSeconds)) {
                return new Response('Invalid or expired webhook signature.', 401);
            }
        }

        /** @var ResponseInterface $response */
        $response = $next($request);

        return $response;
    }
}
