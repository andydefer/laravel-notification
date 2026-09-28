<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class WebPushSubscriptionFilterRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $endpoint = null,
        public readonly ?string $browser = null,
        public readonly ?string $notifiable_type = null,
        public readonly ?string $notifiable_id = null,
        public readonly ?string $from_last_seen_at = null,
        public readonly ?string $to_last_seen_at = null,
    ) {}
}
