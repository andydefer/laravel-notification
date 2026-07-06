<?php

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
        return $this->configRepository->get('notification.channels.mail.enabled', true);
    }

    public function getConfig(): AbstractRecord
    {
        $config = $this->configRepository->get('notification.channels.mail', [
            'driver' => 'mail',
            'default_to' => env('MAIL_DEFAULT_TO'),
            'default_from' => env('MAIL_FROM_ADDRESS'),
            'default_from_name' => env('MAIL_FROM_NAME'),
        ]);

        return MailConfigRecord::from($config);
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
