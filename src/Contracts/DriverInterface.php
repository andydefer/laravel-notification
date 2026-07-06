<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts;

use AndyDefer\LaravelNotification\Records\SendResultRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

/**
 * Interface for notification drivers.
 *
 * Defines the contract for all notification drivers that handle the actual
 * delivery of notifications through specific channels (Mail, SMS, Slack, etc.).
 */
interface DriverInterface
{
    /**
     * Send a notification message through the driver.
     *
     * @param  NotificationMessageVO  $message  The notification message to send
     * @param  NotificationRouteVO  $route  The route containing destination and configuration
     * @return SendResultRecord The result of the send operation
     */
    public function send(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): SendResultRecord;

    /**
     * Get the channel name this driver belongs to.
     *
     * @return string The channel name (e.g., 'mail', 'sms', 'slack')
     */
    public function getChannel(): string;

    /**
     * Validate the driver configuration.
     *
     * Checks if all required configuration values are present and valid
     * for the driver to function properly.
     *
     * @return bool True if the configuration is valid
     */
    public function validateConfiguration(): bool;
}
