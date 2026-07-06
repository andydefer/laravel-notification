<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Channels;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Drivers\TelegramDriver;
use AndyDefer\LaravelNotification\Records\TelegramConfigRecord;

final class TelegramChannel extends AbstractChannel
{
    public function getName(): string
    {
        return 'telegram';
    }

    public function getLabel(): string
    {
        return 'Telegram';
    }

    public function getIcon(): string
    {
        return '✈️';
    }

    public function isEnabled(): bool
    {
        return $this->configRepository->get('notification.channels.telegram.enabled', false);
    }

    public function getConfig(): AbstractRecord
    {
        $config = $this->configRepository->get('notification.channels.telegram', [
            'enabled' => false,
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
        ]);

        return TelegramConfigRecord::from($config);
    }

    public function createDriver(): AbstractDriver
    {
        /** @var TelegramConfigRecord $config */
        $config = $this->getConfig();

        return new TelegramDriver($config);
    }

    public static function validateDestination(string $destination): bool
    {
        return ! empty($destination) && is_numeric($destination);
    }
}
