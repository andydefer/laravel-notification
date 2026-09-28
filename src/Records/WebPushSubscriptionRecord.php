<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class WebPushSubscriptionRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $endpoint = null,
        public readonly ?string $p256dh = null,
        public readonly ?string $auth = null,
        public readonly ?string $browser = null,
        public readonly ?string $user_agent = null,
        public readonly ?string $last_seen_at = null,
        public readonly ?string $notifiable_type = null,
        public readonly ?string $notifiable_id = null,
    ) {}
}
