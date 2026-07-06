<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Channels;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Drivers\WhatsAppDriver;
use AndyDefer\LaravelNotification\Records\WhatsAppConfigRecord;

final class WhatsAppChannel extends AbstractChannel
{
    public function getName(): string
    {
        return 'whatsapp';
    }

    public function getLabel(): string
    {
        return 'WhatsApp';
    }

    public function getIcon(): string
    {
        return '💬';
    }

    public function isEnabled(): bool
    {
        return $this->configRepository->get('notification.channels.whatsapp.enabled', false);
    }

    public function getConfig(): AbstractRecord
    {
        $config = $this->configRepository->get('notification.channels.whatsapp', [
            'enabled' => false,
            'driver' => 'meta',
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        ]);

        return WhatsAppConfigRecord::from($config);
    }

    public function createDriver(): AbstractDriver
    {
        /** @var WhatsAppConfigRecord $config */
        $config = $this->getConfig();

        return new WhatsAppDriver($config);
    }

    public static function validateDestination(string $destination): bool
    {
        return preg_match('/^\+[0-9]{10,15}$/', $destination) === 1;
    }
}
