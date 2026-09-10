<?php

// src/Datas/NotificationData.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;

/**
 * Data Transfer Object for a notification.
 *
 * Represents a notification in a serializable format suitable for API responses.
 */
final class NotificationData extends AbstractData
{
    public function __construct(
        public readonly string $id,
        public readonly string $sessionId,
        public readonly string $channel,
        public readonly string $destination,
        public readonly string $notifiableType,
        public readonly int $notifiableId,
        public readonly StrictAssociative $message,
        public readonly ?StrictAssociative $metadata,
        public readonly NotificationStatus $status,
        public readonly ?string $error,
        public readonly ?DateTimeZuluVO $sentAt,
        public readonly ?DateTimeZuluVO $readAt,
        public readonly DateTimeZuluVO $createdAt,
        public readonly ?DateTimeZuluVO $updatedAt,
    ) {}
}
