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
        return true;
    }

    public function getConfig(): AbstractRecord
    {
        return $this->config->getDatabaseConfig();
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
