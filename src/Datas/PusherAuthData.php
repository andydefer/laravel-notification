<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

final class PusherAuthData extends AbstractData
{
    public function __construct(
        public readonly string $auth,
        public readonly ?string $channelData = null,
    ) {}
}
