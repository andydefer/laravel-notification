<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\ApnsConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use RuntimeException;

/**
 * Driver APNS (Apple Push Notification service) — À IMPLÉMENTER.
 *
 * ⚠️ Ce driver est un squelette. Il ne fait AUCUN envoi réel.
 *    Il doit être implémenté pour envoyer des notifications aux
 *    appareils Apple (iOS, iPadOS, macOS, watchOS, tvOS).
 *
 * Pour envoyer des notifications Android, utiliser
 * {@see FirebaseCloudMessagingDriver} qui est production-ready.
 *
 * Spécifications APNS à implémenter :
 *   - Authentification JWT (clé .p8) ou certificat .p12
 *   - Endpoint HTTP/2 : api.push.apple.com (prod) / api.sandbox.push.apple.com (dev)
 *   - Payload : {"aps": {"alert": {"title": "...", "body": "..."}}}
 *   - Taille max : 4 Ko
 *
 * @see https://developer.apple.com/documentation/usernotifications/sending-notification-requests-to-apns
 * @deprecated Squelette non implémenté — utilisera {@see FirebaseCloudMessagingDriver} pour Android.
 */
final class ApnsDriver extends AbstractDriver
{
    public function __construct(
        private readonly ApnsConfigRecord $config,
    ) {}

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        // ⚠️ À IMPLÉMENTER : envoi réel vers APNS
        throw new RuntimeException(
            'ApnsDriver is not implemented yet. '.
            'Use FirebaseCloudMessagingDriver for Android, or implement this driver for iOS.',
        );
    }

    public function getChannel(): string
    {
        return 'apns';
    }

    public function validateConfiguration(): bool
    {
        return $this->config->enabled
            && $this->config->key_path !== null
            && $this->config->key_id !== null
            && $this->config->team_id !== null
            && $this->config->bundle_id !== null;
    }
}
