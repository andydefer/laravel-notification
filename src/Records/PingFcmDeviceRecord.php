<?php

// src/Records/PingFcmDeviceRecord.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class PingFcmDeviceRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $device_id,
    ) {}
}
