<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts;

use AndyDefer\LaravelNotification\Enums\PingStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for ping/pong helpers that verify the reachability of a
 * notifiable target (a device, a subscription, etc.).
 *
 * Implementations are responsible for sending a lightweight notification
 * through the appropriate channel and interpreting the outcome as one of
 * the {@see PingStatus} values.
 */
interface PingPongInterface
{
    /**
     * Send a ping to the given notifiable.
     *
     * @param  Model&NotifiableInterface  $notifiable  The target to ping
     * @return PingStatus The outcome of the ping attempt
     */
    public function ping(Model $notifiable): PingStatus;

    /**
     * Determine whether the given notifiable answers with a pong.
     *
     * Convenience wrapper over {@see self::ping()} that reduces the
     * result to a boolean.
     *
     * @param  Model&NotifiableInterface  $notifiable  The target to ping
     * @return bool True when the target answers with a pong
     */
    public function isAlive(Model $notifiable): bool;

    /**
     * Ping the given notifiable and delete it when it is reported as invalid.
     *
     * @param  Model&NotifiableInterface  $notifiable  The target to ping
     * @return PingStatus The outcome of the ping attempt
     */
    public function pingOrPrune(Model $notifiable): PingStatus;
}
