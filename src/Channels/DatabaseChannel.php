<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Channels;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Drivers\DatabaseDriver;
use AndyDefer\LaravelNotification\Records\DatabaseConfigRecord;

final class DatabaseChannel extends AbstractChannel
{
    public function getName(): string
    {
        return 'database';
    }

    public function getLabel(): string
    {
        return 'Base de données';
    }

    public function getIcon(): string
    {
        return '💾';
    }

    public function isEnabled(): bool
    {
        return $this->configRepository->get('notification.channels.database.enabled', true);
    }

    public function getConfig(): AbstractRecord
    {
        $config = $this->configRepository->get('notification.channels.database', [
            'driver' => 'database',
            'table' => 'notifications',
        ]);

        return DatabaseConfigRecord::from($config);
    }

    public function createDriver(): AbstractDriver
    {
        /** @var DatabaseConfigRecord $config */
        $config = $this->getConfig();

        return new DatabaseDriver($config);
    }

    public static function validateDestination(string $destination): bool
    {
        return $destination === 'database';
    }
}
