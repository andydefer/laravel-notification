<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;

final class ProcessNotificationRecord extends AbstractRecord
{
    public function __construct(
        public readonly FqcnChannelCollection $channels = new FqcnChannelCollection,
        public readonly ?int $limit_per_channel = null,
    ) {}
}
