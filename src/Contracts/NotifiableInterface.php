<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts;

use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;

interface NotifiableInterface
{
    public function getNotificationChannels(): NotificationRouteCollection;

    public function getMorphClass(): string;

    public function getKey(): int;
}
