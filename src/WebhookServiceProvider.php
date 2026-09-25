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

            $queue = self::configString($config, 'webhook.queue', 'default');

            return new WebhookDispatcher(
                $this->app->make(QueueInterface::class),
                $queue,
                $config->get('webhook.timestamped', false) === true,
            );
        });

        $this->app->bind(VerifyWebhookSignatureMiddleware::class, function (): VerifyWebhookSignatureMiddleware {
            $config = $this->app->make(ConfigInterface::class);

            $secret = self::configString($config, 'webhook.secret', '');
            $header = self::configString($config, 'webhook.signature_header', 'X-Webhook-Signature');

            $tolerance = $config->get('webhook.tolerance', 0);

            return new VerifyWebhookSignatureMiddleware(
                $this->app->make(WebhookSigner::class),
                $secret,
                $header,
                is_int($tolerance) && $tolerance > 0 ? $tolerance : null,
            );
        });
    }

    /**
     * @return void
     */
    public function boot(): void
    {
    }

    /**
     * Read a string config value, falling back to $default when it is missing or not a string.
     *
     * @param ConfigInterface $config
     * @param string          $key
     * @param string          $default
     *
     * @return string
     */
    private static function configString(ConfigInterface $config, string $key, string $default): string
    {
        $value = $config->get($key, $default);

        return is_string($value) ? $value : $default;
    }
}
