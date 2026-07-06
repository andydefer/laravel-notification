<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class SmsConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly string $driver = 'twilio',
        public readonly ?string $sid = null,
        public readonly ?string $token = null,
        public readonly ?string $from = null,
    ) {}
}
