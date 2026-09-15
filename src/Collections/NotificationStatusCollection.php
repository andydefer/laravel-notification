<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;

/**
 * @extends AbstractTypedCollection<NotificationStatus>
 */
final class NotificationStatusCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(NotificationStatus::class);
    }

    /**
     * @return array<int, string>
     */
    public function toValues(): array
    {
        return array_map(
            fn (NotificationStatus $status): string => $status->value,
            $this->toArray(),
        );
    }
}
