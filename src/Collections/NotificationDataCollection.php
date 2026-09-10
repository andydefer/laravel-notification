<?php

// src/Collections/NotificationDataCollection.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelNotification\Datas\NotificationData;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;

/**
 * @extends AbstractTypedCollection<NotificationData>
 */
final class NotificationDataCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(NotificationData::class);
    }

    /**
     * Filter only unread notifications.
     */
    public function unread(): self
    {
        $collection = new self;
        foreach ($this->items as $item) {
            if (! $item->readAt) {
                $collection->add($item);
            }
        }

        return $collection;
    }

    /**
     * Filter only read notifications.
     */
    public function read(): self
    {
        $collection = new self;
        foreach ($this->items as $item) {
            if ($item->readAt) {
                $collection->add($item);
            }
        }

        return $collection;
    }

    /**
     * Filter notifications by status.
     */
    public function byStatus(NotificationStatus $status): self
    {
        $collection = new self;
        foreach ($this->items as $item) {
            if ($item->status === $status) {
                $collection->add($item);
            }
        }

        return $collection;
    }

    /**
     * Filter notifications by channel.
     */
    public function byChannel(string $channelClass): self
    {
        $collection = new self;
        foreach ($this->items as $item) {
            if ($item->channel === $channelClass) {
                $collection->add($item);
            }
        }

        return $collection;
    }

    /**
     * Filter sent notifications.
     */
    public function sent(): self
    {
        return $this->byStatus(NotificationStatus::SENT);
    }

    /**
     * Filter pending notifications.
     */
    public function pending(): self
    {
        return $this->byStatus(NotificationStatus::PENDING);
    }

    /**
     * Filter failed notifications.
     */
    public function failed(): self
    {
        return $this->byStatus(NotificationStatus::FAILED);
    }

    /**
     * Count unread notifications.
     */
    public function unreadCount(): int
    {
        return $this->unread()->count();
    }

    /**
     * Count sent notifications.
     */
    public function sentCount(): int
    {
        return $this->sent()->count();
    }

    /**
     * Count pending notifications.
     */
    public function pendingCount(): int
    {
        return $this->pending()->count();
    }

    /**
     * Count failed notifications.
     */
    public function failedCount(): int
    {
        return $this->failed()->count();
    }

    /**
     * Check if there are unread notifications.
     */
    public function hasUnread(): bool
    {
        foreach ($this->items as $item) {
            if (! $item->readAt) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if there are failed notifications.
     */
    public function hasFailed(): bool
    {
        foreach ($this->items as $item) {
            if ($item->status === NotificationStatus::FAILED) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get only the IDs.
     *
     * @return array<string>
     */
    public function ids(): array
    {
        $ids = [];
        foreach ($this->items as $item) {
            $ids[] = $item->id;
        }

        return $ids;
    }
}
