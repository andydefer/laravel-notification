<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts;

use AndyDefer\LaravelNotification\Records\SendResultRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

interface DriverInterface
{
    public function send(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): SendResultRecord;

    public function getChannel(): string;

    public function validateConfiguration(): bool;
}
