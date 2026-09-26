<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Channels;

use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Contracts\Configs\NotificationConfigInterface;
use AndyDefer\LaravelNotification\Drivers\FirebaseCloudMessagingDriver;

final class FirebaseCloudMessagingChannel extends AbstractChannel
{
    public function __construct(
        NotificationConfigInterface $config,
    ) {
        parent::__construct($config);
    }

    public function getName(): string
    {
        return 'firebase';
    }

    public function getLabel(): string
    {
        return 'Firebase Cloud Messaging';
    }

    public function getIcon(): string
    {
        return '🔥';
    }

    public function isEnabled(): bool
    {
        return $this->config->isFirebaseEnabled();
    }

    public static function validateDestination(string $destination): bool
    {
        // FCM registration tokens are long opaque strings.
        $length = strlen($destination);

        return $length >= 100 && $length <= 512
            && preg_match('/^[A-Za-z0-9_\-:.]+$/', $destination) === 1;
    }

    public function createDriver(): AbstractDriver
    {
        return new FirebaseCloudMessagingDriver($this->config->getFirebaseConfig());
    }
}
