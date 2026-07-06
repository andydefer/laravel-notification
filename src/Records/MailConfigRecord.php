<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Drivers\MailDriver;

final class MailConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $enabled = true,
        public readonly string $driver = MailDriver::class,
        public readonly ?string $default_from = null,
        public readonly ?string $default_from_name = null,
    ) {}
}
