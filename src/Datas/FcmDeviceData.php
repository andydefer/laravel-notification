<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\LaravelNotification\Enums\FcmPlatform;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;

final class FcmDeviceData extends AbstractData
{
    public function __construct(
        public readonly string $id,
        public readonly string $deviceId,
        public readonly string $token,
        public readonly ?FcmPlatform $platform,
        public readonly ?DateTimeZuluVO $lastSeenAt,
    ) {}
}
