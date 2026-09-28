<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Enums;

/**
 * Result of a ping/pong exchange with a device.
 */
enum PingStatus: string
{
    case PONG = 'pong';
    case INVALID = 'invalid';

    public function isPong(): bool
    {
        return $this === self::PONG;
    }

    public function isInvalid(): bool
    {
        return $this === self::INVALID;
    }
}
