<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Fixtures\Models;

use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use Illuminate\Database\Eloquent\Model;

final class TestEmptyChannel extends Model implements NotifiableInterface
{
    protected $table = 'test_empty_channels';

    protected $fillable = [
        'name',
    ];

    public function getNotificationChannels(): NotificationRouteCollection
    {
        return new NotificationRouteCollection;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getKey(): int
    {
        return $this->id;
    }
}
