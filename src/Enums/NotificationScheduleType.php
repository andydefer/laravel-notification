<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Enums;

enum NotificationScheduleType: string
{
    case IMMEDIATE = 'immediate';
    case DELAYED = 'delayed';
    case SCHEDULED = 'scheduled';
    case RECURRING = 'recurring';

    public function getLabel(): string
    {
        return match ($this) {
            self::IMMEDIATE => 'Immédiate',
            self::DELAYED => 'Différée',
            self::SCHEDULED => 'Programmée',
            self::RECURRING => 'Récurrente',
        };
    }

    public function isDelayed(): bool
    {
        return in_array($this, [self::DELAYED, self::SCHEDULED, self::RECURRING]);
    }
}
