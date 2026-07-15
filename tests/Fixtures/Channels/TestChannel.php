<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Fixtures\Channels;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Tests\Fixtures\Drivers\AlwaysSuccessDriver;

final class TestChannel extends AbstractChannel
{
    public function getName(): string
    {
        return 'test';
    }

    public function getLabel(): string
    {
        return 'Test';
    }

    public function getIcon(): string
    {
        return '🧪';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getConfig(): AbstractRecord
    {
        return new class extends AbstractRecord {};
    }

    public function createDriver(): AbstractDriver
    {
        return new AlwaysSuccessDriver;
    }

    public static function validateDestination(string $destination): bool
    {
        return true;
    }
}
