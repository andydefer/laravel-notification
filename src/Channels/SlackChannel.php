<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Channels;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Drivers\SlackDriver;
use AndyDefer\LaravelNotification\Records\SlackConfigRecord;

final class SlackChannel extends AbstractChannel
{
    public function getName(): string
    {
        return 'slack';
    }

    public function getLabel(): string
    {
        return 'Slack';
    }

    public function getIcon(): string
    {
        return '💼';
    }

    public function isEnabled(): bool
    {
        return $this->config->getSlackConfig()->enabled;
    }

    public function getConfig(): AbstractRecord
    {
        return $this->config->getSlackConfig();
    }

    public function createDriver(): AbstractDriver
    {
        /** @var SlackConfigRecord $config */
        $config = $this->getConfig();

        return new SlackDriver($config);
    }

    public static function validateDestination(string $destination): bool
    {
        return filter_var($destination, FILTER_VALIDATE_URL) !== false
            && str_contains($destination, 'hooks.slack.com');
    }
}
