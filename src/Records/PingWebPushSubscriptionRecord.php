<?php

// src/Records/PingWebPushSubscriptionRecord.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class PingWebPushSubscriptionRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $subscription_id,
    ) {}
}
