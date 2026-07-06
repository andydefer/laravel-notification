<?php

// Dans src/Records/DatabaseConfigRecord.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Drivers\DatabaseDriver;

final class DatabaseConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $driver = DatabaseDriver::class,
        public readonly string $table = 'notifications',
    ) {}
}
