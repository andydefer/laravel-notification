<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;

/**
 * @extends AbstractTypedCollection<FqcnChannelVO>
 */
final class FqcnChannelCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(FqcnChannelVO::class);
    }

    public function getChannelClasses(): array
    {
        $classes = [];
        foreach ($this->items as $item) {
            $classes[] = $item->getValue();
        }

        return $classes;
    }

    public function filterByChannel(string $channelClass): self
    {
        $collection = new self;
        foreach ($this->items as $item) {
            if ($item->getValue() === $channelClass) {
                $collection->add($item);
            }
        }

        return $collection;
    }

    public function filterByChannels(array $channelClasses): self
    {
        $collection = new self;
        foreach ($this->items as $item) {
            if (in_array($item->getValue(), $channelClasses, true)) {
                $collection->add($item);
            }
        }

        return $collection;
    }

    public function hasChannel(string $channelClass): bool
    {
        foreach ($this->items as $item) {
            if ($item->getValue() === $channelClass) {
                return true;
            }
        }

        return false;
    }
}
