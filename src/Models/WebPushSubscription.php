<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Models;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\WebPushChannel;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Contracts\PingableInterface;
use AndyDefer\LaravelNotification\Traits\HasPingPong;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class WebPushSubscription extends Model implements NotifiableInterface, PingableInterface
{
    use HasPingPong;
    use HasUuids;

    protected $table = 'web_push_subscriptions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'endpoint',
        'p256dh',
        'auth',
        'browser',
        'user_agent',
        'last_seen_at',
        'notifiable_type',
        'notifiable_id',
    ];

    protected $casts = [
        'last_seen_at' => 'immutable_datetime',
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;

        $collection->add(new NotificationRouteVO(
            channelClass: WebPushChannel::class,
            destination: (string) $this->endpoint,
            metadata: new StrictDataObject([
                'type' => 'webpush',
                'endpoint' => (string) $this->endpoint,
                'p256dh' => (string) $this->p256dh,
                'auth' => (string) $this->auth,
            ]),
        ));

        return $collection;
    }
}
