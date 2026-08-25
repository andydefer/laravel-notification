<?php

// src/Contracts/Configs/NotificationConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts\Configs;

use AndyDefer\LaravelNotification\Records\DatabaseConfigRecord;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;
use AndyDefer\LaravelNotification\Records\PushConfigRecord;
use AndyDefer\LaravelNotification\Records\SlackConfigRecord;
use AndyDefer\LaravelNotification\Records\SmsConfigRecord;
use AndyDefer\LaravelNotification\Records\TelegramConfigRecord;
use AndyDefer\LaravelNotification\Records\WhatsAppConfigRecord;

/**
 * Interface for notification kit configuration.
 *
 * Provides methods to retrieve notification configuration values
 * for various channels and logging settings.
 */
interface NotificationConfigInterface
{
    /**
     * Get the default notification channels.
     *
     * @return array<int, string> List of default channel names
     */
    public function getDefaultChannels(): array;

    /**
     * Get the mail channel configuration.
     *
     * @return MailConfigRecord The mail configuration
     */
    public function getMailConfig(): MailConfigRecord;

    /**
     * Get the database channel configuration.
     *
     * @return DatabaseConfigRecord The database configuration
     */
    public function getDatabaseConfig(): DatabaseConfigRecord;

    /**
     * Get the SMS channel configuration.
     *
     * @return SmsConfigRecord The SMS configuration
     */
    public function getSmsConfig(): SmsConfigRecord;

    /**
     * Get the Slack channel configuration.
     *
     * @return SlackConfigRecord The Slack configuration
     */
    public function getSlackConfig(): SlackConfigRecord;

    /**
     * Get the WhatsApp channel configuration.
     *
     * @return WhatsAppConfigRecord The WhatsApp configuration
     */
    public function getWhatsAppConfig(): WhatsAppConfigRecord;

    /**
     * Get the Telegram channel configuration.
     *
     * @return TelegramConfigRecord The Telegram configuration
     */
    public function getTelegramConfig(): TelegramConfigRecord;

    /**
     * Get the Push notification channel configuration.
     *
     * @return PushConfigRecord The Push configuration
     */
    public function getPushConfig(): PushConfigRecord;

    /**
     * Check if SMS channel is enabled.
     *
     * @return bool True if SMS is enabled, false otherwise
     */
    public function isSmsEnabled(): bool;

    /**
     * Check if WhatsApp channel is enabled.
     *
     * @return bool True if WhatsApp is enabled, false otherwise
     */
    public function isWhatsAppEnabled(): bool;

    /**
     * Check if Slack channel is enabled.
     *
     * @return bool True if Slack is enabled, false otherwise
     */
    public function isSlackEnabled(): bool;

    /**
     * Check if Telegram channel is enabled.
     *
     * @return bool True if Telegram is enabled, false otherwise
     */
    public function isTelegramEnabled(): bool;

    /**
     * Check if Push channel is enabled.
     *
     * @return bool True if Push is enabled, false otherwise
     */
    public function isPushEnabled(): bool;

    /**
     * Get the logging configuration.
     *
     * @return array<string, mixed> The logging configuration
     */
    public function getLoggingConfig(): array;

    /**
     * Check if logging is enabled.
     *
     * @return bool True if logging is enabled, false otherwise
     */
    public function isLoggingEnabled(): bool;

    /**
     * Get the list of enabled channels.
     *
     * @return array<int, string> List of enabled channel names
     */
    public function getEnabledChannels(): array;

    /**
     * Get the list of all available channels.
     *
     * @return array<int, string> List of all channel names
     */
    public function getAllChannels(): array;
}
