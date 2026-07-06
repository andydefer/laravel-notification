<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;
use AndyDefer\LaravelNotification\ValueObjects\UuidVO;

final class NotificationFilterRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?UuidVO $session_id = null,
        public readonly ?FqcnChannelVO $channel = null,
        public readonly ?string $destination = null,
        public readonly ?string $notifiable_type = null,
        public readonly ?int $notifiable_id = null,
        public readonly ?NotificationStatus $status = null,
        public readonly ?bool $read = null,
    ) {}
}
