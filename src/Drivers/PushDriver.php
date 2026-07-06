<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\PushConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

final class PushDriver extends AbstractDriver
{
    public function __construct(
        private readonly PushConfigRecord $config,
    ) {}

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $metadata = $route->getMetadata();

        $tokens = $metadata?->get('tokens') ?? $this->config->default_tokens ?? [];

        if (empty($tokens)) {
            throw new \RuntimeException('Push notification tokens not specified.');
        }

        if (is_string($tokens)) {
            $tokens = [$tokens];
        }

        $platform = $metadata?->get('platform') ?? $this->config->platform ?? 'fcm';

        $payload = [
            'title' => $message->getSubjectValue(),
            'body' => $message->getBodyValue(),
            'data' => $metadata?->get('data') ?? [],
            'sound' => $metadata?->get('sound') ?? $this->config->default_sound,
            'badge' => $metadata?->get('badge') ?? 1,
            'click_action' => $metadata?->get('click_action') ?? null,
        ];

        // Simulation d'envoi
        // À remplacer par l'implémentation réelle (FCM, APNS, etc.)

        return true;
    }

    public function getChannel(): string
    {
        return 'push';
    }

    public function validateConfiguration(): bool
    {
        return $this->config->enabled
            && ($this->config->fcm_api_key !== null
                || $this->config->apns_key_path !== null
                || $this->config->default_tokens !== null);
    }
}
