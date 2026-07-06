<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\ValueObjects\NotificationChannelVO;

final class NotificationDestinationRecord extends AbstractRecord
{
    public function __construct(
        public readonly NotificationChannelVO $channel,
        public readonly string $value,
        public readonly ?StrictDataObject $metadata = null,
    ) {}
}
