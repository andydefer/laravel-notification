<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Enums\FcmPlatform;

final class FcmDeviceFilterRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $device_id = null,
        public readonly ?string $token = null,
        public readonly ?FcmPlatform $platform = null,
        public readonly ?string $notifiable_type = null,
        public readonly ?string $notifiable_id = null,
        public readonly ?string $from_last_seen_at = null,
        public readonly ?string $to_last_seen_at = null,
    ) {}
}
