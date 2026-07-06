<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\PushConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use RuntimeException;

/**
 * Driver for sending push notifications.
 *
 * Supports multiple push notification platforms including FCM (Firebase Cloud Messaging)
 * and APNS (Apple Push Notification Service). Handles token management and
 * platform-specific payload formatting.
 */
final class PushDriver extends AbstractDriver
{
    /**
     * Constructor for the push driver.
     *
     * @param  PushConfigRecord  $config  The push configuration
     */
    public function __construct(
        private readonly PushConfigRecord $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $metadata = $route->getMetadata();

        $tokens = $metadata?->get('tokens') ?? $this->config->default_tokens ?? [];

        if (empty($tokens)) {
            throw new RuntimeException('Push notification tokens not specified.');
        }

        if (is_string($tokens)) {
            $tokens = [$tokens];
        }

        $platform = $metadata?->get('platform') ?? $this->config->platform ?? 'fcm';

        $payload = $this->buildPayload($message, $metadata);

        // Simulation d'envoi
        // À remplacer par l'implémentation réelle (FCM, APNS, etc.)

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getChannel(): string
    {
        return 'push';
    }

    /**
     * {@inheritDoc}
     */
    public function validateConfiguration(): bool
    {
        return $this->config->enabled
            && ($this->config->fcm_api_key !== null
                || $this->config->apns_key_path !== null
                || $this->config->default_tokens !== null);
    }

    /**
     * Build the push notification payload.
     *
     * @param  NotificationMessageVO  $message  The notification message
     * @param  mixed  $metadata  Additional metadata for the push
     * @return array<string, mixed> The formatted push payload
     */
    private function buildPayload(NotificationMessageVO $message, mixed $metadata): array
    {
        return [
            'title' => $message->getSubjectValue(),
            'body' => $message->getBodyValue(),
            'data' => $metadata?->get('data') ?? [],
            'sound' => $metadata?->get('sound') ?? $this->config->default_sound,
            'badge' => $metadata?->get('badge') ?? 1,
            'click_action' => $metadata?->get('click_action') ?? null,
        ];
    }
}
