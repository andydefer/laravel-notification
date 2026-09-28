<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class WebPushConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly ?string $subject = null,
        public readonly ?string $public_key = null,
        public readonly ?string $private_key = null,
        public readonly int $ttl = 3600,
        public readonly string $urgency = 'normal',
        public readonly string $topic = 'notification',
    ) {}
}
