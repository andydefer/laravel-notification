<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Channels;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Drivers\WebPushDriver;

/**
 * Channel implementation for Web Push notifications using the W3C Push API
 * with VAPID authentication.
 *
 * Delegates configuration lookup to {@see NotificationConfig} and instantiates
 * the {@see WebPushDriver} responsible for delivering the payload to the
 * subscription endpoint.
 */
final class WebPushChannel extends AbstractChannel
{
    /**
     * Return the channel identifier used across the notification system.
     *
     * @return string The channel key, e.g. "webpush"
     */
    public function getName(): string
    {
        return 'webpush';
    }

    /**
     * Return the human-readable label shown in UIs and diagnostics.
     *
     * @return string The display label
     */
    public function getLabel(): string
    {
        return 'Web Push (VAPID)';
    }

    /**
     * Return the icon representing the channel in UIs.
     *
     * @return string A short emoji or icon string
     */
    public function getIcon(): string
    {
        return '🌐';
    }

    /**
     * Indicate whether the Web Push channel is currently enabled by configuration.
     *
     * @return bool True when the channel is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config->isWebPushEnabled();
    }

    /**
     * Return the channel configuration record.
     *
     * @return AbstractRecord The Web Push configuration record
     */
    public function getConfig(): AbstractRecord
    {
        return $this->config->getWebPushConfig();
    }

    /**
     * Build the driver responsible for delivering Web Push notifications.
     *
     * @return AbstractDriver A ready-to-use {@see WebPushDriver} instance
     */
    public function createDriver(): AbstractDriver
    {
        return new WebPushDriver($this->config->getWebPushConfig());
    }

    /**
     * Determine whether the given destination is a valid Web Push endpoint.
     *
     * A valid endpoint must be a URL using either the HTTP or HTTPS scheme.
     *
     * @param  string  $destination  The endpoint to validate
     * @return bool True when the destination is a valid endpoint URL
     */
    public static function validateDestination(string $destination): bool
    {
        return filter_var($destination, FILTER_VALIDATE_URL) !== false
            && (
                str_starts_with($destination, 'https://')
                || str_starts_with($destination, 'http://')
            );
    }
}
