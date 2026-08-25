<?php

// src/Channels/MailChannel.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Channels;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Drivers\MailDriver;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;

final class MailChannel extends AbstractChannel
{
    public function getName(): string
    {
        return 'mail';
    }

    public function getLabel(): string
    {
        return 'Email';
    }

    public function getIcon(): string
    {
        return '📧';
    }

    public function isEnabled(): bool
    {
        return $this->config->getMailConfig()->enabled;
    }

    public function getConfig(): AbstractRecord
    {
        return $this->config->getMailConfig();
    }

    public function createDriver(): AbstractDriver
    {
        /** @var MailConfigRecord $config */
        $config = $this->getConfig();

        return new MailDriver($config);
    }

    public static function validateDestination(string $destination): bool
    {
        return filter_var($destination, FILTER_VALIDATE_EMAIL) !== false;
    }
}
