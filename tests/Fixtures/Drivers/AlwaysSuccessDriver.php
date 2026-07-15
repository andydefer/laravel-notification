<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Fixtures\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

final class AlwaysSuccessDriver extends AbstractDriver
{
    public function getChannel(): string
    {
        return 'test';
    }

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        return true;
    }
}
