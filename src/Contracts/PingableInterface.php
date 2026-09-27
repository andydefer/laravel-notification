<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts;

use AndyDefer\LaravelNotification\Enums\PingStatus;

/**
 * Contract for notifiables that can be pinged to verify their
 * device/token is still valid.
 */
interface PingableInterface
{
    /**
     * Send a ping to this notifiable.
     *
     * Implementations should return:
     * - PingStatus::PONG when the notifiable is reachable.
     * - PingStatus::INVALID when the notifiable is definitively invalid
     *   (token unregistered, sender mismatch, etc.).
     * - PingStatus::UNREACHABLE when the outcome is unknown (network,
     *   FCM unavailable, etc.).
     */
    public function ping(): PingStatus;

    /**
     * Return true when the notifiable answers with a pong.
     */
    public function isAlive(): bool;

    /**
     * Ping the notifiable and delete it when it is definitively invalid.
     *
     * @return PingStatus The ping result
     */
    public function pingOrPrune(): PingStatus;
}
