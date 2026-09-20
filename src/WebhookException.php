<?php

declare(strict_types=1);

namespace EzPhp\Webhook;

use EzPhp\Contracts\EzPhpException;

/**
 * Class WebhookException
 *
 * Thrown when a webhook cannot be signed, verified, or delivered.
 *
 * @package EzPhp\Webhook
 */
final class WebhookException extends EzPhpException
{
}
