<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelNotification\Records\SendResultRecord;

/**
 * @extends AbstractTypedCollection<SendResultRecord>
 */
final class SendResultCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(SendResultRecord::class);
    }

    public function getSuccessCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            if ($item->success) {
                $count++;
            }
        }

        return $count;
    }

    public function getFailureCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            if (! $item->success) {
                $count++;
            }
        }

        return $count;
    }

    public function allSuccess(): bool
    {
        foreach ($this->items as $item) {
            if (! $item->success) {
                return false;
            }
        }

        return true;
    }

    public function hasFailures(): bool
    {
        foreach ($this->items as $item) {
            if (! $item->success) {
                return true;
            }
        }

        return false;
    }

    public function filterBySuccess(): self
    {
        $collection = new self;
        foreach ($this->items as $item) {
            if ($item->success) {
                $collection->add($item);
            }
        }

        return $collection;
    }

    public function filterByFailure(): self
    {
        $collection = new self;
        foreach ($this->items as $item) {
            if (! $item->success) {
                $collection->add($item);
            }
        }

        return $collection;
    }

    public function filterByChannel(string $channelClass): self
    {
        $collection = new self;
        foreach ($this->items as $item) {
            if ($item->channel->getValue() === $channelClass) {
                $collection->add($item);
            }
        }

        return $collection;
    }

    public function getSuccessfulDestinations(): array
    {
        $destinations = [];
        foreach ($this->items as $item) {
            if ($item->success) {
                $destinations[] = $item->destination;
            }
        }

        return $destinations;
    }

    public function getFailedDestinations(): array
    {
        $destinations = [];
        foreach ($this->items as $item) {
            if (! $item->success) {
                $destinations[] = $item->destination;
            }
        }

        return $destinations;
    }
}
