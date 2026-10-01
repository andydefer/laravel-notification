<?php

// src/Datas/PingData.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\LaravelNotification\Enums\PingStatus;

final class PingData extends AbstractData
{
    public function __construct(
        public readonly string $deviceId,
        public readonly PingStatus $status,
    ) {}
}
