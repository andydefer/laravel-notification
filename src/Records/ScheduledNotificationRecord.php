<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelNotification\Enums\NotificationScheduleType;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class ScheduledNotificationRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $signature,
        public readonly string $taskClass,
        public readonly NotificationScheduleType $type,
        public readonly string $notifiable_type,
        public readonly int $notifiable_id,
        public readonly NotificationMessageVO $message,
        public readonly ?StringTypedCollection $channels = null,
        public readonly ?DateTimeVO $scheduled_at = null,
        public readonly ?int $delay_seconds = null,
        public readonly ?int $interval_seconds = null,
        public readonly ?DateTimeVO $end_at = null,
        public readonly int $execution_count = 0,
        public readonly int $success_count = 0,
        public readonly int $failure_count = 0,
        public readonly ?DateTimeVO $last_run_at = null,
        public readonly ?DateTimeVO $next_run_at = null,
        public readonly bool $is_active = true,
    ) {}
}
