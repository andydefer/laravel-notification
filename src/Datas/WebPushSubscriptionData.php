<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;

/**
 * Data transfer object representing a Web Push subscription as exposed
 * through the public API.
 *
 * This DTO is immutable and only carries the fields that are safe to
 * expose to clients. Internal details such as the cryptographic keys
 * (`p256dh`, `auth`) or the notifiable owner are intentionally omitted.
 */
final class WebPushSubscriptionData extends AbstractData
{
    /**
     * @param  string  $id  The subscription identifier
     * @param  string  $endpoint  The Web Push endpoint URL
     * @param  string|null  $browser  The browser that owns the subscription, when known
     * @param  DateTimeZuluVO|null  $lastSeenAt  Last successful ping timestamp
     */
    public function __construct(
        public readonly string $id,
        public readonly string $endpoint,
        public readonly ?string $browser,
        public readonly ?DateTimeZuluVO $lastSeenAt,
    ) {}
}
