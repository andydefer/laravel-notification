<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\PusherConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Pusher\Pusher;
use Pusher\PusherException;
use RuntimeException;

final class PusherDriver extends AbstractDriver
{
    private const CHANNEL_KEY = 'channel';

    private const EVENT_KEY = 'event';

    private const DEFAULT_EVENT = 'notification';

    private ?Pusher $client = null;

    public function __construct(private readonly PusherConfigRecord $config) {}

    public function getChannel(): string
    {
        return 'pusher';
    }

    public function validateConfiguration(): bool
    {
        return $this->config->enabled
            && ! empty($this->config->app_id)
            && ! empty($this->config->key)
            && ! empty($this->config->secret)
            && ! empty($this->config->cluster);
    }

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        if (! $this->validateConfiguration()) {
            throw new RuntimeException('Pusher configuration is incomplete.');
        }

        $channel = $this->resolveChannelName($route);
        $event = $this->resolveEventName($route);

        try {
            $this->client()->trigger($channel, $event, $message->toArray());
        } catch (PusherException $exception) {
            throw new RuntimeException(
                sprintf('Pusher trigger failed: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }

        return true;
    }

    private function resolveChannelName(NotificationRouteVO $route): string
    {
        $channel = $route->getMetadata()?->get(self::CHANNEL_KEY);

        if (! is_string($channel) || $channel === '') {
            $channel = $route->getDestination();
        }

        if (! is_string($channel) || $channel === '') {
            $channel = $this->config->default_channel;
        }

        return $channel;
    }

    private function resolveEventName(NotificationRouteVO $route): string
    {
        $event = $route->getMetadata()?->get(self::EVENT_KEY);

        return is_string($event) && $event !== '' ? $event : self::DEFAULT_EVENT;
    }

    private function client(): Pusher
    {
        if ($this->client instanceof Pusher) {
            return $this->client;
        }

        $this->client = new Pusher(
            $this->config->key,
            $this->config->secret,
            $this->config->app_id,
            [
                'cluster' => $this->config->cluster,
                'useTLS' => $this->config->use_tls,
                'timeout' => $this->config->timeout,
            ],
        );

        return $this->client;
    }
}
