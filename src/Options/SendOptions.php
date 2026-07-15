<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Options;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;

final class SendOptions
{
    public function __construct(
        public readonly ?FqcnChannelCollection $channels = null,
        public readonly ?int $limitPerChannel = null,
        public readonly ?StrictAssociative $destinationFilters = null,
    ) {}

    /**
     * Create a new SendOptions instance.
     */
    public static function init(): self
    {
        return new self;
    }

    public function withChannel(string $channelClass): self
    {
        $collection = $this->channels ?? new FqcnChannelCollection;
        $collection->add(new FqcnChannelVO($channelClass));

        return new self(
            channels: $collection,
            limitPerChannel: $this->limitPerChannel,
            destinationFilters: $this->destinationFilters,
        );
    }

    public function withChannels(array $channelClasses): self
    {
        // ✅ Fusionner avec les canaux existants au lieu de remplacer
        $collection = $this->channels ?? new FqcnChannelCollection;

        foreach ($channelClasses as $channel) {
            // ✅ Éviter les doublons
            if (! $collection->hasChannel($channel)) {
                $collection->add(new FqcnChannelVO($channel));
            }
        }

        return new self(
            channels: $collection,
            limitPerChannel: $this->limitPerChannel,
            destinationFilters: $this->destinationFilters,
        );
    }

    public function withLimitPerChannel(int $limit): self
    {
        return new self(
            channels: $this->channels,
            limitPerChannel: $limit,
            destinationFilters: $this->destinationFilters,
        );
    }

    public function withDestinationFilter(string $channelClass, string|array $destinations): self
    {
        $filters = $this->destinationFilters?->toArray() ?? [];

        if (! isset($filters[$channelClass])) {
            $filters[$channelClass] = [];
        }

        if (is_array($destinations)) {
            $filters[$channelClass] = array_merge($filters[$channelClass], $destinations);
        } else {
            $filters[$channelClass][] = $destinations;
        }

        return new self(
            channels: $this->channels,
            limitPerChannel: $this->limitPerChannel,
            destinationFilters: new StrictAssociative($filters),
        );
    }

    public function withDestinationFilters(array $filters): self
    {
        return new self(
            channels: $this->channels,
            limitPerChannel: $this->limitPerChannel,
            destinationFilters: new StrictAssociative($filters),
        );
    }

    public function getDestinationFilters(): ?StrictAssociative
    {
        return $this->destinationFilters;
    }
}
