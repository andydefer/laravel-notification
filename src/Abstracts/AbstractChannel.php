<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Abstracts;

use AndyDefer\LaravelNotification\Contracts\ChannelInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

abstract class AbstractChannel implements ChannelInterface
{
    public function __construct(
        protected readonly ConfigRepository $configRepository,
    ) {}

    abstract public function createDriver(): AbstractDriver;
}
