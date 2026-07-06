<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;

final class NotificationRouteVO extends AbstractValueObject
{
    private FqcnChannelVO $channel;

    private DestinationVO $destination;

    private ?StrictDataObject $metadata;

    public function __construct(
        string $channelClass,
        string $destination,
        ?StrictDataObject $metadata = null
    ) {
        $this->channel = new FqcnChannelVO($channelClass);
        $this->destination = new DestinationVO($destination);
        $this->metadata = $metadata;

        $this->channel->validateDestination($this->destination->getValue());
    }

    public function getDefinition(): object
    {
        return app($this->channel->getValue());
    }

    public function getName(): string
    {
        return $this->getDefinition()->getName();
    }

    public function getLabel(): string
    {
        return $this->getDefinition()->getLabel();
    }

    public function getIcon(): string
    {
        return $this->getDefinition()->getIcon();
    }

    public function getConfigKey(): string
    {
        return $this->getDefinition()->getConfigKey();
    }

    public function requiresConfiguration(): bool
    {
        return $this->getDefinition()->requiresConfiguration();
    }

    public function isEnabled(): bool
    {
        return $this->getDefinition()->isEnabled();
    }

    public function getConfig(): AbstractRecord
    {
        return $this->getDefinition()->getConfig();
    }

    public function createDriver(): AbstractDriver
    {
        return $this->getDefinition()->createDriver();
    }

    public function getChannelClass(): string
    {
        return $this->channel->getValue();
    }

    public function getDestination(): string
    {
        return $this->destination->getValue();
    }

    public function getMetadata(): ?StrictDataObject
    {
        return $this->metadata;
    }

    public function getValue(): StrictDataObject
    {
        return new StrictDataObject([
            'channel' => $this->channel->getValue(),
            'destination' => $this->destination->getValue(),
            'metadata' => $this->metadata?->toArray(),
        ]);
    }

    public function __toString(): string
    {
        return $this->getName().':'.$this->destination->getValue();
    }
}
