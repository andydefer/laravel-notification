<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Channels;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Drivers\SmsDriver;
use AndyDefer\LaravelNotification\Records\SmsConfigRecord;

final class SmsChannel extends AbstractChannel
{
    public function getName(): string
    {
        return 'sms';
    }

    public function getLabel(): string
    {
        return 'SMS';
    }

    public function getIcon(): string
    {
        return '📱';
    }

    public function isEnabled(): bool
    {
        return $this->config->getSmsConfig()->enabled;
    }

    public function getConfig(): AbstractRecord
    {
        return $this->config->getSmsConfig();
    }

    public function createDriver(): AbstractDriver
    {
        /** @var SmsConfigRecord $config */
        $config = $this->getConfig();

        return new SmsDriver($config);
    }

    public static function validateDestination(string $destination): bool
    {
        return preg_match('/^\+[0-9]{10,15}$/', $destination) === 1;
    }
}
