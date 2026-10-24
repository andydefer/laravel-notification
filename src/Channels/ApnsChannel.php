<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Channels;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Drivers\ApnsDriver;
use AndyDefer\LaravelNotification\Records\ApnsConfigRecord;

/**
 * Canal APNS (Apple Push Notification service) — À IMPLÉMENTER.
 *
 * @deprecated Le driver sous-jacent n'est pas encore implémenté.
 */
final class ApnsChannel extends AbstractChannel
{
    public function getName(): string
    {
        return 'apns';
    }

    public function getLabel(): string
    {
        return 'Apple Push Notification';
    }

    public function getIcon(): string
    {
        return '🍎';
    }

    public function isEnabled(): bool
    {
        return $this->config->getApnsConfig()->enabled;
    }

    public function getConfig(): AbstractRecord
    {
        return $this->config->getApnsConfig();
    }

    public function createDriver(): AbstractDriver
    {
        /** @var ApnsConfigRecord $config */
        $config = $this->getConfig();

        return new ApnsDriver($config);
    }

    public static function validateDestination(string $destination): bool
    {
        // Un device token APNS est une string hex de 64 caractères
        return ! empty($destination) && strlen($destination) === 64;
    }
}
