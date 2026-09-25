<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Channels;

use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Contracts\Configs\NotificationConfigInterface;
use AndyDefer\LaravelNotification\Drivers\PusherDriver;

final class PusherChannel extends AbstractChannel
{
    public function __construct(
        NotificationConfigInterface $config,
    ) {
        parent::__construct($config);
    }

    public function getName(): string
    {
        return 'pusher';
    }

    public function getLabel(): string
    {
        return 'Pusher';
    }

    public function getIcon(): string
    {
        return 'radio';
    }

    public function isEnabled(): bool
    {
        return $this->config->isPusherEnabled();
    }

    public static function validateDestination(string $destination): bool
    {
        if ($destination === '') {
            return false;
        }

        if (strlen($destination) > 164) {
            return false;
        }

        return preg_match('/^[a-zA-Z0-9_\-=@,.;]+$/', $destination) === 1;
    }

    public function createDriver(): AbstractDriver
    {
        return new PusherDriver($this->config->getPusherConfig());
    }
}
