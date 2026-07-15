<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\ValueObjects;

use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use Illuminate\Database\Eloquent\Model;

final class DirectNotifiable extends Model implements NotifiableInterface
{
    private string $morphClass = 'direct';

    private int|string $key = 0;

    private NotificationRouteCollection $routes;

    public function __construct(NotificationRouteCollection $routes)
    {
        $this->routes = $routes;
    }

    public function getNotificationChannels(): NotificationRouteCollection
    {
        return $this->routes;
    }

    public function getMorphClass(): string
    {
        return $this->morphClass;
    }

    public function getKey(): int|string
    {
        return $this->key;
    }

    public function setMorphClass(string $morphClass): self
    {
        $this->morphClass = $morphClass;

        return $this;
    }

    public function setKey(int|string $key): self
    {
        $this->key = $key;

        return $this;
    }
}
