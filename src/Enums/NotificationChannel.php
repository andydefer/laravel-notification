<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Enums;

use AndyDefer\LaravelNotification\Drivers\DatabaseDriver;
use AndyDefer\LaravelNotification\Drivers\MailDriver;
use AndyDefer\LaravelNotification\Drivers\PushDriver;
use AndyDefer\LaravelNotification\Drivers\SlackDriver;
use AndyDefer\LaravelNotification\Drivers\SmsDriver;
use AndyDefer\LaravelNotification\Drivers\TelegramDriver;
use AndyDefer\LaravelNotification\Drivers\WhatsAppDriver;

enum NotificationChannel: string
{
    case MAIL = 'mail';
    case DATABASE = 'database';
    case SMS = 'sms';
    case WHATSAPP = 'whatsapp';
    case SLACK = 'slack';
    case TELEGRAM = 'telegram';
    case PUSH = 'push';

    public function getLabel(): string
    {
        return match ($this) {
            self::MAIL => 'Email',
            self::DATABASE => 'Base de données',
            self::SMS => 'SMS',
            self::WHATSAPP => 'WhatsApp',
            self::SLACK => 'Slack',
            self::TELEGRAM => 'Telegram',
            self::PUSH => 'Push Notification',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::MAIL => '📧',
            self::DATABASE => '💾',
            self::SMS => '📱',
            self::WHATSAPP => '💬',
            self::SLACK => '💼',
            self::TELEGRAM => '✈️',
            self::PUSH => '🔔',
        };
    }

    public function requiresConfiguration(): bool
    {
        return match ($this) {
            self::MAIL, self::SMS, self::WHATSAPP, self::SLACK, self::TELEGRAM => true,
            self::DATABASE, self::PUSH => false,
        };
    }

    public function isEnabled(): bool
    {
        return match ($this) {
            self::MAIL, self::DATABASE => true,
            default => config("notification.channels.{$this->value}.enabled", false),
        };
    }

    /**
     * Get the driver class name for this channel.
     *
     * @return class-string
     */
    public function getDriverClass(): string
    {
        return match ($this) {
            self::MAIL => MailDriver::class,
            self::DATABASE => DatabaseDriver::class,
            self::SMS => SmsDriver::class,
            self::WHATSAPP => WhatsAppDriver::class,
            self::SLACK => SlackDriver::class,
            self::TELEGRAM => TelegramDriver::class,
            self::PUSH => PushDriver::class,
        };
    }
}
