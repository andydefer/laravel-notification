<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;

/**
 * Interface for notification channels.
 *
 * Defines the contract for all notification channels (Mail, SMS, Slack, etc.).
 * Each channel implementation must provide a driver, validation, and metadata.
 */
interface ChannelInterface
{
    /**
     * Get the unique name of the channel.
     *
     * @return string The channel name (e.g., 'mail', 'sms', 'slack')
     */
    public function getName(): string;

    /**
     * Get the human-readable label of the channel.
     *
     * @return string The channel label (e.g., 'Email', 'SMS', 'Slack')
     */
    public function getLabel(): string;

    /**
     * Get the icon identifier for the channel.
     *
     * @return string The icon name or identifier
     */
    public function getIcon(): string;

    /**
     * Check if the channel is enabled.
     *
     * @return bool True if the channel is enabled and ready to send notifications
     */
    public function isEnabled(): bool;

    /**
     * Create a driver instance for this channel.
     *
     * @return AbstractDriver The driver responsible for sending notifications
     */
    public function createDriver(): AbstractDriver;

    /**
     * Validate a destination address for this channel.
     *
     * @param  string  $destination  The destination to validate
     *                               (e.g., email address, phone number, webhook URL)
     * @return bool True if the destination is valid for this channel
     */
    public static function validateDestination(string $destination): bool;
}
