<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class SessionStatsRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $session_id,
        public readonly int $total,
        public readonly int $sent,
        public readonly int $failed,
        public readonly int $pending,
    ) {}
}
