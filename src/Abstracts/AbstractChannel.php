<?php

// src/Abstracts/AbstractChannel.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Abstracts;

use AndyDefer\LaravelNotification\Contracts\ChannelInterface;
use AndyDefer\LaravelNotification\Contracts\Configs\NotificationConfigInterface;

abstract class AbstractChannel implements ChannelInterface
{
    public function __construct(
        protected readonly NotificationConfigInterface $config,
    ) {}

    abstract public function createDriver(): AbstractDriver;
}
