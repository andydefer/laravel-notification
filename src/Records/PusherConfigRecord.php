<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class PusherConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly ?string $app_id = null,
        public readonly ?string $key = null,
        public readonly ?string $secret = null,
        public readonly string $cluster = 'eu',
        public readonly bool $use_tls = true,
        public readonly int $timeout = 30,
        public readonly string $default_channel = 'notifications',
        public readonly string $user_channel_prefix = 'private-user-',
    ) {}
}
