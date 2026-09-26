<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class PusherAuthRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $socket_id,
        public readonly string $channel_name,
    ) {}
}
