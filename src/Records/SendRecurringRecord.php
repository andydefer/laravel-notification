<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;

final class SendRecurringRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $interval_seconds,
        public readonly NotificationDateTimeVO $start_at,
        public readonly ?NotificationDateTimeVO $end_at = null,
        public readonly FqcnChannelCollection $channels = new FqcnChannelCollection,
        public readonly ?int $limit_per_channel = null,
        public readonly MaxFailedAttemptsVO $max_attempts = new MaxFailedAttemptsVO(3),
    ) {}
}
