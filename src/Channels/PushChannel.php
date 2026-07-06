<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Channels;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Drivers\PushDriver;
use AndyDefer\LaravelNotification\Records\PushConfigRecord;

final class PushChannel extends AbstractChannel
{
    public function getName(): string
    {
        return 'push';
    }

    public function getLabel(): string
    {
        return 'Push Notification';
    }

    public function getIcon(): string
    {
        return '🔔';
    }

    public function isEnabled(): bool
    {
        return $this->configRepository->get('notification.channels.push.enabled', false);
    }

    public function getConfig(): AbstractRecord
    {
        $config = $this->configRepository->get('notification.channels.push', [
            'enabled' => false,
            'platform' => 'fcm',
            'fcm_api_key' => env('FCM_API_KEY'),
            'fcm_project_id' => env('FCM_PROJECT_ID'),
            'apns_key_path' => env('APNS_KEY_PATH'),
            'apns_key_id' => env('APNS_KEY_ID'),
            'apns_team_id' => env('APNS_TEAM_ID'),
            'apns_bundle_id' => env('APNS_BUNDLE_ID'),
            'default_sound' => 'default',
            'default_tokens' => [],
        ]);

        return PushConfigRecord::from($config);
    }

    public function createDriver(): AbstractDriver
    {
        /** @var PushConfigRecord $config */
        $config = $this->getConfig();

        return new PushDriver($config);
    }

    public static function validateDestination(string $destination): bool
    {
        return ! empty($destination) && strlen($destination) > 10;
    }
}
