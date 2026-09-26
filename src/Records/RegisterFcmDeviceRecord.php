<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Enums\FcmPlatform;

final class RegisterFcmDeviceRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $device_id,
        public readonly string $token,
        public readonly ?FcmPlatform $platform = null,
        public readonly ?string $user_agent = null,
    ) {}
}
