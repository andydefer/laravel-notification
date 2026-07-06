<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class WhatsAppConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly string $driver = 'meta',
        public readonly ?string $access_token = null,
        public readonly ?string $phone_number_id = null,
    ) {}
}
