<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Channels;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Drivers\PushDriver;
use AndyDefer\LaravelNotification\Records\PushConfigRecord;

final class PushChannel extends AbstractChannel
{
    public function getName(): string
    {
        return 'push';
    }

    public function getLabel(): string
    {
        return 'Push Notification';
    }

    public function getIcon(): string
    {
        return '🔔';
    }

    public function isEnabled(): bool
    {
        return $this->config->getPushConfig()->enabled;
    }

    public function getConfig(): AbstractRecord
    {
        return $this->config->getPushConfig();
    }

    public function createDriver(): AbstractDriver
    {
        /** @var PushConfigRecord $config */
        $config = $this->getConfig();

        return new PushDriver($config);
    }

    public static function validateDestination(string $destination): bool
    {
        return ! empty($destination) && strlen($destination) > 10;
    }
}
