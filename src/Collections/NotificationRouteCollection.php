<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

/**
 * @extends AbstractTypedCollection<NotificationRouteVO>
 */
final class NotificationRouteCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(NotificationRouteVO::class);
    }

    public function filterByDestination(string $destination): self
    {
        $collection = new self;
        foreach ($this->items as $item) {
            if ($item->getDestination() === $destination) {
                $collection->add($item);
            }
        }

        return $collection;
    }

    public function filterByChannel(string $channelClass): self
    {
        $collection = new self;
        foreach ($this->items as $item) {
            if ($item->getChannelClass() === $channelClass) {
                $collection->add($item);
            }
        }

        return $collection;
    }

    public function filterByChannels(array $channelClasses): self
    {
        $collection = new self;
        foreach ($this->items as $item) {
            if (in_array($item->getChannelClass(), $channelClasses, true)) {
                $collection->add($item);
            }
        }

        return $collection;
    }

    public function filterByMetadataKey(string $key, mixed $value): self
    {
        $collection = new self;
        foreach ($this->items as $item) {
            $metadata = $item->getMetadata();
            if ($metadata !== null && $metadata->has($key) && $metadata->get($key) === $value) {
                $collection->add($item);
            }
        }

        return $collection;
    }

    public function hasDestination(string $destination): bool
    {
        foreach ($this->items as $item) {
            if ($item->getDestination() === $destination) {
                return true;
            }
        }

        return false;
    }

    public function hasChannel(string $channelClass): bool
    {
        foreach ($this->items as $item) {
            if ($item->getChannelClass() === $channelClass) {
                return true;
            }
        }

        return false;
    }

    public function getUniqueChannels(): self
    {
        $collection = new self;
        $seen = [];
        foreach ($this->items as $item) {
            $class = $item->getChannelClass();
            if (! in_array($class, $seen, true)) {
                $seen[] = $class;
                $collection->add($item);
            }
        }

        return $collection;
    }

    public function getDefinitionsMap(): array
    {
        $map = [];
        foreach ($this->items as $item) {
            $class = $item->getChannelClass();
            if (! isset($map[$class])) {
                $map[$class] = $item->getDefinition();
            }
        }

        return $map;
    }
}
