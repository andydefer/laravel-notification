<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\ValueObjects\ErrorMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\UuidVO;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class NotificationRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?UuidVO $id = null,
        public readonly ?UuidVO $session_id = null,
        public readonly ?FqcnChannelVO $channel = null,
        public readonly ?string $destination = null,
        public readonly ?string $notifiable_type = null,
        public readonly ?int $notifiable_id = null,
        public readonly ?NotificationMessageVO $message = null,
        public readonly ?StrictDataObject $metadata = null, // ✅ NOUVEAU
        public readonly ?NotificationStatus $status = NotificationStatus::PENDING,
        public readonly ?ErrorMessageVO $error = null,
        public readonly ?DateTimeVO $sent_at = null,
        public readonly ?DateTimeVO $read_at = null,
        public readonly ?DateTimeVO $created_at = null,
        public readonly ?DateTimeVO $updated_at = null,
        public readonly ?DateTimeVO $deleted_at = null,
    ) {}
}
