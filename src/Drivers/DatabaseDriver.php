<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\DatabaseConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

final class DatabaseDriver extends AbstractDriver
{
    public function __construct(
        private readonly DatabaseConfigRecord $config,
    ) {}

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        return true;
    }

    public function getChannel(): string
    {
        return 'database';
    }

    public function validateConfiguration(): bool
    {
        return $this->config->table !== '';
    }
}
