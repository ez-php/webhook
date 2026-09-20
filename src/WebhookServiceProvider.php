<?php

declare(strict_types=1);

namespace EzPhp\Webhook;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\QueueInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Webhook\Middleware\VerifyWebhookSignatureMiddleware;

/**
 * Class WebhookServiceProvider
 *
 * Reads `config/webhook.php` and binds `WebhookSigner` and `WebhookDispatcher`
 * into the container. Requires `QueueInterface` to already be bound (e.g. via
 * `ez-php/queue`'s `QueueServiceProvider`, registered first).
 *
 * @package EzPhp\Webhook
 */
final class WebhookServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(WebhookSigner::class, fn (): WebhookSigner => new WebhookSigner());

        $this->app->bind(WebhookDispatcher::class, function (): WebhookDispatcher {
            $config = $this->app->make(ConfigInterface::class);

            /** @var string $queue */
            $queue = $config->get('webhook.queue', 'default');

            return new WebhookDispatcher($this->app->make(QueueInterface::class), $queue);
        });

        $this->app->bind(VerifyWebhookSignatureMiddleware::class, function (): VerifyWebhookSignatureMiddleware {
            $config = $this->app->make(ConfigInterface::class);

            /** @var string $secret */
            $secret = $config->get('webhook.secret', '');
            /** @var string $header */
            $header = $config->get('webhook.signature_header', 'X-Webhook-Signature');

            return new VerifyWebhookSignatureMiddleware($this->app->make(WebhookSigner::class), $secret, $header);
        });
    }

    /**
     * @return void
     */
    public function boot(): void
    {
    }
}
