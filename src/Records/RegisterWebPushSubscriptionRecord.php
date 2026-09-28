<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class RegisterWebPushSubscriptionRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $endpoint,
        public readonly string $p256dh,
        public readonly string $auth,
        public readonly ?string $browser = null,
        public readonly ?string $user_agent = null,
    ) {}
}
