<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Enums;

enum NotificationStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case DELIVERED = 'delivered';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::SENT => 'Envoyé',
            self::FAILED => 'Échoué',
            self::DELIVERED => 'Délivré',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::SENT, self::FAILED, self::DELIVERED]);
    }
}
