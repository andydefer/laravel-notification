<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Traits;

use AndyDefer\LaravelNotification\Contracts\PingPongInterface;
use AndyDefer\LaravelNotification\Enums\PingStatus;
use AndyDefer\LaravelNotification\Helpers\PingPongAdapter;

/**
 * Provides the {@see PingableInterface} implementation on top of the
 * appropriate {@see PingPongInterface} helper resolved at runtime.
 *
 * The concrete helper is resolved through the {@see PingPongAdapter},
 * which maps the model class to its ping/pong helper.
 */
trait HasPingPong
{
    /**
     * Send a ping to this notifiable.
     */
    public function ping(): PingStatus
    {
        return $this->pingPong()->ping($this);
    }

    /**
     * Determine whether this notifiable answers with a pong.
     */
    public function isAlive(): bool
    {
        return $this->pingPong()->isAlive($this);
    }

    /**
     * Ping this notifiable and delete it when it is invalid.
     */
    public function pingOrPrune(): PingStatus
    {
        return $this->pingPong()->pingOrPrune($this);
    }

    /**
     * Resolve the ping/pong helper for the current model.
     */
    private function pingPong(): PingPongInterface
    {
        return app(PingPongAdapter::class)->for($this);
    }
}
